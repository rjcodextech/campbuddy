# CampBuddy Master Architecture: Full Codebase Review, Feature Matrix & Complete Screen/Page Catalog

*   **Author:** rjcodextech
*   **Status:** Approved
*   **Date:** 2026-11-24
*   **Repository Workspace:** [campbuddy/V2](file:///e:/Sites/InHouse/campbuddy/V2)

--------------------------------------------------------------------------------

## Overview

CampBuddy is a multi-platform companion ecosystem engineered for WordPress WordCamp conferences. It operates simultaneously across four distinct client runtime tiers:
1. **Website Frontend (Blade / Vite)**: Responsive desktop and mobile web application providing public discovery, schedule browsing, digital badge creation, and attendee networking, implemented with pure vanilla JavaScript and BEM styling without client UI framework overhead.
2. **Standalone PWA (Progressive Web App)**: Installable mobile web client powered by Service Worker CacheStorage (`campbuddy-v3`) for pre-rendered HTML views and client-side IndexedDB for bookmarks, meetings, and quest progress.
3. **Android APK (Compose Multiplatform)**: Native Android mobile client built with Kotlin 2.1 Multiplatform, on-device `KeyValueStore` persistence (backed by `SharedPreferences` on Android and `NSUserDefaults` on iOS) with `kotlinx.serialization`, canvas QR badge rendering, and background talk notifications.
4. **Admin & Manager Operations Panel**: Operational command center for superadmins and event organizers, built on Laravel Breeze session authentication, providing live room delay broadcasts, mobile app remote JSON configuration, media health audits, push notifications, and attendee moderation reports.

This technical document reviews the entire CampBuddy codebase ([Laravel 12 / PHP 8.3 backend](file:///e:/Sites/InHouse/campbuddy/V2/app) with 327 registered routes, [Vite client utilities](file:///e:/Sites/InHouse/campbuddy/V2/resources/js), [Kotlin Compose Multiplatform native source](file:///e:/Sites/InHouse/campbuddy/V2/native-app), and [FigCraft design specifications](file:///e:/Sites/InHouse/campbuddy/V2/docs/designs/campbuddy_all_screens.figcraft)). It establishes an exhaustive feature matrix of all 26 system features and catalogs all 38 production screens and pages across all platforms using a unified 7-attribute schema.

--------------------------------------------------------------------------------

## The Problem

WordCamp conferences present acute operational and physical challenges:
1. **Venue Corridor Network Congestion**: Cellular networks and venue Wi-Fi regularly experience severe bandwidth saturation during peak keynote transitions and lunch breaks. Apps requiring synchronous network requests fail, freeze, or display stale error pages.
2. **Hallway Decision Paralysis**: Attendees have less than 10 minutes between talks to navigate unfamiliar venue halls, evaluate concurrent speaker topics, and resolve schedule conflicts. Multi-column, desktop-dense schedules lead to attendee disorientation and missed sessions in physical corridors.
3. **Privacy and Statutory Compliance (DPDP Act 2023 / GDPR)**: Exchanging contact credentials during networking cannot rely on central server harvesting or unconsented cloud address books. Attendees require verifiable, on-device local data retention.
4. **Broadcast Information Latency & Compounding Delays**: Unannounced session room delays or emergency venue announcements traditionally bottleneck at physical registration desks. Furthermore, manual room delays must not be overwritten or double-delayed by automated background ingestion scrapers.

CampBuddy solves these problems through an offline-first architecture, local CacheStorage / IndexedDB / KeyValueStore persistence, optical peer-to-peer QR exchanges, automated multi-tier scrapers with fallback resilience, and an integrated room-delay broadcast engine.

--------------------------------------------------------------------------------

## Goals

*   **G-1: Production Data Ingestion & Media Resolution**: Ingest, normalize, and cache complete conference feeds from WordCamp portals (WordCamp Delhi, Athens, Bandung, Nairobi), resolving high-resolution sponsor logos (SVG/PNG) and speaker headshots with automated DOM scraper fallbacks and local storage proxying.
*   **G-2: 2-Second Viewport Render Time**: Present the exact next session, track hall name, and minute countdown on the primary viewport within 2 seconds of cold start on both native APK and PWA under degraded network conditions.
*   **G-3: Zero-Server P2P Networking**: Execute attendee badge swaps via client-rendered URL / vCard 3.0 QR codes and on-device camera scanners without storing or indexing scanned contacts on central servers.
*   **G-4: Universal Multi-Platform Cohesion**: Maintain visual and functional alignment across Website Frontend (11 screens), Standalone PWA (9 screens), Native Android APK (10 screens), and Admin/Staff Panel (8 screens).
*   **G-5: Complete Offline Operation**: Guarantee full schedule navigation, room maps, and badge generation under airplane mode via ServiceWorker CacheStorage, IndexedDB, and native `KeyValueStore`.

--------------------------------------------------------------------------------

## Non-Goals

*   **NG-1: Centralized Cloud Contact Storage**: CampBuddy will not collect, aggregate, or store scanned peer contact lists in a central database.
*   **NG-2: Proprietary Chat Server Infrastructure**: CampBuddy will not host internal Matrix or XMPP chat servers; attendee discovery connects users directly to public profiles (GitHub, LinkedIn, WordPress.org, Telegram).
*   **NG-3: Non-WordCamp CMS Scrapers**: The ingestion engine targets WordCamp.org REST APIs and standard WordPress layouts; generic non-WordCamp platforms are out of scope.
*   **NG-4: Mandatory Cold-Start Language Gate**: No obstructive language picker modals before showing the schedule; the system automatically adopts device locale with manual overrides in settings.

--------------------------------------------------------------------------------

## Open Questions & Decisions

*   **Question 1: Optical Device-to-Device Streaming Fallback**: Should the 6-digit desktop-to-mobile transfer link support an animated/chunked optical QR fallback when venue Wi-Fi is completely severed?
    - **Decision**: **Rejected (Out of Scope)**. Device transfer (F-02) is an initial setup convenience between an attendee's own laptop and phone prior to conference sessions. Optical QR exchanges are reserved for peer CampCards (F-07/F-08). Speculative chunked visual data streaming across screens introduces disproportionate complexity (YAGNI).
*   **Question 2: Static HTML Archival Exporter**: For past events, should an automated routine archive event pages into read-only static HTML after 90 days?
    - **Decision**: **Rejected (Standard Controller Caching Retained)**. The Laravel backend serves past events effortlessly via `EventMemoriesPageController` with negligible database load. Standard HTTP caching headers (`Cache-Control: public, max-age=...`) combined with ServiceWorker CacheStorage already guarantee offline longevity without maintaining a separate static site generation pipeline.

--------------------------------------------------------------------------------

## Critical User Journeys (CUJs)

### CUJ 1: First-Time Attendee Corridor Navigation
*   **Given**: Attendee arrives at WordCamp Delhi venue with congested Wi-Fi; mobile device has CampBuddy PWA or APK installed.
*   **When**: Attendee opens CampBuddy in hallway between sessions.
*   **Then**: The Live Event Dashboard immediately renders the current session in Hall A and upcoming session in Hall B with room tags, zero network latency (served from CacheStorage / `KeyValueStore`), and clear walking directions.

### CUJ 2: Peer-to-Peer Contact Exchange
*   **Given**: Two attendees meet at a sponsor booth and agree to connect.
*   **When**: Attendee A taps "CampCard" to display their offline QR code, and Attendee B scans it with their phone camera or native scanner.
*   **Then**: Attendee B's device parses the direct profile URL or vCard data, triggers confirmation, and saves the connection locally to IndexedDB / `KeyValueStore` without transmitting contact details to a central server.

### CUJ 3: Urgent Schedule Delay Broadcast
*   **Given**: Keynote speaker in Hall A is running 15 minutes late due to technical setup.
*   **When**: Event Lead opens Admin Delay Broadcaster and applies a "+15 min" delay to Hall A.
*   **Then**: Schedule delay is recorded in `schedule_delays` and dynamically applied to session times at read-time via `ScheduleDelay::apply`; baseline `starts_at` is preserved to prevent scraper drift; client devices refresh delay banners within 30–60 seconds via background HTTP polling.

### CUJ 4: Contributor Day Table Onboarding
*   **Given**: First-time contributor attends WordCamp Contributor Day and joins the Documentation table.
*   **When**: Contributor navigates to the "Contributor Quests" view on their mobile device or laptop.
*   **Then**: The app presents the Documentation table onboarding guide, direct links to team Slack and GitHub repos, and an interactive checklist of quests stored in IndexedDB / `KeyValueStore` yielding achievement badges upon completion.

--------------------------------------------------------------------------------

## Technical Plan & System Architecture

### 1. High-Level System Architecture

```text
                                  +------------------------------------+
                                  |     WordCamp.org Official APIs     |
                                  |  (REST Endpoints & Page HTML DOM)  |
                                  +-----------------+------------------+
                                                    |
                                       [Daily & Hourly Cron Sync]
                                                    v
+---------------------------------------------------+-----------------------------------+
|                           CampBuddy Laravel 12 Backend Core                           |
|                                                                                       |
|  - Ingestion Engine: WordCampRestClient, WordCampNormalizer, WordCampScraper          |
|  - Storage: MySQL / SQLite (Events, Sessions, Speakers, Sponsors, Quests, Delays)   |
|  - Services: PublicStorageController, ScheduledTasks, DeviceTransferController        |
|  - Security: DPDPA 2023 / GDPR Alignment, Rate Limiting, Session Auth (Admin)         |
|  - Routing: 327 Registered Routes (Admin, Event Public, API, Well-Known)              |
+----------+------------------------+-------------------------+-------------------------+
           |                        |                         |                         |
   [HTTP / SSR HTML]        [HTTPS / CacheStorage]    [REST JSON API]           [Admin HTTP Polling / ETags]
           |                        |                         |                         |
           v                        v                         v                         v
+--------------------+   +--------------------+   +--------------------+   +--------------------+
|  Website Frontend  |   |   Standalone PWA   |   |   Android App APK  |   |  Admin Operations  |
|  (Blade, Vanilla JS|   | (CacheStorage & IDB|   | (Compose Multiplat |   | (Desktop & Mobile  |
|   Tailwind, BEM)   |   |  campbuddy-v3)     |   |  KeyValueStore)    |   |  Breeze Auth)      |
+--------------------+   +--------------------+   +--------------------+   +--------------------+
```

### 2. Full Codebase Review: Subsystem Breakdown

#### A. Backend Ingestion & Media Pipeline
*   [app/Services/WordCamp/WordCampRestClient.php](file:///e:/Sites/InHouse/campbuddy/V2/app/Services/WordCamp/WordCampRestClient.php): Fetches official WordCamp REST feeds for posts, sessions, speakers, sponsors, and tracks. Contains resilient DOM-scraping fallback routines (`fetchSponsorLogosFromPage()`, `fetchSpeakersFromPage()`) when upstream REST feeds omit media images. Verified feeds: WordCamp Delhi (17/17 sponsor logos), Athens (30/30 sponsor logos), Bandung, and Nairobi.
*   [app/Services/WordCamp/WordCampNormalizer.php](file:///e:/Sites/InHouse/campbuddy/V2/app/Services/WordCamp/WordCampNormalizer.php): Cleans names, extracts track halls, resolves high-resolution logo links, and normalizes disparate upstream schemas into standardized internal structures.
*   [app/Support/ScheduledTasks.php](file:///e:/Sites/InHouse/campbuddy/V2/app/Support/ScheduledTasks.php): Central schedule registry defining 20 automated cron routines, including `event-day-hourly-refresh` (hourly sync during live event days), `ingest-sessions-speakers-sponsors` (daily sync), `fill-missing-photos`, `backfill-event-branding`, and `erase-expired-personal-data` (DPDPA 2023 retention compliance).
*   [app/Http/Controllers/PublicStorageController.php](file:///e:/Sites/InHouse/campbuddy/V2/app/Http/Controllers/PublicStorageController.php): Serves cached branding assets, speaker photos, and sponsor logos stored locally via Laravel queue jobs (`FetchBrandingAssetsJob`, `FillMissingPhotosJob`), eliminating mixed-content issues and external CDN rate-limits.
*   [app/Support/ScheduleDelay.php](file:///e:/Sites/InHouse/campbuddy/V2/app/Support/ScheduleDelay.php): Reconciles live organizer delays with hourly scraper syncs. Delays are stored as delta records on the event model (`schedule_delays`) and applied dynamically at read-time via `ScheduleDelay::apply`. Base session `starts_at` times in the database remain untouched, preventing compounded schedule drift when upstream WordCamp feeds refresh.

#### B. Offline Engine & Client Storage Architecture
*   [public/sw.js](file:///e:/Sites/InHouse/campbuddy/V2/public/sw.js): Production service worker managing three dedicated cache buckets:
    - `campbuddy-v3` (CacheStorage): Pre-caches and stores pre-rendered attendee HTML views (`/event/{slug}`, `/event/{slug}/my-day`, `/event/{slug}/explore`, `/event/{slug}/guide`). Uses a 4000ms network timeout fallback with an optional Stale-While-Revalidate (`swrPages`) flag via `/sw-flags.json`.
    - `campbuddy-avatars`: Dedicated avatar and logo cache with a 30-day eviction policy.
    - Hashed build assets (`build/assets/*`): Cache-first indefinitely.
    - Auth pages (`/admin`, `/login`) and `/api/` endpoints are explicitly bypassed and never stored in browser cache.
*   [resources/js/attendee/db.js](file:///e:/Sites/InHouse/campbuddy/V2/resources/js/attendee/db.js): IndexedDB engine creating 5 client-side object stores:
    - `kv`: Application configuration, flags, and sync timestamps.
    - `bookmarks`: Saved session IDs per event.
    - `questProgress`: Contributor Day and conference achievement XP records.
    - `metHistory`: Locally saved peer connections and contact notes.
    - `meetings`: 1-on-1 meeting records.
*   [native-app/shared/src/commonMain/kotlin/club/campbuddy/shared/data/Repository.kt](file:///e:/Sites/InHouse/campbuddy/V2/native-app/shared/src/commonMain/kotlin/club/campbuddy/shared/data/Repository.kt): Kotlin Multiplatform storage layer utilizing `KeyValueStore` (implemented via `SharedPreferences` on Android and `NSUserDefaults` on iOS). Serializes session bookmarks, profile settings, and event caches into compact JSON payloads using `kotlinx.serialization` without heavyweight SQL drivers.

#### C. Peer-to-Peer Transfer & CampCard Engine
*   [resources/js/attendee/camp-card.js](file:///e:/Sites/InHouse/campbuddy/V2/resources/js/attendee/camp-card.js) & [resources/js/attendee/camp-card-paint.js](file:///e:/Sites/InHouse/campbuddy/V2/resources/js/attendee/camp-card-paint.js): Digital attendee card generator rendering high-contrast 2D QR codes encoding direct public profile links or plaintext vCard 3.0 strings. Supports high-resolution 900 DPI canvas PNG export.
*   [app/Http/Controllers/Api/DeviceTransferController.php](file:///e:/Sites/InHouse/campbuddy/V2/app/Http/Controllers/Api/DeviceTransferController.php) & [resources/js/attendee/device-transfer.js](file:///e:/Sites/InHouse/campbuddy/V2/resources/js/attendee/device-transfer.js): Client-side end-to-end encrypted transfer for moving attendee profiles and bookmarks across devices. The new device generates an ECDH keypair, receives a 6-digit verification code, and requests transfer. The old device seals the payload using ECDH + AES-GCM via WebCrypto. The server stages ciphertext temporarily with strict rate limiting (6 requests/profile/hour) and immediately deletes it upon completion (`prune-device-transfers`), ensuring zero server access to plaintext data.

#### D. Design System & FigCraft Tooling
*   **FigCraft**: The declarative UI canvas modeling tool used to specify multi-tier screen layouts.
*   [tools/build_figcraft_multipage.mjs](file:///e:/Sites/InHouse/campbuddy/V2/tools/build_figcraft_multipage.mjs): Compiles the master 4-page design artifact [docs/designs/campbuddy_all_screens.figcraft](file:///e:/Sites/InHouse/campbuddy/V2/docs/designs/campbuddy_all_screens.figcraft) containing all 38 production screens.
*   [tools/generate_specimen_html.mjs](file:///e:/Sites/InHouse/campbuddy/V2/tools/generate_specimen_html.mjs): Generates HTML documentation specimens and visual verification galleries.

--------------------------------------------------------------------------------

## Complete Codebase Feature Matrix (26 Features)

| Feature ID | Feature Name | Platform Scope | Bound Screen IDs | Offline Support | Architecture & Implementation Summary |
| :--- | :--- | :--- | :--- | :--- | :--- |
| **F-01** | Event Discovery & Multi-Event Hub | Web, PWA, APK | W01, W02, A02 | Pre-cached | Global directory supporting live, upcoming, and past WordCamps with country filtering (India, Europe, Asia, Africa, Americas). Powered by `HomeController` and `PickerScreen.kt`. |
| **F-02** | Desktop-to-Mobile Encrypted Transfer | Web, PWA, APK | W02, A02 | Online Sync | ECDH + AES-GCM encrypted transfer moving bookmarked talks and profile keys between devices via 6-digit PIN and transient staging in `DeviceTransferController`. |
| **F-03** | Touch-Optimized Live Event Dashboard | Web, PWA, APK | W03, P02, A03 | Full Offline | Primary viewport card displaying event phase, 30s ticking countdown timer, current live session, and "Up Next" preview. Rendered via `home.blade.php` and `EventScreens.kt`. |
| **F-04** | Multi-Track Program & Room Schedule | Web, PWA, APK | W04, P03, A04 | Full Offline | Track-based program filterable by room (Hall A, Hall B, Hall C) with talk metadata and speaker details. Uses CacheStorage (`campbuddy-v3`) and `KeyValueStore`. |
| **F-05** | My Day Itinerary & Conflict Resolver | Web, PWA, APK | W05, P04, A05 | Full Offline | Personalized schedule manager detecting overlapping sessions, calculating hall transit gaps, and providing 1-tap clash resolution. Persisted in IndexedDB `bookmarks` and `KeyValueStore`. |
| **F-06** | 10-Minute Prior Alert Scheduler | Web, PWA, APK | W05, A01, A04 | Local Notification | Client-side notification scheduler queuing alerts 10 minutes prior to bookmarked session start times via Web Notifications API and Android system notifications. |
| **F-07** | CampCard Digital Attendee Badge | Web, PWA, APK | W06, P05, A06 | Full Offline | Configurable digital attendee badge rendering QR codes encoding public URLs/vCard 3.0 with privacy toggles (GitHub, LinkedIn, WordPress.org). 900 DPI canvas export. |
| **F-08** | In-Browser & Native QR Scanner | Web, PWA, APK | P06, A07 | Full Offline | Barcode reader using Web MediaDevices / Shape Detection API on web/PWA and native camera viewfinder on Android APK, parsing profile links directly into local storage. |
| **F-09** | Attendee & Role Networking Directory | Web, PWA, APK | W07, P07, A08 | Pre-cached | Searchable attendee directory categorizing participants by role (Speakers, Organizers, Sponsors, Volunteers, Media). Powered by `explore.blade.php` and `People.kt`. |
| **F-10** | Shared Interest Matching ("My People") | Web, PWA, APK | W07, P07, A08 | Full Offline | Client-side interest tag matcher pairing attendees based on shared WordPress technology tags (Core, Gutenberg, WooCommerce, Performance) stored in local storage. |
| **F-11** | Sponsor Showcase & Perks Directory | Web, PWA, APK | W08, A08 | Pre-cached | Tiered sponsor directory (Platinum, Gold, Silver, Microsponsor) displaying verified scraped SVG/PNG logos, booth locations, and discount perks. |
| **F-12** | Contributor Day Quests & Roadmaps | Web, PWA, APK | W09, P08, A09 | Full Offline | Contributor table roadmaps (Core, Docs, Polyglots, Accessibility) with interactive quest checklists and XP progression tracked in IndexedDB `questProgress` and `KeyValueStore`. |
| **F-13** | Venue Navigation & Accessible Map | Web, PWA, APK | W10, P09, A10 | Full Offline | Indoor venue map featuring wheelchair-accessible paths, quiet rooms, and registration markers. Rendered via vector SVG in `guide.blade.php` and `Guide.kt`. |
| **F-14** | Emergency SOS & Code of Conduct | Web, PWA, APK | W10, P09, A10 | Dial-out / Cache | 1-tap confidential incident report dispatcher and direct phone dialer connecting attendees to designated event safety marshals. |
| **F-15** | Post-Event Archival Slides & Memories | Web, PWA, APK | W11 | Cached | Archival repository hosting speaker slide download links, community photo galleries, and personal event recap summaries via `EventMemoriesPageController`. |
| **F-16** | DPDPA 2023 & GDPR Privacy Self-Service | Web, PWA, APK | W07, A10 | Online Sync | Automated privacy self-service enabling attendees to request public roster removal or local data erasure via `RosterRemovalController` and scheduled data pruning. |
| **F-17** | Desktop Operations Control Center | Admin | AP01 | Online Session | Administrative dashboard tracking active attendee counts, room check-in rates, background queue status, and broadcast announcement bars. |
| **F-18** | Live Room Delay Broadcaster | Admin | AP02 | Online Session | Delay adjustment console allowing event leads to delay tracks (+5m, +10m, +15m). Delays are saved in `schedule_delays` and dynamically applied via `ScheduleDelay::apply`. |
| **F-19** | Multi-Channel Broadcast Dispatcher | Admin | AP03 | Online Session | Broadcast composer dispatching alerts across Web Push, PWA, and APK channels with audience filters (All, Speakers, Staff) via `NotificationsController`. |
| **F-20** | Media Health & Scraper Validator | Admin | AP04 | Online Session | Media monitor validating remote sponsor logos and speaker photos, flagging broken URLs, and triggering local pre-warming via `MediaController`. |
| **F-21** | Attendee Roles & Roster Manager | Admin | AP05 | Online Session | Role administration matrix managing SuperAdmin, EventLead, StaffVolunteer, and Speaker permissions, CSV roster imports, and QR staff pairing via `RosterController`. |
| **F-22** | Contributor Table Configuration | Admin | AP06 | Online Session | Table management console configuring Contributor Day teams (Core, Polyglots, Docs), table leads, GitHub onboarding links, and quest XP via `ContributorTableController`. |
| **F-23** | Mobile App Config & Releases | Admin | AP07 | Online Session | Dynamic remote configuration editor for native Android APK: manages navigation tabs, theme colors, feature flags, and hosts chunked APK binary uploads via `MobileAppController`. |
| **F-24** | Attendee Moderation & Incident Reports | Admin | AP08 | Online Session | Safety marshal console reviewing attendee Code of Conduct incident flags and profile reports with status toggles (Assigned, In Progress, Resolved) via `ReportsController`. |
| **F-25** | Social Media Studio & Reel Kit | Admin | AP01 | Online Session | Automated social asset generator producing brand-compliant graphic cards, countdown stories, and video teasers for upcoming talks via `SocialController` (accessed via dashboard tile). |
| **F-26** | Automated WordCamp Ingestion Crons | Backend Core | AP01, AP04 | Scheduled Daemon | Dual-tier background ingestion engine running daily syncs for upcoming events and hourly delta syncs during active event days with DOM fallback scraping via `ScheduledTasks`. |

--------------------------------------------------------------------------------

## Comprehensive Screen & Page Catalog (38 Screens across 4 Platforms)

### Platform Tier 1: Website Frontend (Web UI)
*Layout: `1280×800 Desktop Canvas` · Technology: Laravel Blade, Tailwind CSS, Vanilla JS, BEM, Vite*

| Screen ID | Screen Name | Route / Destination | Template / Implementation | Description | Key UI Elements | Offline Behavior |
| :--- | :--- | :--- | :--- | :--- | :--- | :--- |
| **W01** | Landing Discovery Stage | `/` (`home`) | [resources/views/welcome.blade.php](file:///e:/Sites/InHouse/campbuddy/V2/resources/views/welcome.blade.php) | Public gateway: Featured WordCamp hero card, live event badge, countdown clock, and directory quick-search. | Hero banner, live event indicator, countdown timer, event stats bar, primary CTA buttons. | Serves last cached welcome view from CacheStorage; displays offline banner if disconnected. |
| **W02** | Global Event Directory | `/` (`home#events`) | [resources/views/welcome.blade.php](file:///e:/Sites/InHouse/campbuddy/V2/resources/views/welcome.blade.php) | Global multi-event browser: Lists live, upcoming, and past WordCamps with country and region filter tabs. | Region filter chips (India, Asia, Europe, Africa), event cards with dates/venues, pre-cache trigger buttons. | Filterable list populated from pre-cached HTML; offline indicator on unfetched events. |
| **W03** | Event Companion Home | `/event/{event:slug}` (`event.home`) | [resources/views/attendee/home.blade.php](file:///e:/Sites/InHouse/campbuddy/V2/resources/views/attendee/home.blade.php) | Central event command screen: 30-second ticking live session hero, "Up Next" preview, and quick-action navigation grid. | Live talk hero card, room badge (Hall A/B), 30s ticking timer, 2×3 touch-optimized action grid, emergency SOS bar. | Full offline operation: loads from `campbuddy-v3` CacheStorage; calculates countdown against device clock. |
| **W04** | Multi-Track Schedule | `/event/{event:slug}#schedule` (`event.home`) | [resources/views/attendee/home.blade.php](file:///e:/Sites/InHouse/campbuddy/V2/resources/views/attendee/home.blade.php) | Multi-room program browser: Tabbed room switcher (Hall A, B, C) with session timecards, speaker avatars, and bookmarks. | Room tab buttons, session cards, speaker headshots, category tags, 1-click star bookmark buttons. | Fully accessible offline via CacheStorage; bookmark clicks write directly to IndexedDB `bookmarks`. |
| **W05** | My Day Itinerary | `/event/{event:slug}/my-day` (`event.my-day`) | [resources/views/attendee/my-day.blade.php](file:///e:/Sites/InHouse/campbuddy/V2/resources/views/attendee/my-day.blade.php) | Personalized itinerary planner: Displays bookmarked talks, identifies overlapping time slots, and exports calendar files. | Conflict alert banner, chronological talk list, hall transit time gap chips, `.ics` calendar export trigger. | Full offline operation: queries IndexedDB `bookmarks` and matches against cached schedule DOM. |
| **W06** | CampCard Badge Studio | `/event/{event:slug}/camp-card` (`event.camp-card`) | [resources/views/attendee/camp-card.blade.php](file:///e:/Sites/InHouse/campbuddy/V2/resources/views/attendee/camp-card.blade.php) | Digital badge creator: Configures attendee badge with privacy sliders and generates 2D QR codes and canvas export. | Privacy visibility toggles (GitHub, LinkedIn, WordPress.org), QR canvas preview, 900 DPI PNG download button. | Full offline operation: renders QR code entirely in client-side JavaScript canvas. |
| **W07** | People & Networking Roster | `/event/{event:slug}/explore#people` (`event.explore`) | [resources/views/attendee/explore.blade.php](file:///e:/Sites/InHouse/campbuddy/V2/resources/views/attendee/explore.blade.php) | Community directory: Searchable directory categorizing participants by role with shared interest matching tags. | Role filter pills (Speakers, Organizers, Sponsors, Volunteers), search bar, interest tag chips, contact links. | Serves cached directory from CacheStorage; client-side JavaScript performs instant search filtering. |
| **W08** | Sponsors & Perks Directory | `/event/{event:slug}/explore#sponsors` (`event.explore`) | [resources/views/attendee/explore.blade.php](file:///e:/Sites/InHouse/campbuddy/V2/resources/views/attendee/explore.blade.php) | Tiered sponsor showcase: Verified vector sponsor logos, booth locations, exclusive discounts, and giveaway codes. | Sponsor tier headers (Platinum, Gold, Silver), vector logo cards, booth room markers, discount copy buttons. | Pre-cached in `campbuddy-v3` CacheStorage; sponsor logos served from `campbuddy-avatars` cache. |
| **W09** | Contributor Day Quests | `/event/{event:slug}/contribute` (`event.contribute`) | [resources/views/attendee/contribute.blade.php](file:///e:/Sites/InHouse/campbuddy/V2/resources/views/attendee/contribute.blade.php) | Contributor table roadmap: Team tables (Core, Polyglots, Docs, Accessibility) with onboarding checklists and XP progress. | Table switcher pills, onboarding task checklist, mentor Slack links, GitHub repo cards, XP progress bar. | Checkbox interactions and quest completion states persist locally in IndexedDB `questProgress`. |
| **W10** | Venue & Safety Guide | `/event/{event:slug}/guide` (`event.guide`) | [resources/views/attendee/guide.blade.php](file:///e:/Sites/InHouse/campbuddy/V2/resources/views/attendee/guide.blade.php) | Venue floorplan and safety information: Indoor map, accessibility paths, Code of Conduct, and incident report link. | Indoor vector floorplan SVG, accessibility marker icons, quiet room indicators, emergency call button. | Fully cached in `campbuddy-v3`; vector SVGs render offline without external font or asset requests. |
| **W11** | Post-Event Memories & Slides | `/event/{event:slug}/memories` (`event.memories`) | [resources/views/attendee/memories.blade.php](file:///e:/Sites/InHouse/campbuddy/V2/resources/views/attendee/memories.blade.php) | Archival resource hub: Speaker slide deck links, community photo galleries, and personal event recap summary cards. | Slide download buttons, photo gallery grid with lightbox, personal stats badge, social sharing card generator. | Cached HTML view allows browsing already loaded slide links; external downloads require network. |

---

### Platform Tier 2: Standalone PWA (Mobile Offline App)
*Layout: `390×844 Mobile Standalone Frame` · Technology: ServiceWorker (`campbuddy-v3`), CacheStorage, IndexedDB*

| Screen ID | Screen Name | Route / Destination | Template / Implementation | Description | Key UI Elements | Offline Behavior |
| :--- | :--- | :--- | :--- | :--- | :--- | :--- |
| **P01** | Install & Cache Hero | Standalone Launch Gate | [public/sw.js](file:///e:/Sites/InHouse/campbuddy/V2/public/sw.js) & [resources/js/attendee/offline-warmup.js](file:///e:/Sites/InHouse/campbuddy/V2/resources/js/attendee/offline-warmup.js) | Standalone installation gate: Guides PWA home screen installation and pre-warms offline storage caches. | "Add to Home Screen" prompt card, cache pre-warming progress bar, offline readiness confirmation chip. | Works entirely offline once cached; service worker intercepts installation events. |
| **P02** | Standalone Live Home | `/event/{event:slug}` | [resources/views/attendee/home.blade.php](file:///e:/Sites/InHouse/campbuddy/V2/resources/views/attendee/home.blade.php) | Touch-optimized mobile home: Live talk hero card with ticking timer, quick navigation grid, and offline readiness pill. | Current talk banner, room location tag, 30s ticking countdown timer, 2×3 touch action grid, offline status pill. | Instantly loads pre-rendered HTML from CacheStorage; runs countdown against client device clock. |
| **P03** | Offline Program & Rooms | `/event/{event:slug}#schedule` | [resources/views/attendee/home.blade.php](file:///e:/Sites/InHouse/campbuddy/V2/resources/views/attendee/home.blade.php) | Mobile schedule browser: Rapid track switcher (Hall A vs Hall B), cached speaker headshots, and talk bookmark toggles. | Hall tab buttons, compact session cards, speaker avatar thumbnails, 1-tap star bookmark toggle. | Reads cached DOM from CacheStorage; persists bookmark changes immediately to IndexedDB `bookmarks`. |
| **P04** | Conflict Resolution | `/event/{event:slug}/my-day` | [resources/views/attendee/my-day.blade.php](file:///e:/Sites/InHouse/campbuddy/V2/resources/views/attendee/my-day.blade.php) | Mobile clash resolver: Identifies overlapping bookmarked talks and provides side-by-side selection modal. | Overlap alert banner, side-by-side session comparison cards, 1-tap "Keep This Talk" resolution buttons. | Operates 100% offline via client-side JavaScript reading IndexedDB `bookmarks`. |
| **P05** | Offline CampCard Badge | `/event/{event:slug}/camp-card` | [resources/views/attendee/camp-card.blade.php](file:///e:/Sites/InHouse/campbuddy/V2/resources/views/attendee/camp-card.blade.php) | High-contrast attendee QR badge: Maximum brightness toggle, client-rendered QR canvas, and direct contact details. | High-contrast 2D QR code canvas, screen brightness boost toggle, attendee handle, privacy status chip. | Generates QR code locally offline using client-side canvas renderer without network requests. |
| **P06** | Camera QR Scanner Modal | `/event/{event:slug}/camp-card#scan` | [resources/js/attendee/qr-scanner.js](file:///e:/Sites/InHouse/campbuddy/V2/resources/js/attendee/qr-scanner.js) | In-browser camera scanner: Web MediaDevices viewfinder scanning peer QR codes into local contact records. | Camera viewfinder viewport, target alignment reticle, torch toggle, detected contact preview sheet. | Operates offline using browser Shape Detection API / BarcodeDetector; saves to IndexedDB `metHistory`. |
| **P07** | Offline Attendee Roster | `/event/{event:slug}/explore#people` | [resources/views/attendee/explore.blade.php](file:///e:/Sites/InHouse/campbuddy/V2/resources/views/attendee/explore.blade.php) | Mobile attendee directory: Fast fuzzy search across cached participants, role filter pills, and personal contact notes. | Instant search input, role filter chips (Speakers, Volunteers, Sponsors), contact action links. | Searches cached HTML DOM directly; saves personal attendee notes to IndexedDB `metHistory`. |
| **P08** | Contributor Day Checklist | `/event/{event:slug}/contribute` | [resources/views/attendee/contribute.blade.php](file:///e:/Sites/InHouse/campbuddy/V2/resources/views/attendee/contribute.blade.php) | Mobile contributor roadmap: Interactive task checklist, table lead callout, and XP progression bar. | Table selector carousel, interactive checklist checkboxes, mentor call button, XP earned counter. | Checkbox states and XP scores persist entirely in IndexedDB `questProgress` without internet. |
| **P09** | Venue Map & Emergency SOS | `/event/{event:slug}/guide` | [resources/views/attendee/guide.blade.php](file:///e:/Sites/InHouse/campbuddy/V2/resources/views/attendee/guide.blade.php) | Mobile venue guide: Zoomable floorplan SVG, emergency safety hotline dialer, and Code of Conduct summary. | Pinch-to-zoom indoor vector map SVG, safety marshal phone button, Code of Conduct accordion. | Fully cached in `campbuddy-v3`; vector map and safety information render offline. |

---

### Platform Tier 3: Native Android App (Compose Multiplatform APK)
*Layout: `390×844 Mobile Frame` · Technology: Kotlin 2.1, Compose Multiplatform, `KeyValueStore`, Android Camera*

| Screen ID | Screen Name | Route / Destination | Template / Implementation | Description | Key UI Elements | Offline Behavior |
| :--- | :--- | :--- | :--- | :--- | :--- | :--- |
| **A01** | Native Splash & Onboarding | `Destination.Onboarding` | [club.campbuddy.shared.ui.Onboarding.kt](file:///e:/Sites/InHouse/campbuddy/V2/native-app/shared/src/commonMain/kotlin/club/campbuddy/shared/ui/Onboarding.kt) | First-launch screen: Warm brand palette, `KeyValueStore` cache initialization, and talk reminder notification setup. | Animated brand logo, 5-question profile setup card, notification permission prompt, "Get Started" CTA. | Boots instantly from cached `KeyValueStore` without waiting for network response. |
| **A02** | Event Picker & Transfer | `Destination.Picker` | [club.campbuddy.shared.ui.PickerScreen.kt](file:///e:/Sites/InHouse/campbuddy/V2/native-app/shared/src/commonMain/kotlin/club/campbuddy/shared/ui/PickerScreen.kt) | Conference selector: Country filter chips, conference card list, and 6-digit PIN input to import desktop bookmarks. | Region filter chips, conference cards with dates, 6-digit sync PIN dialog, "Download Offline Data" button. | Displays previously loaded events from `KeyValueStore`; sync PIN import requires network. |
| **A03** | Touch-Optimized Live Home | `Destination.Home` | [club.campbuddy.shared.ui.EventScreens.kt](file:///e:/Sites/InHouse/campbuddy/V2/native-app/shared/src/commonMain/kotlin/club/campbuddy/shared/ui/EventScreens.kt) | Native mobile home screen: 30-second ticking live countdown hero card, speaker thumbnail, and 2×3 touch action grid. | 30s ticking live countdown card, room hall badge, speaker avatar, 2×3 touch action grid, emergency SOS bar. | Operates 100% offline: reads from `Repository.kt` cache and runs ticking clock against system time. |
| **A04** | Session Detail View | `Destination.SessionDetail` | [club.campbuddy.shared.ui.EventScreens.kt](file:///e:/Sites/InHouse/campbuddy/V2/native-app/shared/src/commonMain/kotlin/club/campbuddy/shared/ui/EventScreens.kt) | Detailed talk breakdown: Full speaker biography, social links, room location pin, and talk alert reminder toggle. | Talk title, speaker bio, room hall tag, 1-tap star bookmark button, 10-minute prior notification toggle. | Full offline operation: renders cached talk details from `KeyValueStore`; queues system alert locally. |
| **A05** | My Day Itinerary | `Destination.MyDay` | [club.campbuddy.shared.ui.MyDay.kt](file:///e:/Sites/InHouse/campbuddy/V2/native-app/shared/src/commonMain/kotlin/club/campbuddy/shared/ui/MyDay.kt) | Personalized schedule timeline: Room transit calculations, attendance checkmarks, and clash resolution cards. | Chronological session timeline, room transit walk chips, clash resolution buttons, attendance XP checkmarks. | Reads bookmarks from `KeyValueStore`; calculates timeline and room transit entirely on-device. |
| **A06** | CampCard Native View | `Destination.CampCard` | [club.campbuddy.shared.ui.CampCardScreen.kt](file:///e:/Sites/InHouse/campbuddy/V2/native-app/shared/src/commonMain/kotlin/club/campbuddy/shared/ui/CampCardScreen.kt) | Native digital badge: 2D QR code generator encoding direct profile URL / vCard 3.0 with customizable privacy fields. | High-contrast QR canvas, privacy field toggles (GitHub, LinkedIn, WordPress.org), badge theme switcher. | Generates QR matrix directly on-device via `QrCode.kt`; zero cloud communication required. |
| **A07** | Native Scanner Sheet | `Destination.Scanner` | [club.campbuddy.shared.ui.QrCode.kt](file:///e:/Sites/InHouse/campbuddy/V2/native-app/shared/src/commonMain/kotlin/club/campbuddy/shared/ui/QrCode.kt) | Fast barcode reader: Uses Android native camera integration to scan attendee QR codes (<50ms detection). | Camera preview viewport, scanning reticle, scanned contact details sheet, "Save to My People" button. | Parses vCard and profile URLs offline; persists contact record to `KeyValueStore` without server sync. |
| **A08** | Explore Directory | `Destination.Explore` | [club.campbuddy.shared.ui.People.kt](file:///e:/Sites/InHouse/campbuddy/V2/native-app/shared/src/commonMain/kotlin/club/campbuddy/shared/ui/People.kt) | Integrated directory: Tabbed attendee directory, speaker list, scraped sponsor grid, and debounced search. | Tab bar (Attendees, Speakers, Sponsors), debounced search field, role badges, sponsor tier cards. | Searches cached event roster in memory; avatar images load from disk cache if network is unavailable. |
| **A09** | Quests & XP Rewards | `Destination.Quests` | [club.campbuddy.shared.ui.MoreTabs.kt](file:///e:/Sites/InHouse/campbuddy/V2/native-app/shared/src/commonMain/kotlin/club/campbuddy/shared/ui/MoreTabs.kt) | Gamification center: Conference achievement quests ("Visit 3 sponsors", "Contribute to Docs"), XP progress bar. | Quest achievement cards, progress bar indicator, unlockable achievement badges, Contributor Day guide button. | Quest completion state and XP totals persist locally in `KeyValueStore` across app restarts. |
| **A10** | Safety & Incident Dialog | `Destination.Legal` | [club.campbuddy.shared.ui.LegalSheet.kt](file:///e:/Sites/InHouse/campbuddy/V2/native-app/shared/src/commonMain/kotlin/club/campbuddy/shared/ui/LegalSheet.kt) | Code of Conduct & safety dialog: 1-tap dialer to Head Organizer phone and confidential incident report submission. | Emergency phone dialer button, Code of Conduct text accordion, confidential report input form. | Displays cached safety guidelines and phone dialer offline; submitting web form queues until online. |

---

### Platform Tier 4: Admin & Staff Panel
*Layout: `1280×800 Desktop` & `390×844 Mobile` · Technology: Laravel Breeze Session Auth, Tailwind CSS, HTTP / Polling with ETags*

| Screen ID | Screen Name | Route / Destination | Template / Implementation | Description | Key UI Elements | Offline Behavior |
| :--- | :--- | :--- | :--- | :--- | :--- | :--- |
| **AP01** | Desktop Control Center | `/admin` (`admin.dashboard`) | [resources/views/admin/events/index.blade.php](file:///e:/Sites/InHouse/campbuddy/V2/resources/views/admin/events/index.blade.php) via `Admin\DashboardController` | Master administrative hub: Active attendee counts, room check-in rates, background queue status, and broadcast announcement bars. | Event status cards, scraper health widgets, active delay alert banners, quick action buttons. | Requires authenticated online session; admin pages are never cached in ServiceWorker. |
| **AP02** | Live Delay Broadcaster | `/admin/events/{event}/delay` (`admin.events.delay`) | [resources/views/admin/events/delay.blade.php](file:///e:/Sites/InHouse/campbuddy/V2/resources/views/admin/events/delay.blade.php) via `Admin\ScheduleDelayController` | Room delay management console: Incremental delay controls (+5m, +10m, +15m) with automatic cascade across all client schedules. | Track selector dropdown (All, Hall A, Hall B), delay increment buttons (+5m, +10m, +15m), active delay list, "Clear Delays" button. | Requires online connection; updates `schedule_delays` and broadcasts changes to client polling channels. |
| **AP03** | Push Announcement Dispatcher | `/admin/events/{event}/notifications` (`admin.events.notifications`) | [resources/views/admin/events/notifications.blade.php](file:///e:/Sites/InHouse/campbuddy/V2/resources/views/admin/events/notifications.blade.php) via `Admin\NotificationsController` | Multi-channel broadcast composer: Severity selector (Info, Schedule, Urgent), target segment filter (All, Speakers, Staff), live preview. | Broadcast message textarea, audience segment radio pills, severity dropdown, push notification preview card. | Requires online connection; enqueues Web Push and app notification jobs on server queue. |
| **AP04** | Sponsors & Media Health | `/admin/media` (`admin.media.index`) | [resources/views/admin/media/index.blade.php](file:///e:/Sites/InHouse/campbuddy/V2/resources/views/admin/media/index.blade.php) via `Admin\MediaController` | Media audit console: Scraper verification status (Delhi 17/17, Athens 30/30), broken URL detector, 1-click media pre-warming tool. | Media asset health table, remote URL status chips, broken image detector, "Pre-warm Media Proxy" button. | Requires online connection; queries media database and dispatches background fetch jobs. |
| **AP05** | Attendee Roles & Roster Manager | `/admin/events/{event}/roster` (`admin.events.roster`) | [resources/views/admin/roster/index.blade.php](file:///e:/Sites/InHouse/campbuddy/V2/resources/views/admin/roster/index.blade.php) via `Admin\RosterController` | Role administration matrix: SuperAdmin, EventLead, StaffVolunteer, Speaker permission toggles, CSV roster import, QR staff pairing. | Searchable attendee roster table, role assignment dropdowns, CSV import dropzone, temporary staff pairing QR. | Requires online connection; executes database role changes and permission audits. |
| **AP06** | Contributor Table Config | `/admin/events/{event}/contributor-tables` (`admin.events.contributor-tables.index`) | [resources/views/admin/contributor-tables/index.blade.php](file:///e:/Sites/InHouse/campbuddy/V2/resources/views/admin/contributor-tables/index.blade.php) via `Admin\ContributorTableController` | Table setup editor: Contributor table configuration (Core, Polyglots, Docs), table lead assignments, custom quest XP editor. | Table team cards, table lead selector, GitHub onboarding repo URL fields, quest XP value editor. | Requires online connection; updates `contributor_tables` and quest manifests. |
| **AP07** | Mobile App Config & Releases | `/admin/mobile-app` (`admin.mobile.index`) | [resources/views/admin/mobile/index.blade.php](file:///e:/Sites/InHouse/campbuddy/V2/resources/views/admin/mobile/index.blade.php) via `Admin\MobileAppController` | Dynamic mobile app remote configuration: Edits app navigation tabs, theme colors, feature flags, and hosts APK releases. | Tab order selector, brand primary color picker, feature toggle switches, APK chunked upload dropzone. | Requires online connection; publishes versioned JSON configs consumed by native apps. |
| **AP08** | Attendee Moderation & Reports | `/admin/events/{event}/reports` (`admin.events.reports`) | [resources/views/admin/reports/index.blade.php](file:///e:/Sites/InHouse/campbuddy/V2/resources/views/admin/reports/index.blade.php) via `Admin\ReportsController` | Safety marshal console: Real-time incident notification feed, attendee profile reports, status toggles (Assigned, In Progress, Resolved). | Incoming report queue, reported profile preview, resolution action buttons (Dismiss, Hide Profile, Ban), status tags. | Requires online connection; manages attendee profile visibility and safety compliance. |

--------------------------------------------------------------------------------

## Alternatives Considered

### Alternative A: Centralized Cloud Contact Storage
*   **Pros**: Enables automatic cross-device sync of scanned contacts without physical QR scanning.
*   **Cons**: Introduces high data liability under DPDPA 2023 and GDPR, requires user account registration, creates server-side storage overhead, and fails completely under venue network outages.
*   **Reason for Rejection**: Violates core CampBuddy zero-retention privacy principles and fails during Wi-Fi blackouts.

### Alternative B: Single Monolithic Native App Store Release Only (No Web/PWA)
*   **Pros**: Reduces build matrix to native Kotlin Compose Multiplatform only.
*   **Cons**: Excludes attendees who do not wish to download an APK or lack Google Play access; prevents instant sharing of event links via desktop browsers.
*   **Reason for Rejection**: WordCamp attendees require instant browser access without download gates.

### Alternative C: Retaining Legacy 7-Tab Bottom Navigation Bar on Mobile
*   **Pros**: Maintains visual layout familiarity for legacy app users.
*   **Cons**: Creates cognitive clutter and menu hunting in crowded physical hallways; increases drop-off during keynote-to-track room changes.
*   **Reason for Rejection**: Attendees walking through physical venue corridors have at most 2–3 seconds to make a navigation decision. The multi-tab bottom bar creates decision paralysis.

--------------------------------------------------------------------------------

## Non-Functional Requirements

*   **Security & Privacy**: Zero server retention of scanned peer contacts. Architected for DPDP Act 2023 and GDPR alignment via local on-device retention and zero-cloud processing. Plaintext vCard 3.0 / direct URL QR codes. Strict Content-Security-Policy with zero inline scripts in user content. Authenticated admin routes protected by Laravel Breeze session auth.
*   **Performance & Latency**: Sub-100ms UI response time for schedule lookups. 30–60 second background polling cadence for live room delays with ETag / `If-Modified-Since` headers to prevent server stampedes. Sub-50ms barcode detection via on-device camera.
*   **Offline Resilience**: 100% of schedule, speaker, sponsor, and map data pre-cached into `KeyValueStore` (APK) and CacheStorage `campbuddy-v3` / IndexedDB (PWA). Full operation in airplane mode with graceful sync upon reconnect.

--------------------------------------------------------------------------------

## Detailed Implementation Plan

### Phase 1: Ingestion Engine & Media Pipeline Verification
1. Verify `WordCampRestClient.php` and `WordCampScraper.php` DOM fallback parsers against live feeds (Delhi, Athens, Bandung, Nairobi).
2. Execute `ScheduledTasks::WHAT['fill-missing-photos']` and `ScheduledTasks::WHAT['backfill-event-branding']` to confirm local storage proxying via `PublicStorageController`.
3. Validate that `ScheduleDelay::apply` preserves database baseline `starts_at` times during hourly scraper refresh runs.

### Phase 2: Client Data Layer & Service Worker Hardening
1. Validate `public/sw.js` cache partitioning (`campbuddy-v3`, `campbuddy-avatars`) and ensure authentication paths (`/admin`, `/login`) are never cached.
2. Confirm IndexedDB object stores (`kv`, `bookmarks`, `questProgress`, `metHistory`, `meetings`) initialize cleanly in `resources/js/attendee/db.js`.
3. Verify Kotlin Multiplatform `Repository.kt` and `KeyValueStore` serialization for Android and iOS targets.

### Phase 3: Screen & Multi-Platform UI Synchronization
1. Synchronize all 11 Website Frontend Blade views ([resources/views/attendee/](file:///e:/Sites/InHouse/campbuddy/V2/resources/views/attendee/)) with touch-optimized 2×3 action grids and live ticking countdown timers.
2. Ensure PWA standalone views (P01–P09) match mobile layout specifications and offline readiness indicators.
3. Validate Native Android APK composables ([club.campbuddy.shared.ui](file:///e:/Sites/InHouse/campbuddy/V2/native-app/shared/src/commonMain/kotlin/club/campbuddy/shared/ui/)) matching screen catalog specifications A01–A10.

### Phase 4: Admin Panel Delay & Broadcast Verification
1. Validate Live Room Delay Broadcaster (`/admin/events/{event}/delay`) cascading delays across Hall A/B/C.
2. Test Push Announcement Dispatcher (`/admin/events/{event}/notifications`) message dispatch.
3. Confirm Mobile App Config (`/admin/mobile-app`) saves JSON configs and serves APK updates.

### Phase 5: Verification & Multi-Page FigCraft Artifact Compilation
1. Run JavaScript test suite: `npm run test:js` (verify passing status across all unit and integration tests).
2. Run PHP test suite: `php artisan test` (verify complete passing status across backend test suite).
3. Execute FigCraft CLI builder: `node tools/build_figcraft_multipage.mjs` compiling [docs/designs/campbuddy_all_screens.figcraft](file:///e:/Sites/InHouse/campbuddy/V2/docs/designs/campbuddy_all_screens.figcraft).
4. Run `figcraft-cli info docs/designs/campbuddy_all_screens.figcraft` to confirm all 4 pages and 38 screens validate successfully.

--------------------------------------------------------------------------------

*Drafted with EGM*
