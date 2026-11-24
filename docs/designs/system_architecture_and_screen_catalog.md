# CampBuddy Master Architecture: Full Codebase Review, Feature Matrix & Complete Screen/Page Catalog

*   **Author:** rjcodextech
*   **Status:** Approved
*   **Date:** 2026-11-24

--------------------------------------------------------------------------------

## Overview

CampBuddy is a multi-platform companion ecosystem engineered for WordPress WordCamp conferences. It operates simultaneously across four distinct client runtime tiers:
1. **Website Frontend (Blade / Vite)**: Responsive desktop and mobile web application providing public discovery, schedule browsing, and digital badge creation.
2. **PWA (Progressive Web App)**: Standalone, installable mobile client powered by Service Worker CacheStorage, client-side IndexedDB, and zero-network execution.
3. **Android APK (Compose Multiplatform)**: Native Android mobile client built with Kotlin Multiplatform, on-device SQLite, CameraX MLKit optical barcode scanning, and background push notifications.
4. **Admin & Manager Panel (Desktop Hub & Mobile Staff Console)**: Operational command center for superadmins, event organizers, and volunteer door staff.

This technical document reviews the entire CampBuddy codebase (Laravel 12 / PHP 8.3 backend with 327 registered routes, Vue/Vite frontend utilities, Kotlin Compose Multiplatform native source, and FigCraft design specifications). It establishes an exhaustive inventory of all 26 system features and catalogs all 38 production screens and pages across all platforms.

--------------------------------------------------------------------------------

## The Problem

WordCamp conferences present acute operational and physical challenges:
1. **Venue Corridor Network Congestion**: Cellular networks and venue Wi-Fi regularly choke during peak keynote transitions and lunch breaks. Apps requiring synchronous network requests fail, freeze, or display stale error pages.
2. **Hallway Decision Paralysis**: Attendees have less than 10 minutes between talks to navigate unfamiliar venue halls, evaluate concurrent speaker topics, and resolve schedule conflicts. Multi-column, desktop-dense schedules create high attendee drop-off in physical hallways.
3. **Privacy and Statutory Compliance (DPDP Act 2023 / GDPR)**: Exchanging contact credentials during networking cannot rely on central server harvesting or unconsented cloud address books. Attendees require verifiable, on-device peer-to-peer data sovereignty.
4. **Broadcast Information Latency**: Unannounced session room delays or emergency venue announcements traditionally bottleneck at physical registration desks, causing hallway crowd bunching.

CampBuddy solves these problems through an offline-first architecture, local SQLite/IndexedDB caching, optical peer-to-peer vCard QR exchanges, automated multi-tier scrapers with fallback resilience, and an integrated room-delay broadcast engine.

--------------------------------------------------------------------------------

## Goals

*   **G-1: 100% Real Data Ingestion & Media Integrity**: Ingest, normalize, and cache complete conference feeds from WordCamp portals (WordCamp Delhi, Athens, Bandung, Nairobi), guaranteeing verified high-resolution sponsor logos (SVG/PNG) and speaker headshots with automated DOM scraper fallbacks.
*   **G-2: 2-Second Corridor Glanceability**: Present the exact next session, track hall name, and minute countdown on the primary viewport within 2 seconds of cold start on both native APK and PWA.
*   **G-3: Zero-Server P2P Networking**: Execute attendee badge swaps via client-rendered vCard 3.0 QR codes and on-device camera scanners without storing or indexing scanned contacts on central servers.
*   **G-4: Universal Multi-Platform Cohesion**: Maintain visual and functional alignment across Website Frontend (11 screens), Standalone PWA (9 screens), Native Android APK (10 screens), and Admin/Staff Panel (8 screens).
*   **G-5: Total Offline Autonomy**: Guarantee full schedule navigation, room maps, and badge generation under airplane mode via ServiceWorker CacheStorage, IndexedDB, and native SQLite.

--------------------------------------------------------------------------------

## Non-Goals

