# CampBuddy — Deployment aur Launch Checklist

*26 Sep 2026.* Yeh file live site par naye badlav deploy karne, Cloudflare set karne aur uske baad test karne ki poori list hai.

## Ek nazar me

4 commits tayyar hain aur test ho chuke hain (`dev` branch), par live site par abhi **kuch deploy nahi hua** hai. Neeche ke steps se karna hai.

| Commit | Kya karta hai | Deploy ka jokhim |
| --- | --- | --- |
| `346ac86` Retention | Event ka asli aakhri din (end date khaali ho tab bhi) + 3 din tak kuch archive/expire nahi | Kam |
| `5e0537a` Crowd | Roster/discovery/data-version ek baar banke sabko jate hain (ETag/304), polling shant, reminders time par, load-test script | Kam-madhyam |
| `20f9e2b` Offline | Poora event phone me save, kuch delete nahi, attendee list offline, offline reminder queue | Madhyam (Service Worker) |
| `856e71a` LiteSpeed + photos | `.htaccess` rules LiteSpeed ke liye theek (live test me mili kami), Gravatar photos offline | Kam-madhyam |

Tests: PHP 28/29 files pass (bacha hua `AnalyticsRegistryTest` sirf mere Windows machine ke OpenSSL ki wajah se fail hota hai), JS unit 124/124, asli Chrome me end-to-end 7/7.

## Live site ka test: Cloudflare abhi kya kar raha hai

Cloudflare me jo caching aapne chalu ki hai wo abhi pages aur API ko cache **nahi** kar rahi — 1,697 me se 0 responses `HIT` the — isliye aapke host ko koi rahat nahi mili (26 Sep 2026, sirf padhne wali requests).

| Address | Cloudflare (`cf-cache-status`) | Matlab |
| --- | --- | --- |
| Home, event pages, `/my-day` | BYPASS | Origin `no-cache, private` bolta hai aur Cloudflare uski baat maan raha hai. HTML ko cache nahi karna hi sahi hai. |
| `/api/.../roster`, `/discovery` | BYPASS | Abhi purane headers hain. Deploy ke baad `public, s-maxage=30` aayega, tab ye HIT ban sakte hain. |
| `/api/.../data-version` | BYPASS | Deploy ke baad 20 sec ke liye HIT. `cache-version` hamesha no-store rahega. |
| `/sw.js` | **HIT**, koi Cache-Control nahi | Cloudflare purana `sw.js` edge par rakh sakta hai (default ~2 ghante). Deploy ke baad **Purge zaroori**. |
| `/build/manifest.json` | **HIT** | Naya build deploy karne par purana manifest edge se aa sakta hai. Purge zaroori. |
| `/build/assets/*.js` | HIT, par `Cache-Control` header hi nahi | Hashed files "immutable" nahi hain (neeche wajah). |
| `/media/*.png` | HIT, `public, max-age=604800` | 7 din. Logo badle to 7 din tak purana dikh sakta hai. |
| `/sw-flags.json` | 404 | Naya file hai, abhi deploy nahi hua. |

**Wahi 150-user test dobara** (same settings): p95 **2.3 s** (pehle 2.1 s), 22 req/s, 0 errors, sab responses BYPASS. Yani abhi host par sab kuch wahi load hai — naye headers deploy hone ke baad hi fark padega.

**Do badi khoj:**

