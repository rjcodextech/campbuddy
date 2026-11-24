# Stage 6: Comprehension Test Report

**Target Document**: `docs/designs/system_architecture_and_screen_catalog.md`  
**Reviewer Persona**: Naive Reader (`egm-comprehension`)  
**Scope Analyzed**: Feature Range (Lines 1–265) & Detailed Range (Lines 266–284)

---

### What this proposes
CampBuddy proposes a multi-platform event companion system for WordPress WordCamp conferences spanning four client runtime tiers: a Blade/Vite responsive website, an offline Progressive Web App (PWA), a native Kotlin Compose Multiplatform Android app, and an administrative/staff command panel. The architecture prioritizes offline autonomy in congested conference halls via local on-device caching (SQLite and IndexedDB), peer-to-peer contact exchanges via vCard 3.0 QR codes without centralized contact storage, and organizer room-delay broadcasting. Additionally, the document establishes an inventory of 26 system features and catalogs 38 production screens across all platforms.

### How the existing system works, per this document
The backend is built on Laravel 12 and PHP 8.3 with 327 registered routes, providing scheduled daily and hourly cron workers that ingest conference data from WordCamp.org REST feeds with DOM-scraping fallbacks and local media proxying. Client platforms consume this data and persist it locally using IndexedDB for web/PWA clients and SQLite for Android, enabling offline navigation, bookmarking, and itinerary conflict resolution. Administrative staff manage event delays and volunteer check-ins through dedicated controllers, with schedule updates cascaded to attendees via polling or push channels.

---

### Findings

#### 🔴 Blocker

