# Stage 7: Critic Review Report

**Target Document**: `docs/designs/system_architecture_and_screen_catalog.md`  
**Scope Analyzed**: Feature Range (Lines 1–265)  
**Review Personas**:
- System Architecture Critic (`egm-design`)
- Structural Layout Validator (`egm-structural`)
- Technical Copy Editor (`egm-style`)

---

### Executive Summary
The Stage 7 Critic Review evaluated the system architecture, core user journeys, 26 features, and 38-screen catalog across all four client tiers. The multi-angle audit identified **7 Blockers**, **12 Majors**, and **9 Nits**. The findings demonstrate critical gaps between the conceptual blueprint and technical reality—including hallucinated database engines (SQLite in KMP), impossible offline asymmetric cryptographic signings (Apple/Google Wallet), protocol contradictions ("WebSocket SSE" vs HTTP polling), screen catalog schema omissions (missing `UI Elements` and `Offline Behavior` across all 38 screens), and ungrounded regulatory certifications.

---

### Findings

#### 🔴 Blocker

1. **Fictitious Native SQLite Architecture & High-Risk KMP Memory Contention**
   - **Reviewer**: System Architecture Critic (`egm-design`)
   - **Affected Scope**: [docs/designs/system_architecture_and_screen_catalog.md#L14](file:///e:/Sites/InHouse/campbuddy/V2/docs/designs/system_architecture_and_screen_catalog.md#L14), [L29](file:///e:/Sites/InHouse/campbuddy/V2/docs/designs/system_architecture_and_screen_catalog.md#L29), [L39](file:///e:/Sites/InHouse/campbuddy/V2/docs/designs/system_architecture_and_screen_catalog.md#L39), [L69](file:///e:/Sites/InHouse/campbuddy/V2/docs/designs/system_architecture_and_screen_catalog.md#L69), [L125](file:///e:/Sites/InHouse/campbuddy/V2/docs/designs/system_architecture_and_screen_catalog.md#L125), [L206](file:///e:/Sites/InHouse/campbuddy/V2/docs/designs/system_architecture_and_screen_catalog.md#L206), [L210](file:///e:/Sites/InHouse/campbuddy/V2/docs/designs/system_architecture_and_screen_catalog.md#L210), [L216](file:///e:/Sites/InHouse/campbuddy/V2/docs/designs/system_architecture_and_screen_catalog.md#L216), [L262](file:///e:/Sites/InHouse/campbuddy/V2/docs/designs/system_architecture_and_screen_catalog.md#L262)
   - **Discrepancy Description & Rationale**: The document repeatedly asserts that the Android Compose Multiplatform APK uses "on-device SQLite", "native SQLite synchronization", "SQLite database bootstrap", and "auto-insert into SQLite". In actual codebase reality, `native-app/shared/build.gradle.kts` contains **zero** SQL dependencies (no SQLDelight, Room, or SQLite driver). `Repository.kt` implements storage via a primitive `KeyValueStore` backed by `SharedPreferences` (Android XML) and `NSUserDefaults` (iOS plist), serializing multi-megabyte JSON blobs (`roster:$slug`, `sessions:$slug`) into flat preference keys. Synchronously deserializing monolithic JSON strings on startup loads entire trees into the application heap, triggering frame drops, GC spikes, and ANRs during large conferences (1,500+ attendees). The specification must accurately reflect `KeyValueStore` and define heap/cache paging boundaries rather than hallucinating an indexed SQLite database.

2. **Ingestion Race Condition & Schedule Drift: Hourly Scrapers vs. Manual Room Delays**
   - **Reviewer**: System Architecture Critic (`egm-design`)
   - **Affected Scope**: [docs/designs/system_architecture_and_screen_catalog.md#L72-L75](file:///e:/Sites/InHouse/campbuddy/V2/docs/designs/system_architecture_and_screen_catalog.md#L72-L75), [L120-L121](file:///e:/Sites/InHouse/campbuddy/V2/docs/designs/system_architecture_and_screen_catalog.md#L120-L121), [L155](file:///e:/Sites/InHouse/campbuddy/V2/docs/designs/system_architecture_and_screen_catalog.md#L155), [L163](file:///e:/Sites/InHouse/campbuddy/V2/docs/designs/system_architecture_and_screen_catalog.md#L163), [L229](file:///e:/Sites/InHouse/campbuddy/V2/docs/designs/system_architecture_and_screen_catalog.md#L229)
   - **Discrepancy Description & Rationale**: Automated hourly ingestion crons (`event-day-hourly-refresh` via `FetchSpeakersSponsorsSessionsJob`) pull from upstream WordCamp REST APIs while organizers apply manual room delay offsets (+15m cascade via `ScheduleDelay::set`). If organizers adjust session times on the upstream WordCamp.org portal, the hourly scraper ingests the updated start time as baseline `starts_at`. `ScheduleDelay::apply` then compounds the manual +15m delay on top of the already-delayed upstream time, creating a double-delayed (+30m) phantom schedule. The architecture document lacks any conflict resolution, upstream change detection, or tombstone protocol to prevent compounding schedule drift.

3. **Cryptographic Impossibility: Offline Apple/Google Wallet Generation & "Encrypted" vCard Paradox**
   - **Reviewers**: System Architecture Critic (`egm-design`), Technical Copy Editor (`egm-style`)
   - **Affected Scope**: [docs/designs/system_architecture_and_screen_catalog.md#L37](file:///e:/Sites/InHouse/campbuddy/V2/docs/designs/system_architecture_and_screen_catalog.md#L37), [L128](file:///e:/Sites/InHouse/campbuddy/V2/docs/designs/system_architecture_and_screen_catalog.md#L128), [L179](file:///e:/Sites/InHouse/campbuddy/V2/docs/designs/system_architecture_and_screen_catalog.md#L179), [L197](file:///e:/Sites/InHouse/campbuddy/V2/docs/designs/system_architecture_and_screen_catalog.md#L197), [L215](file:///e:/Sites/InHouse/campbuddy/V2/docs/designs/system_architecture_and_screen_catalog.md#L215), [L260](file:///e:/Sites/InHouse/campbuddy/V2/docs/designs/system_architecture_and_screen_catalog.md#L260)
   - **Discrepancy Description & Rationale**:
     - *Wallet Passes*: Generating Apple Wallet (`.pkpass`) or Google Wallet passes requires signing artifacts with asymmetric private keys (PKCS#7 or Google Service Account JWT). Generating passes locally offline would require baking private developer signing keys into public client builds. If done server-side, it violates the offline-first mandate.
     - *vCard Paradox*: The document claims badges use "vCard 3.0 encrypted QR codes" while asserting they are "readable by standard camera scanners". Standard smartphone cameras cannot decrypt proprietary ciphertext without shared keys. In the actual codebase (`camp-card.js` and `CampCardScreen.kt`), QR codes encode standard direct URLs (e.g. LinkedIn, personal website) or standard plaintext vCard strings.

4. **Screen Catalog Schema Defect: Omission of UI Elements and Offline Behavior Across All 38 Screens**
   - **Reviewer**: Structural Layout Validator (`egm-structural`)
   - **Affected Scope**: [docs/designs/system_architecture_and_screen_catalog.md#L167-L236](file:///e:/Sites/InHouse/campbuddy/V2/docs/designs/system_architecture_and_screen_catalog.md#L167-L236)
   - **Discrepancy Description & Rationale**: The required Screen Catalog blueprint mandates a complete 7-attribute schema: `Screen ID`, `Screen Name`, `Route / Path`, `Tech / Template`, `Description`, `UI Elements`, and `Offline Behavior`. Across all 38 screen records in the four platform catalogs (Web, PWA, APK, Admin), the `UI Elements` and `Offline Behavior` attributes are completely omitted from the schema. In an offline-first system governed by Goal G-5 ("Total Offline Autonomy"), omitting explicit per-screen offline behavior (IndexedDB, local storage, CacheStorage, or queued sync) and UI element breakdowns prevents engineering audit and UI verification.

5. **Structural Column Asymmetry Across Platform Screen Catalogs**
   - **Reviewer**: Structural Layout Validator (`egm-structural`)
   - **Affected Scope**: [docs/designs/system_architecture_and_screen_catalog.md#L172-L236](file:///e:/Sites/InHouse/campbuddy/V2/docs/designs/system_architecture_and_screen_catalog.md#L172-L236)
   - **Discrepancy Description & Rationale**: The four screen catalog tables violate structural symmetry. Table 1 (Website Frontend) and Table 4 (Admin Panel) define 5 columns, whereas Table 2 (PWA) and Table 3 (Native Android) drop to 4 columns. Headers mutate arbitrarily: Table 1 defines `Route / Path` and `Primary Blade View`; Table 2 replaces them with `Implementation Mechanism`; Table 3 uses `Kotlin Composable Source`; Table 4 uses `Controller & View`. This prevents uniform tabular parsing and multi-platform alignment.

6. **Ungrounded Statutory "DPDP Act 2023 Certified" Compliance Claim**
   - **Reviewer**: Technical Copy Editor (`egm-style`)
   - **Affected Scope**: [docs/designs/system_architecture_and_screen_catalog.md#L215](file:///e:/Sites/InHouse/campbuddy/V2/docs/designs/system_architecture_and_screen_catalog.md#L215), [L260](file:///e:/Sites/InHouse/campbuddy/V2/docs/designs/system_architecture_and_screen_catalog.md#L260)
   - **Discrepancy Description & Rationale**: The document claims that CampCard storage is "DPDP 2023 certified" and "DPDP Act 2023 certified." India's Digital Personal Data Protection Act, 2023 is a statutory legal regulation; it does not define or provide any technical software certification program or accredited certification body. Claiming statutory compliance as a technical certification creates legal misrepresentation. Must be calibrated to "Architected for DPDP Act 2023 and GDPR alignment via local on-device retention and zero-cloud processing."

7. **Incoherent & Contradictory Real-Time Protocol Specification ("WebSocket SSE" vs Polling)**
   - **Reviewers**: Technical Copy Editor (`egm-style`), System Architecture Critic (`egm-design`)
   - **Affected Scope**: [docs/designs/system_architecture_and_screen_catalog.md#L105](file:///e:/Sites/InHouse/campbuddy/V2/docs/designs/system_architecture_and_screen_catalog.md#L105), [L224](file:///e:/Sites/InHouse/campbuddy/V2/docs/designs/system_architecture_and_screen_catalog.md#L224), [L261](file:///e:/Sites/InHouse/campbuddy/V2/docs/designs/system_architecture_and_screen_catalog.md#L261)
   - **Discrepancy Description & Rationale**: Line 224 specifies `Technology: Laravel Admin/Manager Controllers, WebSocket SSE`. "WebSocket SSE" combines two mutually exclusive transports (WebSockets and Server-Sent Events). Line 105 diagrams `[Admin WebSockets]`, yet Line 261 explicitly defines a `30-second background polling cadence for live room delays`. The document must disambiguate: specify SSE or WebSockets for Admin, and HTTP polling for mobile/PWA.

---

#### 🟡 Major

8. **Phantom Admin Subsystems & Fabricated Operations Modules (AP07, AP08, TransferCrypto)**
   - **Reviewer**: System Architecture Critic (`egm-design`)
   - **Affected Scope**: [docs/designs/system_architecture_and_screen_catalog.md#L100](file:///e:/Sites/InHouse/campbuddy/V2/docs/designs/system_architecture_and_screen_catalog.md#L100), [L129](file:///e:/Sites/InHouse/campbuddy/V2/docs/designs/system_architecture_and_screen_catalog.md#L129), [L234-L235](file:///e:/Sites/InHouse/campbuddy/V2/docs/designs/system_architecture_and_screen_catalog.md#L234-L235)
   - **Discrepancy Description & Rationale**: AP07 (Staff Mobile Check-In) and AP08 (Mobile Incident Dispatch) claim routes and views under `Admin\MobileController` and `admin/mobile/index.blade.php`. In reality, `MobileAppController.php` exists solely to edit JSON UI configs (tabs, brand colors) and host APK binary uploads. `TransferCrypto.php` does not exist (crypto is in JavaScript WebCrypto / `DeviceTransferController.php`). These speculative operational features must be de-scoped or formally specified as new roadmap modules.

9. **Architectural Protocol Misattribution & Corridor Battery Depletion**
   - **Reviewers**: System Architecture Critic (`egm-design`), Technical Copy Editor (`egm-style`)
   - **Affected Scope**: [docs/designs/system_architecture_and_screen_catalog.md#L74](file:///e:/Sites/InHouse/campbuddy/V2/docs/designs/system_architecture_and_screen_catalog.md#L74), [L105](file:///e:/Sites/InHouse/campbuddy/V2/docs/designs/system_architecture_and_screen_catalog.md#L105), [L261](file:///e:/Sites/InHouse/campbuddy/V2/docs/designs/system_architecture_and_screen_catalog.md#L261)
   - **Discrepancy Description & Rationale**: 30s background short-polling across 1,000 attendees creates ~33.3 continuous QPS on Laravel over congested venue links, compounding bandwidth starvation and preventing mobile OS battery sleep states (Doze). Diagram edges also mislabel client libraries as network protocols (`[Blade / SSR API]`, `[Ktor REST API]`). Must use standard protocol names (`[HTTP / SSR HTML]`, `[REST JSON API]`).

10. **Service Worker 4-Second Network Timeout Breaches 2-Second Glanceability SLA (G-2)**
    - **Reviewer**: System Architecture Critic (`egm-design`)
    - **Affected Scope**: [docs/designs/system_architecture_and_screen_catalog.md#L36](file:///e:/Sites/InHouse/campbuddy/V2/docs/designs/system_architecture_and_screen_catalog.md#L36), [L123](file:///e:/Sites/InHouse/campbuddy/V2/docs/designs/system_architecture_and_screen_catalog.md#L123)
    - **Discrepancy Description & Rationale**: Goal G-2 mandates cold start under 2 seconds. In `public/sw.js` (line 70), `NETWORK_TIMEOUT_MS = 4000` (4 seconds) for its network-first navigation strategy. In congested corridors, requests stall until 4,000ms elapses before serving cached HTML, violating the SLA. Must adopt stale-while-revalidate with sub-500ms timeout or cache-first navigation.

11. **Fictitious IndexedDB Manifest Architecture in PWA Tier**
    - **Reviewer**: System Architecture Critic (`egm-design`)
    - **Affected Scope**: [docs/designs/system_architecture_and_screen_catalog.md#L124](file:///e:/Sites/InHouse/campbuddy/V2/docs/designs/system_architecture_and_screen_catalog.md#L124), [L195](file:///e:/Sites/InHouse/campbuddy/V2/docs/designs/system_architecture_and_screen_catalog.md#L195), [L199](file:///e:/Sites/InHouse/campbuddy/V2/docs/designs/system_architecture_and_screen_catalog.md#L199), [L262](file:///e:/Sites/InHouse/campbuddy/V2/docs/designs/system_architecture_and_screen_catalog.md#L262)
    - **Discrepancy Description & Rationale**: The document specifies that `db.js` caches "full schedule manifests, speaker rosters, sponsor lists" into IndexedDB. In reality, `resources/js/attendee/db.js` only provisions stores for `kv`, `bookmarks`, `questProgress`, `metHistory`, and `meetings`. Schedules and rosters are stored as pre-rendered server HTML pages inside the Service Worker's `CacheStorage` (`campbuddy-v3`). The document confuses browser CacheStorage with IndexedDB.

12. **Unmitigated Offline Gate Check-In Vulnerability (Replay & Double-Spend)**
    - **Reviewer**: System Architecture Critic (`egm-design`)
    - **Affected Scope**: [docs/designs/system_architecture_and_screen_catalog.md#L160](file:///e:/Sites/InHouse/campbuddy/V2/docs/designs/system_architecture_and_screen_catalog.md#L160), [L234](file:///e:/Sites/InHouse/campbuddy/V2/docs/designs/system_architecture_and_screen_catalog.md#L234)
    - **Discrepancy Description & Rationale**: Operating an asynchronous, offline check-in queue across multiple disconnected volunteer gates has no protection against duplicate redemptions. Attendees can share QR code screenshots across Gates 1, 2, and 3 simultaneously, redeeming multiple physical badges and t-shirts before reconciliation occurs.

13. **Missing Route and Navigation Destination Schema in PWA and Native Android Catalogs**
    - **Reviewer**: Structural Layout Validator (`egm-structural`)
    - **Affected Scope**: [docs/designs/system_architecture_and_screen_catalog.md#L188-L220](file:///e:/Sites/InHouse/campbuddy/V2/docs/designs/system_architecture_and_screen_catalog.md#L188-L220)
    - **Discrepancy Description & Rationale**: Table 1 and Table 4 provide explicit URL routes, but Table 2 (PWA) and Table 3 (Native Android APK) omit routing entirely. PWA cached URL scopes and Compose Multiplatform navigation destinations (e.g., `Destination.Onboarding`, `Destination.Home`) must be documented.

14. **Absence of Feature-to-Screen Cross-Referencing & Missing Feature Matrix Structure**
    - **Reviewer**: Structural Layout Validator (`egm-structural`)
    - **Affected Scope**: [docs/designs/system_architecture_and_screen_catalog.md#L133-L164](file:///e:/Sites/InHouse/campbuddy/V2/docs/designs/system_architecture_and_screen_catalog.md#L133-L164), [L167-L236](file:///e:/Sites/InHouse/campbuddy/V2/docs/designs/system_architecture_and_screen_catalog.md#L167-L236)
    - **Discrepancy Description & Rationale**: The document H1 promises a "Feature Matrix", yet Section 133 is an unmapped numbered inventory. There is zero bidirectional cross-referencing between Features 1–26 and Screen IDs (W01–W11, P01–P09, A01–A10, AP01–AP08), CUJs, or Goals. Features lack classification metadata (Platform Scope, Auth Tier, Data Source, Offline Support).

15. **Inconsistent Codebase Path Conventions Across Catalog Tables**
    - **Reviewer**: Structural Layout Validator (`egm-structural`)
    - **Affected Scope**: [docs/designs/system_architecture_and_screen_catalog.md#L172-L236](file:///e:/Sites/InHouse/campbuddy/V2/docs/designs/system_architecture_and_screen_catalog.md#L172-L236)
    - **Discrepancy Description & Rationale**: Table 1 uses repo-relative paths (`resources/views/welcome.blade.php`); Table 2 mixes scripts and views (`public/sw.js & resources/js/...`); Table 3 uses JVM package-dot syntax (`club.campbuddy.shared.ui.Onboarding.kt`); Table 4 truncates Blade views (`admin/events/index.blade.php`). Unified repository-relative path syntax is required.

16. **Total Absence of Markdown File Hyperlinks Throughout Target Scope**
    - **Reviewer**: Structural Layout Validator (`egm-structural`)
    - **Affected Scope**: [docs/designs/system_architecture_and_screen_catalog.md#L114-L130](file:///e:/Sites/InHouse/campbuddy/V2/docs/designs/system_architecture_and_screen_catalog.md#L114-L130), [L167-L236](file:///e:/Sites/InHouse/campbuddy/V2/docs/designs/system_architecture_and_screen_catalog.md#L167-L236)
    - **Discrepancy Description & Rationale**: Referenced codebase files are formatted strictly as inert inline code literals (`code`) rather than clickable Markdown links, impairing traceability and navigating efficiency.

17. **Frontend Reactive Framework Contradiction (Vue/Vite vs Alpine.js / Vanilla JS)**
    - **Reviewer**: Technical Copy Editor (`egm-style`)
    - **Affected Scope**: [docs/designs/system_architecture_and_screen_catalog.md#L17](file:///e:/Sites/InHouse/campbuddy/V2/docs/designs/system_architecture_and_screen_catalog.md#L17), [L170](file:///e:/Sites/InHouse/campbuddy/V2/docs/designs/system_architecture_and_screen_catalog.md#L170)
    - **Discrepancy Description & Rationale**: Line 17 introduces the codebase as utilizing "Vue/Vite frontend utilities", whereas Line 170 specifies `Laravel Blade, Tailwind CSS, Alpine.js, Vite`. In actual attendee templates, vanilla JavaScript with BEM styling is used without Vue or Alpine. Clarify exact frontend technology per tier.

18. **Structural Taxonomy Mislabeling: Platform Tiers Designated as "Page"**
    - **Reviewer**: Technical Copy Editor (`egm-style`)
    - **Affected Scope**: [docs/designs/system_architecture_and_screen_catalog.md#L169](file:///e:/Sites/InHouse/campbuddy/V2/docs/designs/system_architecture_and_screen_catalog.md#L169), [L188](file:///e:/Sites/InHouse/campbuddy/V2/docs/designs/system_architecture_and_screen_catalog.md#L188), [L205](file:///e:/Sites/InHouse/campbuddy/V2/docs/designs/system_architecture_and_screen_catalog.md#L205), [L223](file:///e:/Sites/InHouse/campbuddy/V2/docs/designs/system_architecture_and_screen_catalog.md#L223)
    - **Discrepancy Description & Rationale**: The sections are titled `Page 1: Website Frontend`, `Page 2: Standalone PWA`, `Page 3: Native Android App`, and `Page 4: Admin & Staff Panel`, which leaks internal FigCraft multi-page canvas numbering into architectural tiers. Must be renamed to `Platform Tier 1: Website Frontend`, etc.

19. **Hyperbolic Adjectives & Uncalibrated Verification Guarantees**
    - **Reviewer**: Technical Copy Editor (`egm-style`)
    - **Affected Scope**: [docs/designs/system_architecture_and_screen_catalog.md#L35](file:///e:/Sites/InHouse/campbuddy/V2/docs/designs/system_architecture_and_screen_catalog.md#L35), [L39](file:///e:/Sites/InHouse/campbuddy/V2/docs/designs/system_architecture_and_screen_catalog.md#L39), [L216](file:///e:/Sites/InHouse/campbuddy/V2/docs/designs/system_architecture_and_screen_catalog.md#L216)
    - **Discrepancy Description & Rationale**: Unsubstantiated guarantees such as "100% Real Data Ingestion & Media Integrity", DOM scrapers "guarantee verified high-resolution sponsor logos", and marketing buzzwords ("Ultra-fast barcode reader", "Total Offline Autonomy") must be calibrated to objective engineering statements.

---

#### 🔵 Nit

20. **Repetitive Buzzword Usage ("Tactile") for Touchscreen Interfaces**
    - **Affected Scope**: Lines 138, 176, 194, 212. Replace "Tactile Live Event Dashboard" with "Touch-Optimized Live Event Dashboard".
21. **Colloquial Marketing Phrasing in Feature Titles**
    - **Affected Scope**: Lines 146, 160, 174, 181. Replace "Swag Steals Wall" with "Sponsor Perks & Giveaways Directory", and "Fast-Flow Volunteer Check-In" with "High-Throughput Volunteer Check-In".
22. **Colloquial Phrasing and Mixed Metaphors in Problem Statement**
    - **Affected Scope**: Lines 24–25. Replace "regularly choke" with "regularly experience severe bandwidth saturation", and "high attendee drop-off in physical hallways" with "attendee disorientation and missed sessions".
23. **Git/Issue-Tracker Metadata Left in Alternatives Considered**
    - **Affected Scope**: Lines 244, 249, 254. Remove `Rejected by author:` and state reasons directly.
24. **Informal Truncated Abbreviation in Architecture Diagram**
    - **Affected Scope**: Line 109. Replace `(Compose Multiplat)` with `(Compose Multiplatform / KeyValueStore)`.
25. **Non-Standardized Feature Numbering Identifiers**
    - **Affected Scope**: Lines 135–164. Adopt standardized tokens (`F-01` through `F-26`) matching screen ID syntax.
26. **Embedded Inline Code Syntax in Section H4 Heading**
    - **Affected Scope**: Line 116. Remove inline backticks and file paths from the H4 heading string.
27. **Infeasible Browser Multicast DNS (mDNS) Discovery Consideration**
    - **Affected Scope**: Line 54. Web browser sandboxes do not expose raw UDP multicast/mDNS sockets to JavaScript.
28. **Routing URI and Controller Namespace Drift**
    - **Affected Scope**: Lines 176–184, 228–232. Align screen routes with Laravel routes (`/event/{event:slug}/...` and controllers `ScheduleDelayController`, `NotificationsController`, `MediaController`).
