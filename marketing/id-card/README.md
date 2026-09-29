# CampBuddy promotion ID card

Team CampBuddy ke 8 logon ke liye print hone wale ID cards. Front par naam hai, back par campbuddy.club ka QR code hai.

## Printer ko ye bhejein

**`print/CampBuddy-ID-cards.pdf`**: 16 pages. Har person ka front, phir back (page 1 = Prathamesh ka front, page 2 = back, page 3 = Abhishek ka front…).

Agar printer crop marks maange to **`print/CampBuddy-ID-cards-cropmarks.pdf`** bhejein (same cards, har page par cutting lines ke saath).

| | |
|---|---|
| Card size (trim) | **86 × 54 mm**, landscape |
| Bleed | har taraf **3 mm**, isliye file ka page **92 × 60 mm** hai |
| Safe area | trim se 3 mm andar tak saara text hai |
| Printing | dono taraf (front + back), colour, matt ya gloss lamination |
| PNG | 300 dpi (1084 × 706 px), bleed ke saath |

Printer ko ye bhi bata dein: *"Colours RGB mein hain. Aapka software CMYK mein badlega; maroon aur orange ka match dekh lena."* Chahein to pehle ek card ka sample print karwa lein.

## Files: `print/`

| File | Kya hai |
|---|---|
| `CampBuddy-ID-cards.pdf` | Saare 8 cards, front aur back (bleed ke saath) |
| `CampBuddy-ID-cards-cropmarks.pdf` | Wahi cards, crop marks ke saath |
| `pdf/<naam>.pdf` | Ek person ka card (front + back) |
| `pdf/blank-front.pdf`, `png/blank-front.png` | Bina naam ka front, naya naam khud likhne ke liye |
| `png/<naam>-front.png`, `png/back.png` | PNG images, 300 dpi |

**QR code:** `https://campbuddy.club/?utm_source=id-card&utm_medium=print`. Google Analytics mein source "id-card" dikhega. Check kiya: image 3 guna chhoti karne par bhi scan hota hai.

## Naam jodna ya badalna

`names.txt` mein har line par ek naam likhein (role bhi dena ho to `Naam | Role`), phir chalayein:

```bash
node marketing/id-card/render.mjs
```

Saari files dobara ban jaati hain. Lamba naam apne aap chhota hokar do line mein fit ho jaata hai. Ek hi person ke liye: `node marketing/id-card/render.mjs "Naam"`. Chrome aur internet (Inter font ke liye) chahiye.

Canva/Photoshop mein banana ho to `png/blank-front.png` lein. Upar chhota "HELLO, I'M" (Inter ExtraBold, 5.4 pt, #d77b06, letter-spacing wide), phir naam **Inter ExtraBold (800), 17 pt**, colour **#721313**, logo ke neeche baayein taraf se shuru karein, aur uske neeche 9 mm ki orange line (#d77b06).

## Colours

Wine `#721313` · Ochre `#d77b06` · Cream `#fbecd8` · Paper `#fffaf4`

Design ka source `id-card.html` aur `render.mjs` hai.
