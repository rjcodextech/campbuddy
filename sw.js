
const CACHE="campbuddy-v9";
const ASSETS=["./","./index.html","./styles.css?v=9","./app.js?v=9","./manifest.webmanifest",
  "./icon.svg","./favicon.png","./logo.png","./wcr/web/mascot-wappu.png","./wcr/web/qr-mark.png",
  "./wcr/web/icon-512.png","./wcr/web/logo-header.png"];
self.addEventListener("install",e=>e.waitUntil(caches.open(CACHE).then(c=>c.addAll(ASSETS))));
self.addEventListener("activate",e=>e.waitUntil(caches.keys().then(keys=>Promise.all(keys.filter(k=>k!==CACHE).map(k=>caches.delete(k))))));
// The API needs to stay network-fresh (handled below). CSS/JS also stay
// network-first so edits show up on the next reload instead of being stuck
// behind a stale cache until CACHE is bumped; everything else (images, etc.)
// is fine to serve cache-first.
self.addEventListener("fetch",e=>{
  if(e.request.method!=="GET") return;
  const url=new URL(e.request.url);
  if(url.origin===location.origin&&url.pathname.startsWith("/backend/api/")) return;
  if(url.origin===location.origin&&/\.(?:css|js)$/.test(url.pathname)){
    e.respondWith(fetch(e.request).then(r=>{
      const copy=r.clone(); caches.open(CACHE).then(c=>c.put(e.request,copy)); return r;
    }).catch(()=>caches.match(e.request)));
    return;
  }
  e.respondWith(caches.match(e.request).then(cached=>cached||fetch(e.request).then(r=>{
    const copy=r.clone(); caches.open(CACHE).then(c=>c.put(e.request,copy)); return r;
  }).catch(()=>caches.match("./index.html"))));
});