*   **NG-1: Centralized Cloud Contact Storage**: CampBuddy will not collect, aggregate, or store scanned peer contact lists in a central database.
*   **NG-2: Proprietary Chat Server Infrastructure**: CampBuddy will not host internal Matrix or XMPP chat servers; attendee discovery connects users directly to public profiles (GitHub, LinkedIn, WordPress.org, Telegram).
*   **NG-3: Non-WordCamp CMS Scrapers**: The ingestion engine targets WordCamp.org REST APIs and standard WordPress layouts; generic non-WordCamp platforms are out of scope.
*   **NG-4: Mandatory Cold-Start Language Gate**: No obstructive language picker modals before showing the schedule; the system automatically adopts device locale with manual overrides in settings.

--------------------------------------------------------------------------------

## Open Questions

*   **Question 1**: Should the 6-digit desktop-to-mobile transfer link support automated peer discovery via local Wi-Fi multicast DNS (mDNS) as a fallback when internet access is fully disconnected?
*   **Question 2**: For past events, should post-event memories remain open indefinitely for slide downloads, or automatically archive to read-only static HTML after 90 days?

--------------------------------------------------------------------------------

## Critical User Journeys (CUJs)

### CUJ 1: First-Time Attendee Corridor Navigation
*   **Given**: Attendee arrives at WordCamp Delhi venue with congested Wi-Fi; mobile device has CampBuddy installed.
*   **When**: Attendee opens CampBuddy APK or PWA in hallway between sessions.
*   **Then**: The Live Event Dashboard immediately renders the current session in Hall A and upcoming session in Hall B with room tags, zero network latency, and clear walking directions.

### CUJ 2: Peer-to-Peer Contact Exchange
*   **Given**: Two attendees meet at a sponsor booth and agree to connect.
*   **When**: Attendee A taps "CampCard" to display their offline QR code, and Attendee B taps "Scan QR" with CameraX MLKit.
*   **Then**: Attendee B's device parses the vCard 3.0 data, triggers a haptic confirmation, and saves the contact locally to SQLite/IndexedDB without transmitting any data to a central cloud server.

### CUJ 3: Urgent Schedule Delay Broadcast
*   **Given**: Keynote speaker in Hall A is running 15 minutes late due to technical setup.
*   **When**: Event Lead opens Admin Delay Broadcaster and applies "+15 min" delay to Hall A.
*   **Then**: Schedule delay cascades across all subsequent Hall A sessions; Web, PWA, and APK clients update their ticking countdown cards within 30 seconds and display a notice banner.

### CUJ 4: Contributor Day Table Onboarding
*   **Given**: First-time contributor attends WordCamp Contributor Day and joins the Documentation table.
*   **When**: Contributor navigates to the "Contributor Quests" view on their mobile device or laptop.
*   **Then**: The app presents the Documentation table onboarding guide, direct links to the team Slack channel and GitHub repository, and an interactive checklist of quests yielding achievement badges upon completion.

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
|  - Services: MediaProxy, ScheduledTasks, AppPushNotification, P2P TransferCrypto      |
|  - Security: DPDP 2023 / GDPR Compliance, Rate Limiting, Role-Based Access Control    |
|  - Routing: 327 Registered Routes (Admin, Manager, Attendee, API, Well-Known)        |
+----------+------------------------+-------------------------+-------------------------+
           |                        |                         |                         |
   [Blade / SSR API]        [Service Worker]          [Ktor REST API]           [Admin WebSockets]
           |                        |                         |                         |
           v                        v                         v                         v
