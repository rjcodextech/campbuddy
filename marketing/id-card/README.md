# CampBuddy promotion ID card

Promotion ke liye print hone wala ID card. Front par person ka naam hai, back par campbuddy.club ka QR code hai.

## Size (printer ko ye batayein)

| | |
|---|---|
| Card (trim) | **54 × 85.6 mm**, CR80, portrait (normal PVC ID card) |
| Bleed | har taraf **3 mm**, isliye file **60 × 91.6 mm** hai |
| Safe area | trim se 3 mm andar tak saara text hai |
| Resolution | PNG **300 dpi** (706 × 1081 px). PDF mein text vector hai. |
| Lanyard slot | upar beech mein 3–8 mm ki jagah khali rakhi hai, wahan slot punch ho sakta hai |

## Files: `print/`

| File | Kya hai |
|---|---|
| `sunil-kumar-sharma.pdf` | Page 1 front, page 2 back. **Printer ko ye bhejein.** |
| `sunil-kumar-sharma-front.png` | Front (naam ke saath) |
| `back.png` | Back, sabke liye ek hi |
| `blank-front.png` / `blank.pdf` | Front bina naam ke. Canva/Photoshop mein naam khud likhna ho to ye lein. |

**QR code:** `https://campbuddy.club/?utm_source=id-card&utm_medium=print`. Google Analytics mein source "id-card" ke naam se dikhega, to pata chalega kitne log card se aaye. Check kiya: file ko 35% chhota karke bhi scan hota hai.

## Baaki logon ke card

**Tareeka 1: command se (sabse aasaan, design bilkul same rahega)**

```bash
node marketing/id-card/render.mjs "Rahul Verma" "Frontend Developer"
node marketing/id-card/render.mjs "Neha Gupta"          # role ke bina
node marketing/id-card/render.mjs --blank               # bina naam ka front
```

Har naam ke liye `print/<naam>.pdf` aur `print/<naam>-front.png` ban jaata hai. Lamba naam apne aap chhota hokar do line mein fit ho jaata hai. Chrome aur internet (Inter font ke liye) chahiye.

**Tareeka 2: Canva / Photoshop**

`blank-front.png` ko background banayein. Naam aur role beech ki khali jagah mein likhein, logo ke neeche aur "Ask me about CampBuddy" ke upar:
- Naam: **Inter ExtraBold (800), 15 pt**, colour **#721313**, center mein. Lamba naam ho to 12–13 pt.
- Role: **Inter Bold (700), 7.4 pt**, colour **#d77b06**.

## Colours

Wine `#721313` · Ochre `#d77b06` · Cream `#fbecd8` · Paper `#fffaf4`

Design ka source `id-card.html` hai. Browser mein `id-card.html?guides` kholne par trim line (neeli) aur safe area (gulaabi) dikhte hain.
