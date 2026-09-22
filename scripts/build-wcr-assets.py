"""Generate optimized, web-sized derivatives of the source WCR brand assets,
and refresh app.js's live-data fallbacks from the WPSimplified API.

Source files in wcr/ are large originals (multi-MB, 4000px+) supplied for
print/brand use. This script produces small, right-sized PNGs in wcr/web/
for actual use in the PWA (favicons, header logo, QR branding, etc), and
then (network permitting) rewrites the auto-generated blocks in app.js —
EXPLORE_VIDEOS_FALLBACK, SPONSORS_FALLBACK, and the live-derivable EVENT
fields — from the same public API app.js fetches live at runtime. This
keeps the "no data yet loaded / API unreachable" fallback state reasonably
fresh without ever needing to hand-retype it.

Run: python scripts/build-wcr-assets.py
"""
from PIL import Image
import json
import os
import re
import urllib.request
import urllib.error

SRC = "wcr"
OUT = os.path.join("wcr", "web")
APP_JS = "app.js"
os.makedirs(OUT, exist_ok=True)

CREAM = (255, 250, 244, 255)

API_BASE = "https://wpsimplified.in/wp-json/wpsimplified/v1"
MEDIA_ENDPOINT = f"{API_BASE}/media"
EVENTS_ENDPOINT = f"{API_BASE}/events?slug=wordcamp-rajasthan-2026"

SPONSOR_TIER_MAP = {
    "Nahargarh Fort": ("Platinum", "platinum"),
    "Hawa Mahal": ("Silver", "silver"),
    "Jal Mahal": ("Bronze", "bronze"),
}
TIER_ORDER = ["Platinum", "Silver", "Bronze"]

# Fields in EVENT that have a live equivalent and get refreshed in place.
# Everything else in EVENT (id, shortName, timezone, scheduleUrl,
# contributorUrl, sponsorsUrl, codeOfConductUrl, facts) has no API
# equivalent and is left untouched.
EVENT_SCALAR_FIELDS = [
    "name", "tagline", "starts", "conference", "venue", "address",
    "hashtag", "officialUrl", "ticketUrl", "directionsUrl", "contactUrl",
    "ticketPrice",
]


# --- image pipeline (unchanged) -----------------------------------------

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


def build_images():
    icon = load("Icon-Colored.png")
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


# --- live data (mirrors app.js's own fetch/normalize logic) -------------

def fetch_json(url):
    req = urllib.request.Request(url, headers={"User-Agent": "campbuddy-build-script"})
    try:
        with urllib.request.urlopen(req, timeout=10) as res:
            return json.loads(res.read().decode("utf-8"))
    except (urllib.error.URLError, urllib.error.HTTPError, TimeoutError, ValueError) as e:
        print(f"  (skipping — could not fetch {url}: {e})")
        return None


def youtube_id_from_link(link):
    m = re.search(r"(?:shorts/|[?&]v=|youtu\.be/)([a-zA-Z0-9_-]{6,})", link or "")
    return m.group(1) if m else None


def js_str(s):
    """A JS/JSON string literal — json.dumps escaping is a valid subset of
    JS string syntax (handles quotes, backslashes, emoji, unicode)."""
    return json.dumps(s, ensure_ascii=False)


def build_videos_js(media_json):
    items = media_json.get("items") if isinstance(media_json, dict) else None
    if not isinstance(items, list) or not items:
        return None
    out = []
    for it in items[:10]:
        vid = youtube_id_from_link(it.get("youtube_link"))
        title = it.get("title")
        if not vid or not title:
            continue
        vtype = "short" if it.get("type") == "short" else "video"
        out.append(f'  {{id:{js_str(vid)},title:{js_str(title)},type:{js_str(vtype)}}}')
    return out or None


def build_sponsors_js(sponsors):
    if not sponsors:
        return None
    groups = {}
    for s in sponsors:
        name = s.get("name")
        if not name:
            continue
        label, cls = SPONSOR_TIER_MAP.get(s.get("tier"), (s.get("tier") or "Sponsors", "bronze"))
        groups.setdefault(label, {"cls": cls, "items": []})
        groups[label]["items"].append((name, s.get("url") or "", s.get("logo") or None))
    if not groups:
        return None
    order = [t for t in TIER_ORDER if t in groups] + [t for t in groups if t not in TIER_ORDER]
    blocks = []
    for tier in order:
        g = groups[tier]
        items_js = ",\n".join(
            f'    {{name:{js_str(n)}, url:{js_str(u)}, logo:{js_str(l) if l else "null"}}}'
            for n, u, l in g["items"]
        )
        blocks.append(
            f'  {{tier:{js_str(tier)}, cls:{js_str(g["cls"])}, items:[\n{items_js}\n  ]}}'
        )
    return blocks