+--------------------+   +--------------------+   +--------------------+   +--------------------+
|  Website Frontend  |   |   Standalone PWA   |   |   Android App APK  |   |  Admin Operations  |
|  (Blade, Vite, CSS)|   | (IndexedDB Offline)|   | (Compose Multiplat)|   | (Desktop & Mobile) |
+--------------------+   +--------------------+   +--------------------+   +--------------------+
```

### 2. Full Codebase Review: Subsystem Breakdown

#### A. Backend Ingestion & Media Proxy (`app/Services/WordCamp/`, `app/Http/Controllers/`)
- `WordCampRestClient.php`: Fetches official WordCamp REST feeds for posts, sessions, speakers, sponsors, and tracks. Features resilient DOM-scraping fallback methods (`fetchSponsorLogosFromPage()`, `fetchSpeakersFromPage()`) when REST feeds omit image media.
- `WordCampNormalizer.php`: Standardizes ingested raw payloads into consistent internal schemas. Cleans names, extracts track locations, and formats high-resolution logo links.
- `MediaProxyController.php`: Pre-warms, caches, and proxies external media assets (sponsor logos, avatars) with CORS headers, preventing mixed-content warnings and rate-limit bans from external CDNs.
- `ScheduledTasks.php`: Defines automated cron schedules: `event-day-hourly-refresh` (runs hourly on active event days) and `daily-event-refresh` (runs daily at midnight for upcoming events).

#### B. Offline Engine & Client Data Layer
- `public/sw.js`: Service worker implementing stale-while-revalidate caching for static build artifacts, network-first caching for HTML views, and cached asset fallback (`503.blade.php`).
- `resources/js/offline-warmup.js` & `resources/js/db.js`: IndexedDB local database engine caching full schedule manifests, speaker rosters, sponsor lists, and saved bookmarked talks on the client side.
- `native-app/shared/src/commonMain/kotlin/club/campbuddy/shared/data/Repository.kt`: Kotlin Multiplatform repository managing offline SQLite synchronization, local bookmark persistence, and session conflict calculations.

#### C. Peer-to-Peer Transfer & CampCard Engine
- `resources/js/camp-card.js` & `resources/js/camp-card-paint.js`: Client-side vCard 3.0 generator and canvas renderer producing offline QR codes readable by standard camera scanners.
- `resources/js/move-data.js` & `app/Services/TransferCrypto.php`: End-to-end encrypted 6-digit key exchange transferring bookmarked itineraries between desktop browsers and mobile devices.

---

## Complete Codebase Feature Inventory (26 Features)

### 1. Attendee Experience Features
1. **Event Discovery & Multi-Event Directory**: Global picker supporting ongoing, upcoming, and past WordCamps with country category filtering (India, Europe, Asia, Africa, Americas).
2. **Desktop-to-Mobile 6-Digit Transfer**: End-to-end device state transfer importing bookmarked schedules and custom tags from a desktop browser to a mobile device via a transient 6-digit PIN.
3. **Tactile Live Event Dashboard**: Hero card displaying current event phase, running countdown clock (30-second interval), live active talk, and immediate "Up Next" preview.
4. **Multi-Track Program & Room Schedule**: Full multi-track schedule filterable by room (e.g., Hall A Main Stage, Hall B Developer Track, Hall C Community).
5. **My Day Smart Itinerary & Conflict Resolution Hub**: Bookmarking system that detects overlapping sessions, calculates time gaps, and prompts attendees to resolve clashes.
6. **10-Minute Prior Alert Scheduler**: Client-side local notification engine queuing alerts 10 minutes prior to bookmarked session start times.
7. **CampCard Digital Attendee Badge Studio**: Configurable badge generator rendering vCard 3.0 QR codes with customizable privacy sliders (share/hide GitHub, LinkedIn, Telegram, bio).
8. **Camera & MLKit QR Scanner**: Fast in-browser and native on-device barcode scanner reading attendee badges and populating local contacts without internet connectivity.
9. **Attendee & Role Networking Roster**: Searchable directory categorizing event participants by role (Speakers, Organizers, Sponsors, Volunteers, Media, Attendees).
10. **Shared Interest Matching ("My People")**: On-device tag matcher pairing attendees based on shared WordPress technology interests (Core, Gutenberg, WooCommerce, REST API, Performance).
11. **Sponsor Showcase & Swag Steals Wall**: Tiered sponsor presentation (Platinum, Gold, Silver, Microsponsor) displaying verified scraped logos, promotional discount codes, and booth check-in quests.
12. **Contributor Day Quests & Gamification**: Table onboarding guides (Core, Documentation, Polyglots, Accessibility, Community) with XP trackers, step-by-step roadmaps, and achievement badges.
13. **Venue Navigation & Accessibility Map**: High-contrast vector indoor floorplan with wheelchair-accessible pathways, quiet rooms, and registration desk markers.
14. **Emergency SOS & Code of Conduct Helpline**: 1-tap confidential incident dispatcher and emergency telephone link directly connecting attendees to designated safety marshals.
15. **Post-Event Archival Slides & Memories**: Slide repository linking speaker slide decks, community photo galleries, and personal recap summaries.
16. **DPDP Act 2023 & GDPR Privacy Self-Service**: Automated privacy portal enabling attendees to request complete local data erasure or initiate a public roster removal request.

### 2. Operations, Admin & Staff Features
17. **Desktop Operations Control Center**: Real-time administrative dashboard tracking active attendees on-site, check-in completion percentages, room occupancy, and background worker health.
18. **Live Room Delay Broadcaster**: Delay adjustment interface allowing event leads to delay specific tracks (+5m, +10m, +15m) and automatically cascade all downstream talk times across clients.
19. **Multi-Channel Push Announcement Dispatcher**: Web Push, PWA, and APK push composer segmenting broadcasts by audience (All Attendees, Speakers Only, Volunteers) with severity tiers (Info, Schedule Change, Security Alert).
20. **Media Health & Automated Scraper Validator**: Media monitor validating all remote sponsor logos and speaker photos, flagging broken remote URLs and pre-warming local cache proxies.
21. **Role-Based Access Control (RBAC) & Staff Pairing**: Permission system defining SuperAdmin, EventLead, StaffVolunteer, and TrackManager roles with temporary QR staff pairing.
22. **Contributor Table Configuration & Task Manager**: Table lead assignment console configuring GitHub onboarding repositories, Slack channels, and custom XP quests.
23. **Fast-Flow Volunteer Gate Check-In**: High-throughput mobile QR scanning console for volunteers verifying registration, badge issuance, and t-shirt distribution.
24. **Mobile Incident Response Dispatch**: Roaming safety console alerting marshals to incoming incident reports with room location pins and resolution workflows.
25. **Social Media Studio & Reel Kit**: Automated social banner and story generator producing brand-compliant graphics, countdown images, and video teasers for upcoming talks.
26. **Automated WordCamp Scraper & Ingestion Crons**: Dual-tier background ingestion engine running daily syncs for upcoming events and hourly delta syncs during active event days with DOM fallback scraping.

---

## Comprehensive Screen & Page Catalog (38 Screens across 4 Platforms)

### Page 1: Website Frontend (Web UI)
*Layout: `1280×800 Desktop Canvas` · Technology: Laravel Blade, Tailwind CSS, Alpine.js, Vite*

| Screen ID | Screen Name | Route / Path | Primary Blade View | Flow Role & Functional Description |
| :--- | :--- | :--- | :--- | :--- |
| **W01** | Landing Discovery Stage | `/` (`home`) | `resources/views/welcome.blade.php` | First-time visitor entry stage: Event headline, dynamic countdown timer, event stats bar, quick CTA triggers. |
| **W02** | Event Directory & Hub | `/events` (`events.index`) | `resources/views/welcome.blade.php` | Global multi-event directory: Live, upcoming, and past WordCamps with country category filtering and pre-cache triggers. |
| **W03** | Event Companion Home | `/{slug}` (`attendee.home`) | `resources/views/attendee/home.blade.php` | Main attendee command center: 30s ticking live hero card, "Up Next" preview, 2×3 tactile action grid, emergency SOS bar. |
| **W04** | Multi-Track Schedule | `/{slug}/schedule` (`attendee.schedule`) | `resources/views/attendee/home.blade.php` | Multi-room program browser: Room tabs (Hall A, B, C), session timecards, speaker avatars, 1-click itinerary bookmarking. |
| **W05** | My Day & Conflict Hub | `/{slug}/my-day` (`attendee.my-day`) | `resources/views/attendee/my-day.blade.php` | Personalized itinerary manager: Conflict warning banner, visual overlap detector, .ics export, and 10m reminder controls. |
| **W06** | CampCard Badge Studio | `/{slug}/camp-card` (`attendee.camp-card`) | `resources/views/attendee/camp-card.blade.php` | Digital badge workshop: Privacy visibility sliders, vCard 3.0 encrypted QR generator, Apple/Google Wallet export buttons. |
| **W07** | Attendees & Roles Directory | `/{slug}/people` (`attendee.people`) | `resources/views/attendee/about.blade.php` | Community networking directory: Filter chips (Speakers, Organizers, Volunteers, Sponsors), attendee search, shared interest tags. |
| **W08** | Sponsors & Steals Wall | `/{slug}/explore` (`attendee.explore`) | `resources/views/attendee/explore.blade.php` | Tiered sponsor exhibition: High-resolution scraped vector logos, booth locations, exclusive discounts, and swag steals. |
| **W09** | Contributor Day Quests | `/{slug}/contribute` (`attendee.contribute`) | `resources/views/attendee/contribute.blade.php` | Contributor table roadmap: Team tables (Core, Polyglots, Docs, Accessibility), onboarding checklists, XP progression badges. |
| **W10** | Venue & Safety Hub | `/{slug}/guide` (`attendee.guide`) | `resources/views/attendee/guide.blade.php` | Interactive indoor venue map: Accessible pathways, quiet rooms, Code of Conduct guidelines, and 1-tap incident report form. |
| **W11** | Post-Event Memories & Slides | `/{slug}/memories` (`attendee.memories`) | `resources/views/attendee/memories.blade.php` | Archival slide repository: Downloadable speaker decks, community photos, personal recap badge with social share card. |

---

### Page 2: Standalone PWA (Mobile Offline App)
*Layout: `390×844 Mobile Standalone Frame` · Technology: ServiceWorker, CacheStorage, IndexedDB*

| Screen ID | Screen Name | Implementation Mechanism | Flow Role & Functional Description |
| :--- | :--- | :--- | :--- |
| **P01** | Install & Cache Hero | `public/sw.js` & `resources/js/offline-warmup.js` | PWA installation gate: ServiceWorker asset download progress bar, offline storage priming confirmation, "Add to Home Screen" prompt. |
| **P02** | Standalone Tactile Home | `resources/views/attendee/home.blade.php` | Mobile home command screen: Live session countdown timer, tactile ActionGrid, offline cache status indicator (`IndexedDB Ready`). |
| **P03** | Offline Program & Rooms | `resources/js/db.js` | Offline schedule explorer: Rapid room toggle (Hall A vs Hall B), local cached speaker headshots, instant star bookmarking. |
| **P04** | Conflict Resolution | `resources/js/my-day.js` | Overlapping talk resolver: Side-by-side comparison modal with 1-tap resolution to keep chosen session and un-star conflicting talk. |
| **P05** | Offline CampCard Badge | `resources/js/camp-card-paint.js` | High-contrast attendee QR badge: Brightness boost toggle, encrypted offline vCard payload, zero-network scanning readiness. |
| **P06** | Camera QR Scanner Modal | `resources/js/qr-kit.js` | Mobile camera scanner: Web MediaDevices viewfinder, real-time Shape Detection API QR decoding, 1-tap save to local address book. |
| **P07** | Offline Attendee Roster | `resources/js/people-state.js` | IndexedDB cached attendee directory: Instant fuzzy search by name or interest tag, offline role badges, local personal contact notes. |
| **P08** | Contributor Day Checklist | `resources/js/quest.js` | Interactive contributor table guide: LocalStorage-persisted onboarding tasks, GitHub setup instructions, mentor call button. |
| **P09** | Venue Map & Emergency SOS | `resources/views/attendee/guide.blade.php` | Offline vector map & safety hotline: Zoomable floorplan SVG, emergency safety hotline button, and direct volunteer call link. |

---

### Page 3: Native Android App (Compose Multiplatform APK)
*Layout: `390×844 Mobile Frame` · Technology: Kotlin 2.1, Compose Multiplatform, SQLite, MLKit*

| Screen ID | Screen Name | Kotlin Composable Source | Flow Role & Functional Description |
| :--- | :--- | :--- | :--- |
| **A01** | Native Splash & Onboarding | `club.campbuddy.shared.ui.Onboarding.kt` | First-launch screen: Warm brand palette, SQLite database bootstrap, optional notification permissions prompt for 10m talk alerts. |
| **A02** | Event Picker & Transfer | `club.campbuddy.shared.ui.PickerScreen.kt` | Conference selector: Country filter chips, conference card list, 6-digit sync code input to import desktop bookmarks. |
| **A03** | Tactile Home Dashboard | `club.campbuddy.shared.ui.EventScreens.kt` | Native home screen: 30-second live ticking countdown hero card, speaker thumbnail, 2×3 tactile navigation action grid. |
| **A04** | Session Detail View | `club.campbuddy.shared.ui.EventScreens.kt` | Comprehensive talk breakdown: Full speaker biography, social handles, room venue pin, and native notification schedule toggle. |
| **A05** | My Day Itinerary | `club.campbuddy.shared.ui.MyDay.kt` | Personalized schedule timeline: Room walk-time calculations, attendance checkmarks for XP, schedule clash alternative suggestions. |
| **A06** | CampCard Native View | `club.campbuddy.shared.ui.CampCardScreen.kt` | On-device digital badge: DPDP 2023 certified zero-cloud storage, vCard 3.0 QR code, native Google Wallet pass integration. |
| **A07** | Native MLKit Scanner | `club.campbuddy.shared.ui.QrCode.kt` | Ultra-fast barcode reader: CameraX + Google MLKit barcode detection (<50ms detection), haptic confirmation, auto-insert into SQLite. |
| **A08** | Explore Directory | `club.campbuddy.shared.ui.People.kt` | Integrated conference explorer: Tabbed attendee directory, speaker list, scraped sponsor grid, debounced Kotlin Flow search. |
| **A09** | Quests & XP Rewards | `club.campbuddy.shared.ui.MoreTabs.kt` | Gamification center: Conference achievement quests ("Visit 3 sponsors", "Submit 1 patch"), XP progress bar, unlockable badges. |
| **A10** | Safety & Incident Dialog | `club.campbuddy.shared.ui.LegalSheet.kt` | Dedicated Code of Conduct safety dialog: 1-tap dialer to Head Organizer phone, confidential incident report with optional photo attachment. |

---

### Page 4: Admin & Staff Panel
*Layout: `1280×800 Desktop` & `390×844 Mobile` · Technology: Laravel Admin/Manager Controllers, WebSocket SSE*

| Screen ID | Screen Name | Route / Path | Controller & View | Flow Role & Functional Description |
| :--- | :--- | :--- | :--- | :--- |
| **AP01** | Desktop Control Center | `/admin` (`admin.dashboard`) | `Admin\DashboardController` (`admin/events/index.blade.php`) | Master administrative hub: Active attendee counts, room check-in rates, scraper health status, broadcast announcement bar. |
| **AP02** | Live Delay Broadcaster | `/admin/events/{id}/delay` | `Admin\EventController@delay` (`admin/events/delay.blade.php`) | Room delay management console: Incremental delay controls (+5m, +10m, +15m) with automatic cascade across all client schedules. |
| **AP03** | Push Announcement Dispatcher | `/admin/events/{id}/notifications` | `Admin\EventController@notifications` (`admin/events/notifications.blade.php`) | Multi-channel broadcast composer: Severity selector (Info, Schedule, Urgent), target segment filter (All, Speakers, Staff), live preview. |
| **AP04** | Sponsors & Media Health | `/admin/events/{id}/media` | `Admin\MediaController` (`admin/media/index.blade.php`) | Media audit console: Scraper verification status (Delhi 17/17, Athens 30/30), broken URL detector, 1-click media pre-warming tool. |
| **AP05** | Attendee Roles Manager | `/admin/events/{id}/roster` | `Admin\RosterController` (`admin/roster/index.blade.php`) | Role administration matrix: SuperAdmin, EventLead, StaffVolunteer, Speaker permission toggles, CSV roster import, QR staff pairing. |
| **AP06** | Contributor Table Config | `/admin/events/{id}/tables` | `Admin\ContributorTableController` (`admin/events/tables.blade.php`) | Table setup editor: Contributor table configuration (Core, Polyglots, Docs), table lead assignments, custom quest XP editor. |
| **AP07** | Staff Mobile Check-In | `/admin/mobile/check-in` | `Admin\MobileController@checkIn` (`admin/mobile/index.blade.php`) | High-throughput registration console: Camera barcode scanner, attendee verification badge, t-shirt size display, offline sync queue. |
| **AP08** | Mobile Incident Dispatch | `/admin/mobile/incident-dispatch` | `Admin\MobileController@incidents` (`admin/mobile/index.blade.php`) | Safety marshal response console: Real-time incident notification feed, room locator pin, status toggles (Assigned, In Progress, Resolved). |

--------------------------------------------------------------------------------

## Alternatives Considered

### Alternative A: Centralized Cloud Contact Storage
*   **Pros**: Enables cross-device sync of scanned contacts without physical QR scanning.
*   **Cons**: Introduces high data liability, requires mandatory GDPR/DPDP user registration, creates server-side storage overhead, and fails completely under venue network outages.
*   **Reason for Rejection**: `Rejected by author: Violates core CampBuddy zero-retention privacy principles and fails during Wi-Fi blackouts.`

### Alternative B: Single Monolithic Native App Store Release Only (No Web/PWA)
*   **Pros**: Reduces build matrix to native Kotlin Compose Multiplatform only.
*   **Cons**: Excludes attendees who do not wish to download an APK or lack Google Play/F-Droid access; prevents instant sharing of event links via desktop browsers.
*   **Reason for Rejection**: `Rejected by author: WordCamp attendees require instant browser access without download gates.`

### Alternative C: Retaining Legacy 7-Tab Bottom Navigation Bar on Mobile
*   **Pros**: Maintains visual layout familiarity for legacy app users.
*   **Cons**: Creates cognitive clutter and menu hunting in crowded physical hallways; increases drop-off during keynote-to-track room changes.
*   **Reason for Rejection**: `Rejected by author: Attendees walking through physical venue corridors have at most 2–3 seconds to make a navigation decision. The multi-tab bottom bar creates decision paralysis.`

--------------------------------------------------------------------------------

## Non-Functional Requirements

*   **Security & Privacy**: Zero server retention of scanned peer contacts. DPDP Act 2023 certified. vCard 3.0 encrypted on-device payloads. Strict Content-Security-Policy with zero inline scripts in user content.
*   **Performance & Latency**: Sub-100ms UI response time for schedule lookups. 30-second background polling cadence for live room delays. Sub-50ms barcode detection via MLKit.
*   **Offline Resilience**: 100% of schedule, speaker, sponsor, and map data pre-cached into SQLite (APK) and IndexedDB (PWA). Full operation in airplane mode with graceful sync upon reconnect.

--------------------------------------------------------------------------------

## Detailed Implementation Plan

### Target Files to Maintain & Verify
1. `docs/designs/system_architecture_and_screen_catalog.md` (Created): Master technical specification containing complete architectural breakdown, feature traceability matrix, and screen inventory.
2. `tools/build_figcraft_multipage.mjs`: Script compiling the 4-page FigCraft master file (`campbuddy_all_screens.figcraft`).
3. `tools/generate_specimen_html.mjs`: Automated documentation generator compiling `figcraft_design_system_specimen.html` and `docs/designs/specimen.html`.
4. `native-app/shared/src/commonMain/kotlin/club/campbuddy/shared/ui/EventScreens.kt`: Compose Multiplatform UI components dispatching tactile dashboard cards.
5. `app/Support/ScheduledTasks.php`: Scheduled cron task definitions governing daily and hourly WordCamp scrapers.
6. `app/Services/WordCamp/WordCampRestClient.php`: Scraper client providing resilient DOM fallback fetching for sponsor logos and speaker photos.

### Verification Plan
*   **FigCraft CLI Verification**: `figcraft-cli info docs/designs/campbuddy_all_screens.figcraft` confirming 4 pages and 38 screens.
*   **JavaScript Test Suite**: `npm run test:js` (431 tests passing).
*   **PHP Test Suite**: `php artisan test` (1,181 tests passing).
*   **Asset Bundler Verification**: `npm run build` compiling client bundle with zero errors.

--------------------------------------------------------------------------------

*Drafted with EGM*
