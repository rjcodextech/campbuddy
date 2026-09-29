# 3.14 Free Steals (Explore → Free Steals, 29 Sep 2026)

[← Index](00-index.md) · Previous: [3.13 Data controls](13-data-controls.md) · Back to: [Spec index](../../SKILL.md)

**Deals help you save money on paid things. Free Steals help you discover great things that are already free.**

A small, hand-picked shelf of WordPress plugins and tools that cost nothing: developer tools, Gutenberg utilities, WooCommerce, AI, performance, security. It is not a plugin directory. The aim is that an attendee opens it and thinks "I didn't know this existed". Makers are mixed on purpose: a tool from Automattic sits beside one from Lubus, Multidots, rtCamp, Gaurav Tiwari, Nitin Prakash, Abhishek Deshpande or Hardeep Asrani, because small community makers don't have marketing teams and CampBuddy can help people find them.

## How it works

- **One list for every event.** Table `free_steals` (no `event_id`): `name`, `description` (≤300), `maker`, `category`, `url`, `cta_label` (empty = "Get it free"), `is_featured`, `is_active`, `sort_order`. Model `App\Models\FreeSteal`.
- **Curated, not a directory.** The collection can be bigger (15–30), but attendees see only the first `FreeSteal::SHOWN` (12) switched-on ones by `sort_order` (`FreeSteal::shown()`).
- **Featured** (gold tag on the card) = the CampBuddy team finds it especially worth a look. Never paid placement, never "most popular".
- **Card** (`attendee/partials/free-steal-card.blade.php`) reuses the deal card's styles (`.deal-card`, `components/_sponsor.scss`): a line icon picked from the category's first known word (`FreeSteal::icon()`: AI → sparkles, Gutenberg → columns, Security → id-card, Performance → zap, Media → camera, Local Development → laptop, Content/Publishing → pen-tool, WooCommerce → tag, Developer Tools/Utilities → wrench; otherwise sparkles), name, "by maker", Featured tag, description, category, and one button.
- **The link opens in a new tab** (`target=_blank rel="noopener"`, plain link, no in-app browser): GitHub and WordPress.org refuse to be framed.
- **Tab** sits between Deals and Event Info; `?tab=free-steals` deep-links to it. The tab switch is the existing generic `explore.js` code (no JS change); `explore_tab_view` reports `tab: free-steals`. No separate click event yet.
- **Admin → Free Steals** (`Admin\FreeStealController`, `/admin/free-steals`, super-admin area only): list with Showing / "On, but past the first 12" / Off badges, add, edit, remove. Links must be `http(s)`.
- **Shipped list.** Migration `2026_10_05_090100_install_free_steals` runs `App\Support\FreeSteals::install()` (skipped in unit tests) with the 16 rows from `mockups/campbuddy-free-steals.csv`: the 12 marked "Initial 12" switched on (4 Featured: Visual Blueprint Builder, Multidots Passkey Login, WordPress Skills, The Off Switch, shown first), the other 4 switched off. Idempotent, matched on link; never overwrites an admin's edits.
- **Freshness.** Explore is network-first, so an edit shows on the next page open. Free Steals are not part of `DataVersion`, so an already-open page doesn't auto-reload for a Free Steals edit alone.

Tests: `tests/Feature/FreeStealsTest.php`.

## Later (not built)

- **Suggest a Free Steal**: a maker submits a free tool; the team reviews it before it is added.
- **"Made by someone at this WordCamp"** badge: highlight tools whose maker is attending that event, to start conversations.
