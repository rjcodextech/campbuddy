
const CACHE="campbuddy-v6";
const ASSETS=["./","./index.html","./styles.css","./app.js","./manifest.webmanifest",
  "./icon.svg","./favicon.png","./logo.png","./wcr/web/mascot-wappu.png","./wcr/web/qr-mark.png",
  "./wcr/web/icon-512.png","./wcr/web/logo-header.png"];
self.addEventListener("install",e=>e.waitUntil(caches.open(CACHE).then(c=>c.addAll(ASSETS))));
self.addEventListener("activate",e=>e.waitUntil(caches.keys().then(keys=>Promise.all(keys.filter(k=>k!==CACHE).map(k=>caches.delete(k))))));
// Only the API itself needs to stay network-fresh; images from the same
// host (video thumbnails, sponsor logos) are fine to cache normally.
self.addEventListener("fetch",e=>{
  if(e.request.method!=="GET") return;
  const url=new URL(e.request.url);
  if(url.hostname==="wpsimplified.in"&&url.pathname.startsWith("/wp-json/")) return;
  e.respondWith(caches.match(e.request).then(cached=>cached||fetch(e.request).then(r=>{
    const copy=r.clone(); caches.open(CACHE).then(c=>c.put(e.request,copy)); return r;
  }).catch(()=>caches.match("./index.html"))));
});