def build_event_patch(ev):
    patch = {}
    if ev.get("title"):
        patch["name"] = ev["title"]
    if ev.get("event_tagline"):
        patch["tagline"] = ev["event_tagline"]
    if ev.get("event_start_date"):
        patch["starts"] = f'{ev["event_start_date"]}T09:00:00+05:30'
    if ev.get("event_end_date"):
        patch["conference"] = f'{ev["event_end_date"]}T09:00:00+05:30'
    if ev.get("event_venue_name"):
        patch["venue"] = ev["event_venue_name"]
    if ev.get("event_venue_address"):
        patch["address"] = ev["event_venue_address"]
    if ev.get("event_hashtag"):
        patch["hashtag"] = ev["event_hashtag"]
    if ev.get("event_home_url"):
        patch["officialUrl"] = ev["event_home_url"]
    if ev.get("event_tickets_url"):
        patch["ticketUrl"] = ev["event_tickets_url"]
    if ev.get("event_venue_directions_url"):
        patch["directionsUrl"] = ev["event_venue_directions_url"]
    if ev.get("event_email"):
        patch["contactUrl"] = f'mailto:{ev["event_email"]}'
    ticket_types = ev.get("event_ticket_types") or []
    general = next(
        (t for t in ticket_types if (t.get("name") or "").strip().lower() == "general ticket" and t.get("status") == "available"),
        None,
    )
    if general and general.get("price"):
        patch["ticketPrice"] = f'{general["price"]} for both days'
    socials = ev.get("event_social") or []
    socials = [(s["label"], s["url"]) for s in socials if s.get("label") and s.get("url")]
    return patch, (socials or None)


# --- app.js splicing ------------------------------------------------------

def replace_between_markers(text, marker_name, new_body):
    # The START/END marker lines themselves must be exact, single lines
    # (nothing else on them) — anything else nearby (explanatory comments,
    # etc.) belongs OUTSIDE the markers, or a lazy .*? here would silently
    # swallow it as "old content to replace" and discard it.
    start_marker = f"// AUTO-GENERATED:{marker_name} START\n"
    end_marker = f"\n// AUTO-GENERATED:{marker_name} END"
    start_i = text.find(start_marker)
    end_i = text.find(end_marker)
    if start_i == -1 or end_i == -1 or end_i < start_i:
        print(f"  WARN: markers for {marker_name} not found in app.js — skipping")
        return text, False
    body_start = start_i + len(start_marker)
    new_text = text[:body_start] + new_body + text[end_i:]
    return new_text, True


def replace_videos_block(text, video_lines):
    body = "const EXPLORE_VIDEOS_FALLBACK = [\n" + ",\n".join(video_lines) + "\n];"
    return replace_between_markers(text, "EXPLORE_VIDEOS_FALLBACK", body)


def replace_sponsors_block(text, sponsor_blocks):
    body = "const SPONSORS_FALLBACK = [\n" + ",\n".join(sponsor_blocks) + "\n];"
    return replace_between_markers(text, "SPONSORS_FALLBACK", body)


def patch_event_block(text, patch, socials):
    m = re.search(r"const EVENT = \{.*?\n\};\n", text, re.DOTALL)
    if not m:
        print("  WARN: EVENT block not found in app.js — skipping")
        return text, False
    block = m.group(0)
    new_block = block
    changed = False
    for key, value in patch.items():
        if key not in EVENT_SCALAR_FIELDS:
            continue
        field_pattern = re.compile(r"(\n  " + re.escape(key) + r": )\"(?:[^\"\\]|\\.)*\"(,?)")
        new_block, n = field_pattern.subn(
            lambda m, v=value: m.group(1) + js_str(v) + m.group(2), new_block, count=1
        )
        changed = changed or n > 0
    if socials:
        socials_js = ",\n".join(f"    [{js_str(l)},{js_str(u)}]" for l, u in socials)
        new_block, n = re.subn(
            r"socials: \[.*?\n  \],",
            "socials: [\n" + socials_js + "\n  ],",
            new_block,
            count=1,
            flags=re.DOTALL,
        )
        changed = changed or n > 0
    if not changed:
        return text, False
    return text[: m.start()] + new_block + text[m.end():], True


def build_live_data():
    print("\nFetching live data from WPSimplified API...")
    text = open(APP_JS, encoding="utf-8").read()
    any_change = False

    media_json = fetch_json(MEDIA_ENDPOINT)
    if media_json:
        video_lines = build_videos_js(media_json)
        if video_lines:
            text, ok = replace_videos_block(text, video_lines)
            if ok:
                print(f"  EXPLORE_VIDEOS_FALLBACK <- {len(video_lines)} videos")
                any_change = True

    events_json = fetch_json(EVENTS_ENDPOINT)
    ev = None
    if events_json and isinstance(events_json.get("events"), list) and events_json["events"]:
        ev = events_json["events"][0]

    if ev:
        sponsors = ev.get("event_sponsors") or []
        sponsor_blocks = build_sponsors_js(sponsors)
        if sponsor_blocks:
            text, ok = replace_sponsors_block(text, sponsor_blocks)
            if ok:
                print(f"  SPONSORS_FALLBACK <- {len(sponsors)} sponsors")
                any_change = True

        patch, socials = build_event_patch(ev)
        if patch or socials:
            text, ok = patch_event_block(text, patch, socials)
            if ok:
                print(f"  EVENT <- {len(patch)} field(s){' + socials' if socials else ''}")
                any_change = True

    if any_change:
        open(APP_JS, "w", encoding="utf-8", newline="\n").write(text)
        print(f"Updated {APP_JS}")
    else:
        print(f"No live data available — {APP_JS} left unchanged")


if __name__ == "__main__":
    build_images()
    build_live_data()
    print("\ndone")
