"""Generate optimized, web-sized derivatives of the source WCR brand assets.

Source files in wcr/ are large originals (multi-MB, 4000px+) supplied for
print/brand use. This script produces small, right-sized PNGs in wcr/web/
for actual use in the PWA (favicons, header logo, QR branding, etc).

Run: python scripts/build-wcr-assets.py
"""
from PIL import Image
import os

SRC = "wcr"
OUT = os.path.join("wcr", "web")
os.makedirs(OUT, exist_ok=True)

CREAM = (255, 250, 244, 255)


def load(name):
    return Image.open(os.path.join(SRC, name)).convert("RGBA")


def save(im, name, optimize=True):
    path = os.path.join(OUT, name)
    im.save(path, optimize=optimize)
    print(name, im.size, f"{os.path.getsize(path)/1024:.1f}K")


def resized(im, target_w=None, target_h=None):
    w, h = im.size
    if target_w and not target_h:
        target_h = round(h * target_w / w)
    elif target_h and not target_w:
        target_w = round(w * target_h / h)
    return im.resize((target_w, target_h), Image.LANCZOS)


def key_out_white(im, threshold=246):
    """Turn near-white pixels transparent (source has a baked-in white bg)."""
    im = im.convert("RGBA")
    data = im.getdata()
    out = [
        (r, g, b, 0) if r >= threshold and g >= threshold and b >= threshold else (r, g, b, a)
        for (r, g, b, a) in data
    ]
    im.putdata(out)
    return im


icon = load("Icon-Colored.png")
icon_bw = load("icon-bw.png")
logo_h = key_out_white(load("logo-horizontal.png"))
logo_v = load("logo-vertical.png")
wappu = load("wappu.png")
turban = load("indian man with turban rajasthani.png")

# Favicon / app icons (purpose: any)
save(resized(icon, target_w=512), "icon-512.png")
save(resized(icon, target_w=180), "icon-180.png")
save(resized(icon, target_w=32), "icon-32.png")

# Maskable icon: icon content scaled into the safe zone on a solid cream square
maskable = Image.new("RGBA", (512, 512), CREAM)
safe = resized(icon, target_w=360)
maskable.alpha_composite(safe, ((512 - safe.width) // 2, (512 - safe.height) // 2))
save(maskable.convert("RGB"), "icon-maskable-512.png")

# Header wordmark logo (retina-safe at ~34px CSS height)
save(resized(logo_h, target_h=200), "logo-header.png")

# Social share image: logo centered on brand cream canvas, 1200x630
og = Image.new("RGBA", (1200, 630), CREAM)
og_logo = resized(logo_h, target_w=880)
og.alpha_composite(og_logo, ((1200 - og_logo.width) // 2, (630 - og_logo.height) // 2))
save(og.convert("RGB"), "og-image.png")

# QR center branding mark (small, crisp, used at ~15-18% of QR canvas)
save(resized(icon, target_w=256), "qr-mark.png")

# Onboarding mascot art
save(resized(wappu, target_w=700), "mascot-wappu.png")

# Decorative turban illustration (used sparingly as accent art)
save(resized(turban, target_w=560), "mascot-turban.png")

# Vertical logo, small, for potential future use (e.g. splash)
save(resized(logo_v, target_w=420), "logo-vertical.png")

print("done")
