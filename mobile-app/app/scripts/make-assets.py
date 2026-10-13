"""Builds every icon and splash image of the app from the website's own brand
files (public/media/favicon.png, public/media/logo.png), so the app, the PWA
and the site never drift apart.

    python mobile-app/app/scripts/make-assets.py

Needs Pillow. Run it again whenever the logo changes, then commit the output.
Typography: the splash and the offline page use the real CampBuddy wordmark
(its own lettering, not a system font), and www/offline.html bundles Inter,
the site's text font.
"""

import shutil
from pathlib import Path

from PIL import Image, ImageDraw

HERE = Path(__file__).resolve().parent
APP = HERE.parent
REPO = APP.parent.parent
MEDIA = REPO / "public" / "media"
RES = APP / "android" / "app" / "src" / "main" / "res"
IOS = APP / "ios" / "App" / "App" / "Assets.xcassets"

MAROON = (96, 18, 28, 255)
CREAM = (252, 241, 219, 255)

icon = Image.open(MEDIA / "favicon.png").convert("RGBA")
icon = icon.crop(icon.getbbox())  # the rounded square, edge to edge
logo = Image.open(MEDIA / "logo.png").convert("RGBA")
logo = logo.crop(logo.getbbox())  # wordmark + tagline


def fit(image, size):
    return image.resize(size, Image.LANCZOS)


def save(image, path):
    path.parent.mkdir(parents=True, exist_ok=True)
    image.save(path, optimize=True)


def square_on(background, art, size, art_size):
    canvas = Image.new("RGBA", (size, size), background)
    art = fit(art, (art_size, art_size))
    canvas.alpha_composite(art, ((size - art_size) // 2, (size - art_size) // 2))
    return canvas


def circle(image):
    mask = Image.new("L", image.size, 0)
    ImageDraw.Draw(mask).ellipse((0, 0, image.size[0] - 1, image.size[1] - 1), fill=255)
    out = image.copy()
    out.putalpha(mask)
    return out


# ---- Android launcher icons ---------------------------------------------------
# Legacy (before Android 8): the rounded square, and a round version of it.
for density, px in {"mdpi": 48, "hdpi": 72, "xhdpi": 96, "xxhdpi": 144, "xxxhdpi": 192}.items():
    save(fit(icon, (px, px)), RES / f"mipmap-{density}" / "ic_launcher.png")
    save(circle(square_on(MAROON, icon, px, px)), RES / f"mipmap-{density}" / "ic_launcher_round.png")

# Adaptive (Android 8+): 108 dp canvas, only the middle 66 dp is guaranteed visible,
# so the art sits at ~71 dp and the maroon background layer carries the rest.
for density, px in {"mdpi": 108, "hdpi": 162, "xhdpi": 216, "xxhdpi": 324, "xxxhdpi": 432}.items():
    canvas = Image.new("RGBA", (px, px), (0, 0, 0, 0))
    art = fit(icon, (round(px * 71 / 108),) * 2)
    canvas.alpha_composite(art, ((px - art.size[0]) // 2, (px - art.size[1]) // 2))
    save(canvas, RES / f"mipmap-{density}" / "ic_launcher_foreground.png")

# ---- Android splash (the website's cream, the real wordmark) -------------------
def splash(width, height, logo_share):
    canvas = Image.new("RGBA", (width, height), CREAM)
    target_w = round(min(width, height) * logo_share)
    art = fit(logo, (target_w, round(target_w * logo.size[1] / logo.size[0])))
    canvas.alpha_composite(art, ((width - art.size[0]) // 2, (height - art.size[1]) // 2))
    return canvas.convert("RGB")


ports = {"mdpi": (320, 480), "hdpi": (480, 800), "xhdpi": (720, 1280), "xxhdpi": (960, 1600), "xxxhdpi": (1280, 1920)}
for density, (w, h) in ports.items():
    save(splash(w, h, 0.62), RES / f"drawable-port-{density}" / "splash.png")
    save(splash(h, w, 0.62), RES / f"drawable-land-{density}" / "splash.png")
save(splash(480, 320, 0.62), RES / "drawable" / "splash.png")

# ---- iOS ----------------------------------------------------------------------
# App icon: 1024 square, no transparency, no rounded corners (iOS rounds it).
save(square_on(MAROON, icon, 1024, 1024).convert("RGB"), IOS / "AppIcon.appiconset" / "AppIcon-512@2x.png")
splash_ios = splash(2732, 2732, 0.46)
for name in ("splash-2732x2732.png", "splash-2732x2732-1.png", "splash-2732x2732-2.png"):
    save(splash_ios, IOS / "Splash.imageset" / name)

# ---- the offline page ---------------------------------------------------------
www = APP / "www"
save(fit(logo, (640, round(640 * logo.size[1] / logo.size[0]))), www / "wordmark.png")
save(fit(icon, (192, 192)), www / "icon.png")
fonts = APP / "node_modules" / "@fontsource" / "inter" / "files"
(www / "fonts").mkdir(exist_ok=True)
for weight in (400, 600, 700):
    shutil.copy(fonts / f"inter-latin-{weight}-normal.woff2", www / "fonts" / f"inter-{weight}.woff2")

# ---- a store listing icon and a feature image to start from -------------------
store = APP / "store"
store.mkdir(exist_ok=True)
save(square_on(MAROON, icon, 512, 512).convert("RGB"), store / "play-icon-512.png")
feature = Image.new("RGB", (1024, 500), CREAM[:3])
art = fit(logo, (760, round(760 * logo.size[1] / logo.size[0])))
feature.paste(art, ((1024 - art.size[0]) // 2, (500 - art.size[1]) // 2), art)
save(feature, store / "play-feature-1024x500.png")

print("assets written")
