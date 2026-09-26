// node --test tests/js  — one window tells the others that someone's record changed.
import assert from 'node:assert/strict';
import { test } from 'node:test';
import { freshImport } from './helpers/fake-browser.mjs';

const PATH = new URL('../../resources/js/attendee/people-sync.js', import.meta.url).href;
const settle = () => new Promise((resolve) => setTimeout(resolve, 40));

// Each import is a separate copy of the module with its own channel: two windows.
const windows = async () => ({ a: await freshImport(PATH), b: await freshImport(PATH) });
const close = (...all) => all.forEach((w) => w.closePeopleChannel());

test('another window is told which event changed; the window that changed it is not told', async () => {
  const { a, b } = await windows();
  const heardByB = [];
  const heardByA = [];
  b.onPeopleChanged((id) => heardByB.push(id));
  a.onPeopleChanged((id) => heardByA.push(id));

  a.notifyPeopleChanged(7);
  await settle();

  assert.deepEqual(heardByB, [7]);
  assert.deepEqual(heardByA, [], 'it does not hear itself');
  close(a, b);
});

test('every change is heard, in order', async () => {
  const { a, b } = await windows();
  const heard = [];
  b.onPeopleChanged((id) => heard.push(id));

  a.notifyPeopleChanged(1);
  a.notifyPeopleChanged(1);
  a.notifyPeopleChanged(2);
  await settle();

  assert.deepEqual(heard, [1, 1, 2]);
  close(a, b);
});

test('after unsubscribing nothing more is heard', async () => {
  const { a, b } = await windows();
  const heard = [];
  const stop = b.onPeopleChanged((id) => heard.push(id));

  a.notifyPeopleChanged(1);
  await settle();
  stop();
  a.notifyPeopleChanged(2);
  await settle();

  assert.deepEqual(heard, [1]);
  close(a, b);
});

test('messages that are not about people are ignored', async () => {
  const { b } = await windows();
  const heard = [];
  b.onPeopleChanged((id) => heard.push(id));

  const other = new BroadcastChannel('campbuddy-people');
  other.postMessage({ type: 'something-else', eventId: 9 });
  other.postMessage('plain text');
  other.postMessage({ type: 'people', eventId: 3 });
  await settle();
  other.close();

  assert.deepEqual(heard, [3]);
  close(b);
});

test('a browser without BroadcastChannel: nothing is sent, nothing throws, nothing is heard', async () => {
  const original = globalThis.BroadcastChannel;
  globalThis.BroadcastChannel = undefined;

  try {
    const w = await freshImport(PATH);
    const heard = [];
    const stop = w.onPeopleChanged((id) => heard.push(id));

    assert.doesNotThrow(() => w.notifyPeopleChanged(1));
    assert.equal(typeof stop, 'function');
    assert.doesNotThrow(stop);
    assert.doesNotThrow(() => w.closePeopleChannel());
    assert.deepEqual(heard, []);
  } finally {
    globalThis.BroadcastChannel = original;
  }
});
