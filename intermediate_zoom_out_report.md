# Stage 8: Intermediate Zoom Out Report

**Target Document**: `docs/designs/system_architecture_and_screen_catalog.md`  
**Reviewer Persona**: Skeptical Architect (`egm-zoom-out`)  
**Scope Analyzed**: Full Document (Additions & Remediations from Stages 6 & 7)

---

### Architectural Validation & High-Altitude Assessment

The additions and remediations made in Stages 6 and 7 represent an exceptionally sound, pragmatic, and minimalist architectural foundation:
1. **KMP Storage Simplicity (`KeyValueStore`)**: Retaining a lightweight `KeyValueStore` interface backed by `SharedPreferences` on Android and `NSUserDefaults` on iOS with `kotlinx.serialization` avoids heavy SQLite/Room/SQLDelight database overhead and multiplatform compilation friction. For a conference companion app caching JSON payloads, this is textbook minimalism.
2. **PWA Storage Partitioning**: Clear segregation between CacheStorage (`campbuddy-v3` for pre-rendered HTML views) and IndexedDB (`db.js` for dynamic client state) properly leverages standard browser primitives without requiring external client DB frameworks (e.g., RxDB, PouchDB).
3. **Zero-Knowledge Device Transfer (`DeviceTransferController`)**: Leveraging native browser WebCrypto (ECDH + AES-GCM) with an ephemeral, rate-limited server mailbox avoids building centralized account infrastructure, credential storage, or password reset pipelines. This perfectly satisfies DPDP Act 2023 / GDPR zero-cloud retention mandates.
4. **Idempotent Read-Time Delays (`ScheduleDelay::apply`)**: Computing delays dynamically at read-time against immutable baseline `starts_at` timestamps prevents database drift and race conditions during automated hourly WordCamp scraper refreshes.
5. **Standardized Screen Catalog**: The 7-attribute schema applied across all 38 production screens (11 Web, 9 PWA, 10 APK, 8 Admin) enforces clear interface boundaries and offline behaviors without dropping into implementation weeds.

---

### Findings

#### 🔴 Blocker
*None. The core architecture successfully avoids premature optimizations, adheres to codebase reuse, and establishes a minimalist, pragmatic multi-platform design.*

---

#### 🟡 Major

