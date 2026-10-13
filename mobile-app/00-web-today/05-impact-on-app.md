# Naye features ka app plan par asar

| Naya web feature | App plan mein kya badla |
|---|---|
| Day 7 / day 15 / Memories / upcoming push | Phase 2 FCM ko ye **paanchon** push bhejne hain, sirf thank-you nahi → [02-hybrid-app/05-backend-changes.md](../02-hybrid-app/05-backend-changes.md) |
| Notifications tab | Native push bhi isi list mein dikhe, warna organizer ko adhoori tasveer |
| Retention 30 din | `native_push_tokens` ki retention bhi 30 din |
| `/event/{slug}/memories` | Deep link list mein jod diya |
| QR kit | Store ke baad ek **app-install QR** bhi (desktop notice + kit) |
| iPhone notice card | App ke andar chhupana hai (Package A ka dusra hissa) |
| Bring-to-next-WordCamp | App ka storage mitta nahi, to ye app mein aur aasan; device transfer ab bhi chahiye (naya phone) |
| Naya `sw.js` | `WKAppBoundDomains` + deploy par cache version dhyan se |
| Discovery mein report/block nahi | **MVP blocker** (Apple 1.2) |
