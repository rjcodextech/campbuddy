import fs from 'node:fs';
import { launchChrome } from 'file:///E:/Sites/InHouse/campbuddy/V2/tests/browser/helpers/chrome.mjs';

const c = await launchChrome();
try {
  const b64_512 = fs.readFileSync('E:/Sites/InHouse/campbuddy/marketing/usp-campaign/shared/img/icon-512.png').toString('base64');
  const b64_fav = fs.readFileSync('E:/Sites/InHouse/campbuddy/marketing/usp-campaign/shared/img/favicon.png').toString('base64');
  
  const res = await c.send('Runtime.evaluate', {
    expression: `(async () => {
      async function inspect(b64) {
        const img = new Image();
        img.src = 'data:image/png;base64,' + b64;
        await new Promise(r => img.onload = r);
        const cvs = document.createElement('canvas');
        cvs.width = img.width; cvs.height = img.height;
        const ctx = cvs.getContext('2d');
        ctx.drawImage(img, 0, 0);
        return {
          w: img.width,
          h: img.height,
          tl: Array.from(ctx.getImageData(0, 0, 1, 1).data),
          center: Array.from(ctx.getImageData(img.width/2, img.height/2, 1, 1).data),
          sample50: Array.from(ctx.getImageData(50, 50, 1, 1).data),
          sample200: Array.from(ctx.getImageData(200, 200, 1, 1).data)
        };
      }
      return {
        icon512: await inspect('${b64_512}'),
        favicon: await inspect('${b64_fav}')
      };
    })()`,
    awaitPromise: true,
    returnByValue: true
  });
  console.log(JSON.stringify(res.result.value, null, 2));
} finally {
  await c.close();
}