1. **Speculative Optical Data Transfer in Open Question 1 (YAGNI & Scope Creep)**
   - **Affected Scope**: [docs/designs/system_architecture_and_screen_catalog.md#L55](file:///e:/Sites/InHouse/campbuddy/V2/docs/designs/system_architecture_and_screen_catalog.md#L55)
   - **Discrepancy Description & Rationale**: Open Question 1 considers developing an optical QR-based fallback to transfer an encrypted profile from desktop to mobile when conference Wi-Fi is severed. CampBuddy already features an optical QR protocol for peer-to-peer contact exchange (CampCard, F-07/F-08). Device transfer (F-02) is an initial setup convenience between an attendee's own laptop and phone before talks begin. Designing a chunked, animated, or high-density optical QR payload streaming protocol to transfer encrypted profile blobs across screens introduces massive speculative complexity for an extreme edge case (an attendee who set up bookmarks on a laptop, walked into a venue with zero cellular and zero Wi-Fi, and cannot simply re-star talks). This is a YAGNI violation and should be explicitly closed as **Rejected / Out of Scope**.

2. **Speculative Static HTML Archival Engine in Open Question 2 (Premature Optimization & YAGNI)**
   - **Affected Scope**: [docs/designs/system_architecture_and_screen_catalog.md#L56](file:///e:/Sites/InHouse/campbuddy/V2/docs/designs/system_architecture_and_screen_catalog.md#L56)
   - **Discrepancy Description & Rationale**: Open Question 2 suggests building an automated routine to archive past events into read-only static HTML after 90 days. Introducing a static HTML exporter, file-generation workers, and divergent routing paths for archived events creates unnecessary architectural deadweight. The Laravel backend already renders past events effortlessly via `EventMemoriesPageController` with negligible database load, and standard HTTP caching headers (`Cache-Control: public, max-age=...`) paired with ServiceWorker CacheStorage already solve archival caching. This question should be answered as **Rejected: maintain standard controller rendering with HTTP cache headers**.

3. **Erroneous Screen Binding for Social Media Studio (`F-25`) in Feature Matrix**
   - **Affected Scope**: [docs/designs/system_architecture_and_screen_catalog.md#L178](file:///e:/Sites/InHouse/campbuddy/V2/docs/designs/system_architecture_and_screen_catalog.md#L178)
   - **Discrepancy Description & Rationale**: Feature F-25 ("Social Media Studio & Reel Kit") is mapped in the matrix to bound screen IDs `AP01, AP06`. However, AP06 is explicitly cataloged as Contributor Table Config (`/admin/events/{event}/contributor-tables`), which has zero relation to social media graphics or reel audio. The actual social media studio exists in the backend at `/admin/events/{event}/social` (`Admin\SocialController.php`), but it was deliberately omitted from the 8-screen admin catalog (AP01–AP08). F-25's binding to AP06 is an erroneous cross-binding that creates confusion. F-25 should bind only to `AP01` (as a dashboard launch tile) or be noted as an auxiliary administrative tool outside the core 38-screen catalog.

---

#### 🔵 Nit

4. **Protocol Inconsistency: SSE Mention vs Stateless HTTP Polling**
   - **Affected Scope**: [docs/designs/system_architecture_and_screen_catalog.md#L106](file:///e:/Sites/InHouse/campbuddy/V2/docs/designs/system_architecture_and_screen_catalog.md#L106), [L240](file:///e:/Sites/InHouse/campbuddy/V2/docs/designs/system_architecture_and_screen_catalog.md#L240)
   - **Discrepancy Description & Rationale**: The architecture diagram labels the Admin channel as `[Admin SSE / HTTP]`, and Platform Tier 4 lists technology as `SSE / Polling`. However, CUJ 3 (line 75) and the Non-Functional Requirements (line 277) explicitly define a stateless `30–60 second background polling cadence for live room delays with ETag / If-Modified-Since headers`. Long-lived SSE connections in standard PHP-FPM / Laravel deployments lock worker processes without dedicated async daemons (Swoole/Roadrunner) or external pub/sub services. Mentioning SSE is aspirational jargon that contradicts the pragmatic, stateless HTTP polling mechanism actually implemented. It should be simplified to `HTTP / Polling with ETags`.

5. **Misattributed Screen Binding for Post-Event Memories (`F-15`)**
   - **Affected Scope**: [docs/designs/system_architecture_and_screen_catalog.md#L168](file:///e:/Sites/InHouse/campbuddy/V2/docs/designs/system_architecture_and_screen_catalog.md#L168)
   - **Discrepancy Description & Rationale**: Feature F-15 binds to `W11, P02`. Screen P02 is the "Standalone Live Home", which is the active live conference dashboard with a ticking countdown timer, not the memories page. In the PWA, memories are accessed via `/event/{event:slug}/memories` (W11). Binding F-15 to P02 is inaccurate and should be corrected to just `W11`.

6. **Altitude Drop: Hardcoded Test Counts in Implementation Plan**
   - **Affected Scope**: [docs/designs/system_architecture_and_screen_catalog.md#L305-L306](file:///e:/Sites/InHouse/campbuddy/V2/docs/designs/system_architecture_and_screen_catalog.md#L305-L306)
   - **Discrepancy Description & Rationale**: Phase 5 of the Detailed Implementation Plan specifies exact integer test counts: `verify all 431 unit and integration tests pass` and `verify all 1,181 backend tests pass`. Citing volatile concrete test counts in a master architecture specification is an altitude drop. As tests are added or modified, these numbers rot immediately. The plan should state the architectural verification gate generically: `Verify complete passing status of JS attendee and PHP backend test suites`.
