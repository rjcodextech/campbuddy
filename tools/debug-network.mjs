import { launchChrome } from '../tests/browser/helpers/chrome.mjs';

const c = await launchChrome();
try {
  c.send('Network.enable');
  const failed = [];
  const responses = [];
  c.on('Network.responseReceived', (params) => {
    responses.push({ url: params.response.url, status: params.response.status });
  });
  c.on('Network.loadingFailed', (params) => {
    failed.push(params);
  });

  await c.goto('http://campbuddy.test');
  await new Promise(r => setTimeout(r, 2000));
  
  console.log('Failed requests:', failed);
  console.log('Responses:', responses.filter(r => r.status !== 200));
} catch (e) {
  console.error(e);
} finally {
  await c.close();
}