1. Aapka origin server **LiteSpeed** hai (`x-turbo-charged-by: LiteSpeed`). LiteSpeed `.htaccess` ke `<If>` blocks ko chupchap ignore karta hai ([LiteSpeed docs](https://docs.litespeedtech.com/lscache/devguide/controls/)); `<Files>` aur `<FilesMatch>` chalte hain. Live proof: hashed assets par 1 saal wala immutable header nahi aa raha. Isliye `/sw.js` ka `<If>` rule bhi live par kaam nahi karta tha — commit `856e71a` me isko `<Files>` me badal diya gaya hai.
2. Cloudflare ki koi extra feature (Rocket Loader, email obfuscation, Web Analytics beacon) HTML me inject nahi ho rahi. Achchi baat.

## Deploy se pehle: ye sab tick karein

Deploy ka sahi samay: kam traffic ka waqt (raat), aur pehle event se kam se kam 2–3 din pehle (Sylhet 1 Oct se shuru hota hai, Rajasthan 3–4 Oct).

- [ ] `dev` ki 4 commits deploy wali branch me daali hain: `346ac86`, `5e0537a`, `20f9e2b`, `856e71a`. **Koi naya database migration nahi hai**, isliye DB me kuch nahi badlega.
- [ ] Server ka `.env`: `APP_ENV=production`, `APP_DEBUG=false`, `VAPID_PUBLIC_KEY` / `VAPID_PRIVATE_KEY` / `VAPID_SUBJECT` bhare hue (reminders ke liye), `QUEUE_CONNECTION=database`. (26 Sep ko server ki `.env` dekhi: ye sab theek hai. Poori list neeche "Server ka `.env`" section me.)
- [ ] Cloudflare purge button ke liye `.env` me `CLOUDFLARE_ZONE_ID` aur `CLOUDFLARE_API_TOKEN` (token sirf *Zone → Cache Purge → Purge* ki ijazat wala). Isse admin ka "Purge cache & refresh data" button Cloudflare bhi saaf karega. **Abhi server ki `.env` me ye dono nahi hain.**
- [ ] Cron chal raha hai (cPanel → Cron Jobs): `* * * * * php /path/to/artisan schedule:run >> /dev/null 2>&1`. Ye band ho to reminders, data refresh, kuch nahi chalta.
- [ ] Server par `public/hot` naam ki file **nahi** honi chahiye (ye sirf aapke computer par `npm run dev` ke liye hai). Agar hai to delete karein, warna site bina CSS ke khulegi. **26 Sep 2026 ko yahi hua:** file upload ke saath `hot` bhi chali gayi, aur live site ka HTML `http://[::1]:5173/...` (visitor ka apna computer) se CSS/JS maangne laga, jo kisi ke paas nahi hota. Upload se pehle apne computer par `npm run dev` band karein (Ctrl+C se band karne par `hot` khud hat jaati hai) ya `public/hot` ko upload se bahar rakhein; `hot` git me nahi jaati, isliye `git pull` se ye galti nahi hoti.
- [ ] Bade venue (hazaaron log ek wifi par) ho to `.env` me `RATE_LIMIT_ADDRESS_READS=20000` daalein (default 3000 per minute per IP hai). Server par ye pehle se daala hua hai.
- [ ] Database ka ek backup (cPanel → phpMyAdmin → Export), bas aadat ke liye.
- [ ] (Apne computer par, optional par acha) tests ek baar chala lein:

```bash
npm run test:js
npm run test:browser
php artisan test
```

PHP me sirf `AnalyticsRegistryTest` ke 2 tests aapke Windows par fail honge (OpenSSL config ki wajah se, code ki galti nahi). Linux server par ye pass hote hain.

## Server ka `.env`: kya rakhein, kya badlein

*26 Sep 2026 ko server ki `.env` dekhi gayi (passwords/keys ke bina).* Production ke liye ye theek hai, aur **naye code ko koi naya `.env` key nahi chahiye** (5 commits me `config/` aur `.env.example` badle hi nahi). Neeche: jo sahi hai, jo badalna chahiye, jo kabhi nahi badalna, aur `.env` badalne ke baad kya karna hai.

### Jo abhi sahi hai (aur kya karta hai)

| Key | Kya karta hai |
| --- | --- |
| `APP_ENV=production`, `APP_DEBUG=false` | Error pages par visitors ko code, DB naam ya server ki details nahi dikhti. |
| `APP_URL=https://campbuddy.club` | Sitemap aur absolute links banta hai. **Reminder push ka click-link bhi isi se banta hai**, kyunki scheduler me koi request nahi hoti. |
| `QUEUE_CONNECTION=database` | Reminders aur data-fetch jobs `jobs` table me jaate hain; cron `schedule:run` har minute inhe chalata hai. |
| `VAPID_PUBLIC_KEY`, `VAPID_PRIVATE_KEY`, `VAPID_SUBJECT` | Push notification ki pehchaan. Bhare hain. |
| `RATE_LIMIT_DEVICE_READS=200` | Ek phone per minute 200 reads kar sakta hai (default 120). App ko is se bahut kam chahiye. |
| `RATE_LIMIT_ADDRESS_READS=20000` | Ek IP se 20000 reads/minute (default 3000), taaki venue ka wifi sabko ek saath block na kare. Bots se bachav Cloudflare ka kaam hai. |
| `CACHE_STORE=database` | ETag/304 wala micro-cache, rate-limit counters aur admin locks isi me rehte hain. **Abhi mat badlein** (neeche "Kabhi na badlein"). |
| `SESSION_DRIVER=database` | Admin login ke liye. |
| `MAIL_MAILER=log` | Email bheji nahi jaati, log me likhi jaati hai. Attendee ko koi email nahi jaati, isliye chalega. |
| `GA_PROPERTY_ID`, `GA_CREDENTIALS_PATH` | Sirf ek baar `php artisan campbuddy:ga-setup` ke liye. Inse analytics ka tag **nahi** chalta. |
| `REDIS_*`, `MEMCACHED_HOST`, `AWS_*`, `BROADCAST_CONNECTION` | Use nahi hote. Rehne dein, koi nuksan nahi. |

### Jo badalna ya jodna chahiye (zaroori pehle)

Ye 6 lines `.env` me daalein ya badlein:

```ini
LOG_LEVEL=warning
LOG_STACK=daily
GA_MEASUREMENT_ID=G-XXXXXXXXXX
CLOUDFLARE_ZONE_ID=<domain ke Overview page se>
CLOUDFLARE_API_TOKEN=<sirf Cache Purge wala token>
SESSION_SECURE_COOKIE=true
```

| Badlaav | Kyon | Risk / kaise check karein |
| --- | --- | --- |
| `LOG_LEVEL` `debug` se `warning` | Bheed me ek `laravel.log` bahut bada ho sakta hai (har failed push par ek warning likhti hai). `warning` par sirf kaam ki cheezein aati hain. `LOG_STACK=daily` se har din ki alag file, 14 din tak. | Koi nahi. |
| `GA_MEASUREMENT_ID=G-…` | Iske bina production par analytics **bilkul band** hai (tag hi nahi banta). ID: GA Admin → Data streams → web stream. Sirf attendee app me chalta hai, admin me nahi. | Lagane ke baad GA → Realtime me apni visit dekhein. Event ke numbers chahiye to zaroor. |
| `CLOUDFLARE_ZONE_ID` + `CLOUDFLARE_API_TOKEN` | Admin ka "Purge cache & refresh data" button Cloudflare bhi saaf karega. Ab Cloudflare API lists cache karega, to bina iske har baar dashboard se purge karna padega. Token banane ka tareeka: neeche Cloudflare section, "C. Chhoti settings". | Token sirf *Zone → Cache Purge → Purge* aur sirf `campbuddy.club` zone. **Global API key kabhi nahi.** |
| `SESSION_SECURE_COOKIE=true` | Admin session cookie sirf HTTPS par jaati hai (`.env.example` ke production checklist me hai). | `/admin` par login karke dekhein. Login na chale to ye line hata dein. |
| GA credentials hatayein (jab `campbuddy:ga-setup` ek baar chal chuka ho) | `GA_CREDENTIALS_PATH` wali line aur `storage/app/analytics/ga-credentials.json` file dono hata dein. Ye service-account key GA property par Editor access wali hai, ab kaam ki nahi. | Least privilege. Dubara setup chalana ho to file wapas rakh dein. |

Optional, event se sambandh nahi: `GOOGLE_SITE_VERIFICATION`, `BING_SITE_VERIFICATION`, `INDEXNOW_KEY` (SEO). IndexNow ki key `APP_KEY` se apne aap banti hai, alag se zaroori nahi.

### Kabhi na badlein (live data ke saath)

| Key | Kyon |
| --- | --- |
| `APP_KEY` | Badalne par sab login sessions toot jaate hain, aur IndexNow ki default key bhi isi se banti hai. |
| `VAPID_*` keys | Badalne par har phone ki push subscription bekaar ho jaati hai; har attendee ko dobara permission deni padegi. Public key hamesha wahi rahe. |
| `APP_TIMEZONE` | UTC hi rahne dein. Ab badla to DB me pehle se saved timestamps ka matlab khisak jaata hai. UTC ka asar sirf itna hai ki raat ka roster refresh 00:00 UTC (subah 5:30 IST) par chalta hai; wo harmless hai. Event ke samay ka hisaab venue ke apne timezone se hota hai, is se nahi. |
| `CACHE_STORE`, `SESSION_DRIVER` | Database hi rehne dein. Cache store badalne par cached state (jaise pending sync) chali jaati hai. Event ke baad load test se compare karke soch sakte hain, abhi nahi. |

### `.env` badalne ke baad (bhoolna mat)

Production me config cached rehta hai (`campbuddy:doctor` `php artisan optimize` chalata hai). Isliye `.env` edit tab tak lagu nahi hota jab tak ye na chalayein:

```bash
php artisan optimize:clear && php artisan optimize
```

`campbuddy:doctor` bhi yahi karta hai, par wo default me `storage/logs` khali kar deta hai; logs bachane hon to `--keep-logs` lagayein.

**Suraksha:** `.env` ka koi bhi hissa (khaas kar `VAPID_PRIVATE_KEY`, `APP_KEY`, DB password, API token) chat, email ya screenshot me na bhejein. Galti se chala jaye to `VAPID_*` ko rotate na karein (upar wajah), bas dobara share na karein; API token aur DB password badal lein.

## Deploy: server par step-by-step

Poora deploy 4 kadam ka hai; sabse zaroori kadam 3 (`campbuddy:doctor`) hai. Ye commands server ke Terminal (cPanel → Terminal ya SSH) me chalti hain.

1. **Code server par pahunchayein.** Agar server par git hai:

    ```bash
    cd /path/to/campbuddy
    git pull origin main
    ```

    Agar git nahi hai (aapka tareeka: FileZilla) to sirf ye upload karein:

    | Bhejein | Kyon |
    | --- | --- |
    | `app/`, `routes/`, `config/`, `resources/views/` | PHP aur pages |
    | `database/` (sirf naye migration ho to) | DB ke badlav |
    | `public/build/` **poora** (server ka purana `public/build` pehle hata dein) | Naya CSS/JS |
    | `public/sw.js`, `public/sw-flags.json`, `public/.htaccess` | Service Worker aur header rules |
    | `vendor/` sirf tab jab `composer.lock` badla ho | PHP libraries |

    **Kabhi upload na karein:** `public/hot`, `.env` (server ki apni `.env` hai), `storage/`, `bootstrap/cache/` (aapke computer ka cache server ko tod sakta hai), `node_modules/`, `.git/`, `tests/`, `tools/`, `.claude/`.

    **FileZilla settings (ek baar):**

    - Menu **Server → Force showing hidden files** ON karein, warna server par `.htaccess` dikhti hi nahi aur pata nahi chalta ki upload hui ya nahi.
    - Menu **Transfer → Transfer type → Binary**. "Auto" kuch files ko text mode me bhejta hai jo unki line endings badal sakta hai.
    - Agar aapka domain ka docroot `public/` nahi balki `public_html` jaisa alag folder hai, to `public/` ke andar ki cheezein (`build/`, `sw.js`, `sw-flags.json`, `.htaccess`, `index.php`) **us docroot folder me** jaati hain. Pata karne ke liye server Terminal me: `find /home/mzpjfuem/domains/campbuddy.club -maxdepth 3 \( -name sw.js -o -name .htaccess -o -name hot \)`. Jis folder me `sw.js` dikhe wahi docroot hai, aur wahin ki `.htaccess` web server padhta hai.

    Upload ke baad turant "Deploy ke baad test" ka headers check chalayein (`/hot` par 404 aur home page me `5173` ki ginti 0 dekhna sabse pehle).

2. **Front-end build.** `public/build` folder git me nahi hota, isliye ye zaroori hai. Do raaste, koi ek:

    - Server par Node ho to: `npm ci && npm run build`
    - Node na ho to apne computer par `npm run build` chalayein aur `public/build` folder poora upload karein (purani files hatakar).

3. **Doctor chalayein** (ek hi command sab karti hai: caches saaf, migrations, config/route/view cache, har live event ka data fetch, aur ant me health report):

    ```bash
    php artisan campbuddy:doctor
    ```

    Options: `--skip-fetch` (data fetch chhod do), `--no-build` (agar build ho chuka hai), `--keep-logs` (logs mat mitao). Ant me report me kuch laal (problem) nahi dikhna chahiye: "cron not running", "failed jobs", "APP_DEBUG on" jaisi lines aayein to unka fix wahin likha hota hai.

4. **Turant Cloudflare purge karein** (agla section, kadam A). Ye chhoot gaya to naya `sw.js` phones tak ghanton late pahunchega.

Sab hone ke baad site khol kar dekh lein ki home, ek event, My Day aur Explore theek khul rahe hain, phir "Deploy ke baad test" section chalayein.

## Cloudflare settings: kya karna hai, kya nahi

Sirf **kadam A** har deploy ke baad zaroori hai; **kadam B** ek baar ka kaam hai jo 10,000 users ke liye sabse bada fayda dega. (Ye Cloudflare ki dashboard ki settings hain, code ki nahi.)

**A. Har deploy ke baad: purane copy hatayein**

1. Cloudflare dashboard → aapka domain → **Caching** → **Configuration** → **Purge Cache**.
2. **Custom Purge** → *URL* chunein aur ye 3 daalein (ya seedha **Purge Everything**, site chhoti hai isliye theek hai):

    ```text
    https://campbuddy.club/sw.js
    https://campbuddy.club/sw-flags.json
    https://campbuddy.club/build/manifest.json
    ```

    Kyun: abhi ye teeno Cloudflare ke paas cached hain (HIT). Purge na karo to naya Service Worker aur naya build ka manifest ghanton tak purana milta rahega.

**B. Ek baar: API lists ko edge par cache karwayein**

Naye headers (`public, s-maxage=30`) tabhi kaam aayenge jab Cloudflare in 3 addresses ko cache karne ki ijazat de. Isse roster/discovery/data-version ki hazaaron requests host tak pahunchti hi nahi.

1. **Caching** → **Cache Rules** → **Create rule**. Pehle dekh lein ki aapne jo rule pehle banaya tha wo kya karta hai. Agar wo poori site ke liye *Eligible for cache* + *Use cache-control header if present* hai, to naye rule ki zaroorat nahi, deploy ke baad API apne aap cache hone lagegi.
2. Naya rule banana ho to naam: `CampBuddy public lists`. **Edit expression** me ye paste karein:

    ```text
    (http.request.uri.path wildcard "/api/v1/events/*/roster") or (http.request.uri.path wildcard "/api/v1/events/*/discovery") or (http.request.uri.path wildcard "/api/v1/events/*/data-version")
    ```

3. **Then**: *Cache eligibility* = **Eligible for cache**; *Edge TTL* = **Use cache-control header if present, bypass cache if not**; *Browser TTL* = **Respect origin TTL**. Save/Deploy.

**C. Chhoti settings**

- **Caching → Configuration → Browser Cache TTL** = *Respect Existing Headers* (abhi shayad 7 din hai; isse logo badalne par 7 din tak purana dikhta hai).
- **Development Mode** event ke dinon me **OFF** rakhein (ye waise bhi 3 ghante baad apne aap band ho jata hai; on rahega to Cloudflare cache bilkul nahi karega).
- **Speed → Optimization**: *Rocket Loader* **OFF** rakhein (ye module scripts ko bigaad sakta hai).
- Purge button automatic karna ho: dashboard → My Profile → API Tokens → Create Token → Custom → *Zone → Cache Purge → Purge*, zone = `campbuddy.club`. Token aur Zone ID (domain ke Overview page par) `.env` me `CLOUDFLARE_API_TOKEN` / `CLOUDFLARE_ZONE_ID` me daalein.

**D. Ye kabhi na karein**

- HTML pages, `/admin`, `/api/v1/cache-version`, `/api/.../waves`, `/api/.../messages` par *Cache Everything* ya *Ignore cache-control (override TTL)* na lagayein. Isse ek user ka data doosre ko dikh sakta hai aur purani info atak jayegi. Ye sab origin ke `no-cache, private` ki wajah se abhi bilkul theek BYPASS ho rahe hain.

## Deploy ke baad test: kaise karein, kya dekhna hai

Pehle headers (2 minute), phir load test (5 minute), phir phone par test (10 minute). Windows PowerShell me `curl` ki jagah `curl.exe` likhein.

**1. Headers check**

```bash
SITE=https://campbuddy.club
EVENT=wordcamp-rajasthan-2026
curl -sI $SITE/hot | head -1
curl -s  $SITE/ | grep -c "5173"
curl -sI $SITE/sw.js | grep -i "cache-control\|cf-cache-status"
curl -sI $SITE/sw-flags.json | grep -i "HTTP/\|cache-control"
curl -s  $SITE/sw-flags.json
curl -sI $SITE/build/manifest.json | grep -i "cache-control\|cf-cache-status"
ASSET=$(curl -s $SITE/build/manifest.json | grep -o 'assets/app-[A-Za-z0-9_-]*\.js' | head -1)
curl -sI $SITE/build/$ASSET | grep -i "cache-control"
for i in 1 2; do curl -sI $SITE/api/v1/events/$EVENT/data-version | grep -i "cache-control\|etag\|cf-cache-status"; done
for i in 1 2; do curl -sI $SITE/api/v1/events/$EVENT/roster | grep -i "etag\|cf-cache-status"; done
```

| Address | Sahi nateeja |
| --- | --- |
| `/hot` | **404**. Agar 200 aaye to site ka UI bigda hua hai: file delete karein (upar checklist dekhein). |
| Home page me `5173` | `0`. Koi bhi ginti aaye to HTML abhi bhi dev server se assets maang raha hai. |
| `/sw.js` | `Cache-Control: no-cache`; `cf-cache-status` **HIT nahi** (BYPASS ya DYNAMIC) |
| `/sw-flags.json` | 200, `no-store`, body me `"swrPages": false` |
| `/build/manifest.json` | `no-cache`, HIT nahi |
| hashed `app-....js` | `public, max-age=31536000, immutable` |
| `data-version` | `ETag: W/"..."`, `public, max-age=0, s-maxage=20, ...`; kadam B ke baad doosri baar **HIT** |
| `roster` | pehli baar MISS, doosri baar **HIT** (kadam B ke baad) |
| Home aur event pages | BYPASS/DYNAMIC hi rehne chahiye (ye sahi hai) |

Agar `sw.js` par `no-cache` nahi dikha to `.htaccess` upload nahi hua ya Cloudflare purana copy de raha hai: kadam A (purge) dobara karein.

**2. Load test** (apne computer par, Node chahiye). Pehle chhota, phir 150 users:

```bash
node tools/loadtest.mjs --url https://campbuddy.club --event wordcamp-rajasthan-2026 --users 20 --duration 40 --i-own-this-site
node tools/loadtest.mjs --url https://campbuddy.club --event wordcamp-rajasthan-2026 --users 150 --duration 60 --ramp 15 --think 10 --poll 30 --max-rps 40 --max-inflight 30 --i-own-this-site
```

| 150 users, ~22 req/s | Pehle (26 Sep) | Ab kya chahiye |
| --- | --- | --- |
| p95 | 2.1–2.3 s | 1 s se kam |
| p99 | ~10 s | 3 s se kam |
| Cloudflare HIT | 0 | roster/discovery/data-version ke liye dikhna chahiye (kul ka ~30–40%) |
| Errors | 0 | 0 |

**Asli nateeja (26 Sep 2026, deploy ke baad, wahi settings):** 150 users, 23.2 req/s → p95 **245 ms** (pehle 2.1–2.3 s), p99 746 ms tak (pehle ~10 s), errors 0, Cloudflare HIT **569 / 1746 (33%)**. 20-user test bhi PASS (p95 264 ms). Host ki asli seema (ceiling) abhi nahi mili: is test me sirf ~15 req/s origin tak pahunche (baaki HIT); ceiling dhoondhne ke liye 150 se upar ka test chahiye.

HIT bilkul 0 aaye to kadam B (Cache Rule) lagana baaki hai. Isse zyada users (300+) sirf raat ko aur dhire-dhire badhakar chalayein, aur host ke cPanel → *Resource Usage* graph (CPU / Entry Processes) saath me dekhte rahein.

**3. Phone par test** (Android Chrome par sabse aasan)

- [ ] Event ka koi ek page kholein, ek minute rukein (baaki pages background me save hote hain), phir **airplane mode**. Home, My Day, Quest, Contribute, Explore, Camp Card, Guide sab khulne chahiye, images ke saath.
- [ ] Explore → People net ke saath kholein (list aur photos aayein), phir airplane mode me dobara kholein: list dikhni chahiye, upar "Showing the attendee list saved on your phone" ka note, aur wahi photos jo pehle dikhi thi.
- [ ] App **install** karein (Android: menu → Install app; iPhone: Share → Add to Home Screen) aur upar wale 2 test install kiye hue app me dohrayein.
- [ ] My Day me ek aisa session star karein jo 15 minute me shuru ho, "Yes" karein; start se 5–10 minute pehle notification aana chahiye. Airplane mode me star karke baad me net chalu karne par bhi reminder set ho jana chahiye.
- [ ] Admin panel → Dashboard → **Purge cache & refresh data** dabayein, phir phone par app kholein: naya data dikhe, aur airplane mode me pages phir bhi khulein (kuch delete nahi hona chahiye).
- [ ] Computer par Chrome DevTools (F12) → Application → Service Workers me `sw.js` *activated*; Cache Storage me `campbuddy-v3` (pages) aur `campbuddy-avatars` (photos).

**4. Event ke dinon me nazar**

- `storage/logs/laravel.log` me naye errors, aur `https://campbuddy.club/api/v1/health` (OK aana chahiye).
- Cloudflare → Analytics → *Caching* me cache hit ratio; cPanel → Resource Usage me CPU.
- Agar koi active event achanak 404 de: Admin → Events me uska status dekhein (`archived` nahi hona chahiye jab tak uske aakhri din ke 3 din baad na ho).

## Kuch bigde to wapas kaise jayein

Sabse pehle dekhein ki kaunsi cheez bigdi; har cheez ka apna chhota upaay hai, poora deploy palatna aakhri raasta hai.

| Problem | Upaay | Kitni der me |
| --- | --- | --- |
| Pages purane dikh rahe hain / offline copy ajeeb | `public/sw-flags.json` me `"swrPages": false` (ya file delete). Ye abhi false hi hai. | 10 minute tak (phone ka Service Worker file dobara padhta hai) |
| Reminders galat ya late | Sirf ek file purane version par: `git checkout 346ac86 -- app/Jobs/SendSessionRemindersJob.php`, phir `php artisan campbuddy:doctor --skip-fetch --no-build` | turant |
| API list galat dikh rahi | Cloudflare → Cache Rules me `CampBuddy public lists` rule **Disable** karein, phir Purge Everything | 1 minute |
| Poora naya code hatana hai | `git revert 856e71a 20f9e2b 5e0537a 346ac86`, build dobara, `campbuddy:doctor`, phir Cloudflare Purge Everything | 10 minute |
| Service Worker hi bigad gaya (sabse rare) | Neeche wala "kill" worker `public/sw.js` ki jagah rakhein, Cloudflare purge karein | Jaise-jaise phones app kholte hain (kuch ghante) |

Kill worker (ye khud ko hata deta hai aur khule pages ko reload karwa deta hai; saved copies delete nahi karta):

```javascript
self.addEventListener('install', () => self.skipWaiting());
self.addEventListener('activate', (event) => {
  event.waitUntil((async () => {
    await self.registration.unregister();
    for (const client of await self.clients.matchAll({ type: 'window' })) client.navigate(client.url);
  })());
});
```

Rollback ke baad bhi `git` me purane commits maujood rehte hain, isliye kuch kho nahi jata. Attendee ka apna data (saved sessions, notes, quest) phone ke IndexedDB me hai aur inme se koi bhi upaay use nahi chhoota.

## Jo abhi nahi hua aur kyon

Poocha gaya "jo possible ho wo kar do": Gravatar photos ho gayi; iPhone background sync **possible nahi** hai; baaki cheezein jaanbujhkar band ya baad ke liye rakhi hain.

| Cheez | Haal | Kyon / aage kya |
| --- | --- | --- |
| Roster ki Gravatar photos offline | **Ho gaya** | Wahi photos jo phone ne dekhi ya idle me pehle se save ki (max 3000). Baaki ko placeholder avatar dikhta hai. |
| iPhone par background sync | **Possible nahi** | iOS/Safari me Background Sync hai hi nahi, aur Web Push me har baar notification dikhana zaroori hai (chupke se refresh nahi ho sakta). Ab: app kholte hi ya net aate hi sab sync hota hai. |
| Android par background sync | Nahi banaya | Iske liye device-id aur API ka kaam Service Worker me le jana padega, bada badlav. Agar Android users late reminders ki shikayat karein to baad me. |
| Page kholne par "pehle saved copy, peeche se update" (SWR) | Bana hai, **band** hai | `public/sw-flags.json`. Pehle event ke baad, dhire-dhire (file `true`, load test, phir dekhein). Ye sabse zyada host ka load ghatata hai, isliye baad me zaroor. |
| 10,000 users ka daawa | **Sabit nahi** | Abhi host ~20 req/s par tootne lagta hai. Naye headers + Cloudflare Rule + kam polling se load ghatna chahiye, par naapa nahi. Agla kadam: deploy → load test → zaroorat ho to behtar server. |
| Offline pages ka automatic safai | Jaanbujhkar nahi | "Event ke dauran kuch delete nahi" niyam. Ek event ke ~1 MB se kam pages hain, isliye phone par bojh nahi. |
| Roster se hataye gaye log | Chhota apwaad | Unka naam un phones ki saved copy me tab tak rehta hai jab tak wo phone online na aaye. |
| `AnalyticsRegistryTest` (2 tests) | Sirf aapke Windows par fail | OpenSSL config ki wajah se; Linux par pass. |
| iPhone par push | Sirf Home Screen install ke baad | Apple ka niyam. App me iska ek baar wala dialog pehle se hai. |

---

Yeh file Claude Docs wale "Deployment aur Launch Checklist" document ka copy thi; "Server ka `.env`" section sirf isi file me hai. Technical detail ke liye `.claude/skills/campbuddy-docs/spec/` dekhein (khaas kar `12-deployment.md`, `23-data-retention.md`).