1. **Undefined End-to-End Cryptographic Protocol for 6-Digit Desktop-to-Mobile Transfer**
   - **Affected Scope**: [docs/designs/system_architecture_and_screen_catalog.md#L54](file:///e:/Sites/InHouse/campbuddy/V2/docs/designs/system_architecture_and_screen_catalog.md#L54), [L129](file:///e:/Sites/InHouse/campbuddy/V2/docs/designs/system_architecture_and_screen_catalog.md#L129), [L137](file:///e:/Sites/InHouse/campbuddy/V2/docs/designs/system_architecture_and_screen_catalog.md#L137), [L211](file:///e:/Sites/InHouse/campbuddy/V2/docs/designs/system_architecture_and_screen_catalog.md#L211) (Open Question 1, Subsystem C, Feature 2, Screen A02)
   - **Discrepancy Description & Rationale**: The document states that bookmarked itineraries and custom tags are transferred from desktop browsers to mobile devices via an "end-to-end encrypted 6-digit key exchange" using `TransferCrypto.php` and `move-data.js`. A 6-digit numeric PIN provides only $10^6$ combinations (~20 bits of entropy), making direct symmetric key derivation trivial to brute-force without an authenticated key exchange (e.g. SPAKE2 / PAKE). The specification fails to define:
     - Where the ciphertext payload is staged (in-memory Redis, database table, or ephemeral WebSocket channel).
     - How the server authenticates the payload without gaining decryption capability or violating the zero-server storage mandate.
     - Rate-limiting, brute-force mitigation, and time-to-live (TTL) expiration mechanics.
     - Why this is called "P2P" when it relies on a central Laravel backend service (`TransferCrypto.php`), and how it operates when conference Wi-Fi is disconnected (as acknowledged in Open Question 1 regarding mDNS fallbacks).

2. **Unresolved Conflict Between Hourly Ingestion Scrapers and Manual Room Delay Offsets**
   - **Affected Scope**: [docs/designs/system_architecture_and_screen_catalog.md#L72-L75](file:///e:/Sites/InHouse/campbuddy/V2/docs/designs/system_architecture_and_screen_catalog.md#L72-L75), [L120](file:///e:/Sites/InHouse/campbuddy/V2/docs/designs/system_architecture_and_screen_catalog.md#L120), [L155](file:///e:/Sites/InHouse/campbuddy/V2/docs/designs/system_architecture_and_screen_catalog.md#L155), [L163](file:///e:/Sites/InHouse/campbuddy/V2/docs/designs/system_architecture_and_screen_catalog.md#L163), [L229](file:///e:/Sites/InHouse/campbuddy/V2/docs/designs/system_architecture_and_screen_catalog.md#L229) (CUJ 3, ScheduledTasks, Feature 18, Feature 26, Screen AP02)
   - **Discrepancy Description & Rationale**: Feature 18 and Screen AP02 enable event leads to broadcast incremental room delays (+5m, +10m, +15m) that cascade across track sessions. Concurrently, Feature 26 and `ScheduledTasks.php` execute an automated background cron (`event-day-hourly-refresh`) pulling official WordCamp.org REST feeds/DOM during active event days. Upstream WordCamp.org feeds reflect original static schedules. The document contains no data model, reconciliation strategy, or schema definition explaining how manual delay offsets survive hourly ingestion syncs. Without a documented delta offset mechanism, subsequent cron runs risk silently overwriting live room delays with stale upstream schedule times.

3. **Contradictory Real-Time Delay Delivery Transport Architecture**
   - **Affected Scope**: [docs/designs/system_architecture_and_screen_catalog.md#L105](file:///e:/Sites/InHouse/campbuddy/V2/docs/designs/system_architecture_and_screen_catalog.md#L105), [L224](file:///e:/Sites/InHouse/campbuddy/V2/docs/designs/system_architecture_and_screen_catalog.md#L224), [L261](file:///e:/Sites/InHouse/campbuddy/V2/docs/designs/system_architecture_and_screen_catalog.md#L261), [L74](file:///e:/Sites/InHouse/campbuddy/V2/docs/designs/system_architecture_and_screen_catalog.md#L74)
   - **Discrepancy Description & Rationale**: The document presents three conflicting communication protocols for schedule delay propagation:
     - The architecture diagram (Line 105) depicts `[Admin WebSockets]` for Admin, `[Service Worker]` for PWA, and `[Ktor REST API]` for Android APK.
     - The Admin Panel header (Line 224) specifies "Technology: Laravel Admin/Manager Controllers, WebSocket SSE" (merging two incompatible standards: bidirectional WebSockets and unidirectional Server-Sent Events).
     - The Non-Functional Requirements (Line 261) and CUJ 3 (Line 74) state delay updates propagate via a "30-second background polling cadence".
     A fresh engineer cannot determine whether client clients should maintain long-lived stateful socket connections (which drop frequently in congested hallways) or perform periodic short HTTP polling.

---

#### 🟡 Major

4. **Vague and Legally Misleading "DPDP Act 2023 Certification" & Privacy Portal Mechanics**
   - **Affected Scope**: [docs/designs/system_architecture_and_screen_catalog.md#L26](file:///e:/Sites/InHouse/campbuddy/V2/docs/designs/system_architecture_and_screen_catalog.md#L26), [L151](file:///e:/Sites/InHouse/campbuddy/V2/docs/designs/system_architecture_and_screen_catalog.md#L151), [L215](file:///e:/Sites/InHouse/campbuddy/V2/docs/designs/system_architecture_and_screen_catalog.md#L215), [L260](file:///e:/Sites/InHouse/campbuddy/V2/docs/designs/system_architecture_and_screen_catalog.md#L260) (Problem 3, Feature 16, Screen A06, NFR Security)
   - **Discrepancy Description & Rationale**: The specification repeatedly claims the client app is "DPDP Act 2023 certified". India's Digital Personal Data Protection Act 2023 is a statutory legal regulation, not an industry technical certification with an accredited certifying authority. Furthermore, Feature 16 introduces an "Automated privacy portal enabling attendees to request complete local data erasure or initiate a public roster removal request". If contact cards are stored solely peer-to-peer on client devices (SQLite/IndexedDB) as claimed in G-3 and NG-1, an external web portal cannot trigger local erasure on another attendee's device. The document must define what data is stored centrally versus locally, how consent is captured for the public roster (W07/AP05), and how removal requests are executed mechanically.

5. **Unspecified Data Ingestion Source for Volunteer Check-In and Attendee Roster**
   - **Affected Scope**: [docs/designs/system_architecture_and_screen_catalog.md#L144](file:///e:/Sites/InHouse/campbuddy/V2/docs/designs/system_architecture_and_screen_catalog.md#L144), [L160](file:///e:/Sites/InHouse/campbuddy/V2/docs/designs/system_architecture_and_screen_catalog.md#L160), [L232](file:///e:/Sites/InHouse/campbuddy/V2/docs/designs/system_architecture_and_screen_catalog.md#L232), [L234](file:///e:/Sites/InHouse/campbuddy/V2/docs/designs/system_architecture_and_screen_catalog.md#L234) (Features 9, 23; Screens AP05, AP07)
   - **Discrepancy Description & Rationale**: Feature 23 and Screen AP07 outline a volunteer gate check-in scanner verifying registration tickets and t-shirt distribution, while Feature 9/AP05 manages an attendee directory. However, Goal G-1 and Feature 26 state that data ingestion is powered solely by WordCamp.org public REST APIs and DOM scrapers. WordCamp.org public APIs only publish sessions, speakers, and sponsors—never private ticket holder lists, barcode tokens, or t-shirt sizes. The document lacks any specification for ticket data ingestion (e.g. Camptix integration, authenticated WordPress REST API hooks, or manual CSV ingestion schemas).

6. **PWA Network-First Caching Strategy Directly Violates 2-Second Corridor Cold Start Goal**
   - **Affected Scope**: [docs/designs/system_architecture_and_screen_catalog.md#L36](file:///e:/Sites/InHouse/campbuddy/V2/docs/designs/system_architecture_and_screen_catalog.md#L36), [L123](file:///e:/Sites/InHouse/campbuddy/V2/docs/designs/system_architecture_and_screen_catalog.md#L123) (Goal G-2, `public/sw.js`)
   - **Discrepancy Description & Rationale**: Section B (`public/sw.js`) states that HTML views are cached using a "network-first caching" strategy. In congested conference corridors (Problem 1), a network-first strategy forces the mobile browser to wait for network socket timeouts (often 10–30 seconds) before falling back to cached HTML. This directly contradicts Goal G-2, which mandates a 2-second cold-start display on PWA. For an offline-first companion app, HTML views require stale-while-revalidate or cache-first caching with background validation.

7. **Unexplained Offline Issuance of Google Wallet Passes**
   - **Affected Scope**: [docs/designs/system_architecture_and_screen_catalog.md#L179](file:///e:/Sites/InHouse/campbuddy/V2/docs/designs/system_architecture_and_screen_catalog.md#L179), [L215](file:///e:/Sites/InHouse/campbuddy/V2/docs/designs/system_architecture_and_screen_catalog.md#L215), [L262](file:///e:/Sites/InHouse/campbuddy/V2/docs/designs/system_architecture_and_screen_catalog.md#L262) (Screens W06, A06; NFR Offline Resilience)
   - **Discrepancy Description & Rationale**: Screens W06 and A06 promise native Google Wallet pass export under airplane-mode / offline constraints. Generating Google Wallet passes requires signing a JWT pass structure with an asymmetric private key registered to a Google Cloud Service Account or communicating with the Google Wallet REST API. The document does not describe how Google Wallet passes are generated without bundling server private keys in the Android client APK or requiring cloud connectivity.

8. **Incomplete "Detailed Implementation Plan" Lacks Execution Phases**
   - **Affected Scope**: [docs/designs/system_architecture_and_screen_catalog.md#L266-L284](file:///e:/Sites/InHouse/campbuddy/V2/docs/designs/system_architecture_and_screen_catalog.md#L266-L284) (Detailed Implementation Plan)
   - **Discrepancy Description & Rationale**: Section "Detailed Implementation Plan" contains only a static list of 6 files to maintain and a 4-line test execution checklist (`figcraft-cli`, `npm run test:js`, `php artisan test`, `npm run build`). It lacks an actionable implementation breakdown: no development phases, data migration sequence, API contract finalization steps, or rollout milestones.

---

#### 🔵 Nit

9. **Undefined Proprietary Tooling ("FigCraft")**
   - **Affected Scope**: [docs/designs/system_architecture_and_screen_catalog.md#L17](file:///e:/Sites/InHouse/campbuddy/V2/docs/designs/system_architecture_and_screen_catalog.md#L17), [L270](file:///e:/Sites/InHouse/campbuddy/V2/docs/designs/system_architecture_and_screen_catalog.md#L270), [L271](file:///e:/Sites/InHouse/campbuddy/V2/docs/designs/system_architecture_and_screen_catalog.md#L271), [L277](file:///e:/Sites/InHouse/campbuddy/V2/docs/designs/system_architecture_and_screen_catalog.md#L277)
   - **Discrepancy Description & Rationale**: The document repeatedly references "FigCraft" (`campbuddy_all_screens.figcraft`, `tools/build_figcraft_multipage.mjs`, `figcraft-cli`) without an inline definition, documentation link, or architectural explanation. A new developer cannot discern whether FigCraft is an in-house AST generator, an external design tool CLI, or a custom markup compiler.

10. **Screen Catalog Route and View Mapping Duplications**
    - **Affected Scope**: [docs/designs/system_architecture_and_screen_catalog.md#L174-L184](file:///e:/Sites/InHouse/campbuddy/V2/docs/designs/system_architecture_and_screen_catalog.md#L174-L184), [L234-L235](file:///e:/Sites/InHouse/campbuddy/V2/docs/designs/system_architecture_and_screen_catalog.md#L234-L235) (Screens W01/W02, W03/W04, W07, AP07/AP08)
    - **Discrepancy Description & Rationale**: Multiple catalog entries map functionally distinct screens to the same Blade views without explanation:
      - W01 (`/`) and W02 (`/events`) both reference `welcome.blade.php`.
      - W03 (`/{slug}`) and W04 (`/{slug}/schedule`) both reference `resources/views/attendee/home.blade.php`.
      - W07 (`/{slug}/people`) references `resources/views/attendee/about.blade.php`.
      - AP07 (`/admin/mobile/check-in`) and AP08 (`/admin/mobile/incident-dispatch`) both map to `admin/mobile/index.blade.php`.
      The document should state whether these are shared multi-tab views or placeholder template mappings.

11. **Inconsistent Namespace Path for `ScheduledTasks.php`**
    - **Affected Scope**: [docs/designs/system_architecture_and_screen_catalog.md#L120](file:///e:/Sites/InHouse/campbuddy/V2/docs/designs/system_architecture_and_screen_catalog.md#L120) vs [L273](file:///e:/Sites/InHouse/campbuddy/V2/docs/designs/system_architecture_and_screen_catalog.md#L273)
    - **Discrepancy Description & Rationale**: Line 120 groups `ScheduledTasks.php` under `app/Services/WordCamp/` or `app/Http/Controllers/`, whereas Line 273 locates it at `app/Support/ScheduledTasks.php`.
