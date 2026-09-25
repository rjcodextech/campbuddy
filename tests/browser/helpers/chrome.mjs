// A dependency-free driver for a real Chrome/Chromium/Edge over the DevTools
// protocol (Node 22+ has a built-in WebSocket). Used by the browser tests.

import { spawn, spawnSync } from 'node:child_process';
import fs from 'node:fs';
import os from 'node:os';
import path from 'node:path';

const CANDIDATES = [
  process.env.CHROME_PATH,
  'C:/Program Files/Google/Chrome/Application/chrome.exe',
  'C:/Program Files (x86)/Google/Chrome/Application/chrome.exe',
  'C:/Program Files (x86)/Microsoft/Edge/Application/msedge.exe',
  '/Applications/Google Chrome.app/Contents/MacOS/Google Chrome',
  '/usr/bin/google-chrome',
  '/usr/bin/google-chrome-stable',
  '/usr/bin/chromium',
  '/usr/bin/chromium-browser',
].filter(Boolean);

export const sleep = (ms) => new Promise((resolve) => setTimeout(resolve, ms));

export function findChrome() {
  return CANDIDATES.find((candidate) => fs.existsSync(candidate));
}

export async function launchChrome({ port = 9400 + Math.floor(Math.random() * 400) } = {}) {
  const exe = findChrome();
  if (!exe) throw new Error('No Chrome/Chromium/Edge found. Set CHROME_PATH to its executable.');

  const profile = fs.mkdtempSync(path.join(os.tmpdir(), 'campbuddy-e2e-'));
  const chrome = spawn(exe, ['--headless=new', '--disable-gpu', '--no-first-run', '--no-default-browser-check', `--remote-debugging-port=${port}`, `--user-data-dir=${profile}`, 'about:blank'], { stdio: 'ignore' });

  let target = null;
  for (let i = 0; i < 60 && !target; i++) {
    await sleep(250);
    try {
      target = (await (await fetch(`http://127.0.0.1:${port}/json`)).json()).find((t) => t.type === 'page');
    } catch {
      // Not up yet.
    }
  }
  if (!target) {
    chrome.kill();
    throw new Error('Chrome did not start');
  }

  const ws = new WebSocket(target.webSocketDebuggerUrl);
  await new Promise((resolve) => { ws.onopen = resolve; });

  let id = 0;
  const pending = new Map();
  const listeners = [];
  ws.onmessage = (message) => {
    const data = JSON.parse(message.data);
    if (data.id && pending.has(data.id)) {
      pending.get(data.id)(data);
      pending.delete(data.id);
    } else {
      listeners.forEach((fn) => fn(data));
    }
  };

  const send = (method, params = {}) => new Promise((resolve) => {
    const call = ++id;
    pending.set(call, resolve);
    ws.send(JSON.stringify({ id: call, method, params }));
  });

  await send('Page.enable');
  await send('Runtime.enable');

  const api = {
    send,
    /** Runs an expression in the page; resolves its value, or undefined if the page is navigating. */
    async evaluate(expression) {
      const reply = await send('Runtime.evaluate', { expression, returnByValue: true, awaitPromise: true });

      return reply.result?.result?.value;
    },
    /** Navigates and waits for the load event. Resolves the HTTP status of the main document. */
    async goto(url) {
      let status = null;
      const listener = (data) => {
        if (data.method === 'Network.responseReceived' && data.params.type === 'Document') status = data.params.response.status;
      };
      await send('Network.enable');
      listeners.push(listener);
      const loaded = new Promise((resolve) => {
        const on = (data) => {
          if (data.method === 'Page.loadEventFired') {
            listeners.splice(listeners.indexOf(on), 1);
            resolve();
          }
        };
        listeners.push(on);
      });
      await send('Page.navigate', { url });
      await Promise.race([loaded, sleep(20000)]);
      listeners.splice(listeners.indexOf(listener), 1);

      return status;
    },
    async waitFor(expression, { timeout = 30000, every = 200 } = {}) {
      const start = Date.now();
      while (Date.now() - start < timeout) {
        const value = await api.evaluate(expression).catch(() => undefined);
        if (value) return value;
        await sleep(every);
      }
      throw new Error(`Timed out waiting for: ${expression.slice(0, 120)}`);
    },
    async close() {
      try { ws.close(); } catch { /* already closed */ }
      if (process.platform === 'win32') spawnSync('taskkill', ['/F', '/T', '/PID', String(chrome.pid)]);
      else chrome.kill('SIGKILL');
      await sleep(300);
      try { fs.rmSync(profile, { recursive: true, force: true }); } catch { /* still locked: the OS temp cleaner gets it */ }
    },
  };

  return api;
}
