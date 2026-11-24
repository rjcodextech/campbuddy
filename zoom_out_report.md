# Initial Zoom Out Review Report (Stage 5)

**Target Document:** `docs/designs/system_architecture_and_screen_catalog.md` (full file, lines 1..284)  
**Evaluation Standards:** [zoom-out-standard.md](file:///C:/Users/sunil/.gemini/config/plugins/egm/resources/document_standards/zoom-out-standard.md)  
**Reviewers:** `egm-zoom-out` (Skeptical Architect) & `egm-inventive-catalyst` (Inventive Catalyst)

---

## Reviewer Status

| Reviewer | Lens Focus | Status | Findings |
| :--- | :--- | :--- | :--- |
| `egm-zoom-out` | Simplicity, YAGNI, Codebase Grounding, Anti-Weeds | Completed | 2 Blockers, 2 Majors, 1 Nit |
| `egm-inventive-catalyst` | TRIZ System Contradictions & Software Patterns | Completed | 3 Majors, 1 Nit |

---

## Prioritized Findings

### 1. Hallucinated Storage, Scanning & Backend Subsystems - Severity: 🔴 Blocker
*   **Reviewer Lens:** `egm-zoom-out`
*   **Affected Scope:** Lines 13–17, 29, 39, 98–100, 116–129, 198, 206–216, 262
*   **Discrepancy Description**: The document asserts several non-existent architectural technologies:
    1.  *Native Storage*: Asserts "on-device SQLite", "manage offline SQLite synchronization", and "pre-cached into SQLite". In reality, `Repository.kt` and `build.gradle.kts` use a lean `KeyValueStore` (Android `SharedPreferences` / iOS `NSUserDefaults`) serializing JSON data via `kotlinx.serialization`. No SQLite or Room exists in the native app.
    2.  *Barcode Scanning*: Asserts CameraX and Google MLKit dependencies. In reality, `QrCode.kt` is a 38-line Compose Canvas that only renders QR codes; badge scanning relies on native phone camera apps, which natively open attendee URL profiles.
    3.  *Phantom Classes*: Claims `app/Services/TransferCrypto.php` and `MediaProxyController.php`. In reality, cryptographic key exchange is client-side WebCrypto (`transfer-crypto.js` + `DeviceTransferController.php`), and media assets are cached locally via background jobs (`FetchBrandingAssetsJob`, `FillMissingPhotosJob`) and served via `PublicStorageController`.
    4.  *Frontend Frameworks*: Claims Vue and Alpine/Tailwind for attendee views. In reality, attendee pages use zero-framework vanilla JS and hand-crafted BEM/SCSS to maintain an ultra-lean client bundle.
*   **Rationale & Recommendation**: Re-anchor the document to the actual codebase implementation: replace SQLite with `KeyValueStore` + `kotlinx.serialization`, remove CameraX/MLKit claims, and purge non-existent PHP service class references.

---

### 2. Speculative Scope Bloat & Fictional Subsystems (YAGNI) - Severity: 🔴 Blocker
*   **Reviewer Lens:** `egm-zoom-out`
*   **Affected Scope:** Lines 37, 66–70, 142–144, 160–161, 179, 215, 234–235 (Features 7, 8, 23, 24; Screens W06, A06, AP07, AP08)
*   **Discrepancy Description**: The document incorporates speculative operational features that diverge from the core problem of conference schedule glanceability and offline reliability:
    1.  *Gate & Incident Consoles*: AP07 ("Volunteer Gate Check-In" with t-shirt sizing) and AP08 ("Mobile Incident Response Dispatch" with room pins) do not exist in Laravel routes or controllers.
    2.  *vCard 3.0 & Apple/Google Wallet*: Claims encrypted vCard 3.0 payloads and Apple/Google Wallet pass downloads. In reality, `camp-card.js` generates direct web links to LinkedIn, WordPress.org, or X, alongside 900 DPI canvas PNG/SVG exports.
*   **Rationale & Recommendation**: Remove AP07 and AP08 from the admin catalog. Replace vCard and Apple/Google Wallet claims with URL-based CampCard badge links and canvas image downloads.

---

### 3. Network-First Caching Strategy under Choked Connectivity ("Lie-Fi") - Severity: 🟡 Major
*   **Reviewer Lens:** `egm-inventive-catalyst`
*   **Affected Scope:** Section 2.B (Offline Engine: `public/sw.js`) & Offline Resilience (Lines 123–126, 262–264)
*   **Discrepancy Description**: Declaring `"network-first caching for HTML views"` causes client browsers to stall on hung requests when venue Wi-Fi is congested ("Lie-Fi"), delaying hallway schedule lookup by 10–30 seconds and violating G-2 (2-Second Corridor Glanceability).
*   **Rationale & Recommendation**: Apply **Beforehand Cushioning** and **Segmentation**: configure the service worker routing for event companion views to **Stale-While-Revalidate (SWR)** with a sub-500ms network timeout gate, rendering the cached shell and IndexedDB data immediately while reconciling deltas in the background.

---

### 4. Continuous Client REST Polling in Congested Wireless Cells - Severity: 🟡 Major
*   **Reviewer Lens:** `egm-inventive-catalyst`
*   **Affected Scope:** Features 3 & 18 (Delay Broadcaster) & Performance (Lines 138–139, 260–261)
*   **Discrepancy Description**: A fixed 30-second polling cadence across hundreds of active mobile clients creates an unnecessary request storm (30–50+ req/sec) over congested venue cellular towers to catch delay events that occur only 1–3 times per conference day.
*   **Rationale & Recommendation**: Apply **Dynamics** and **Inversion**: implement adaptive polling intervals (e.g., 5-minute backoff during mid-talk periods, ramping to 30s only during session transition windows $[T-5\text{m}, T+5\text{m}]$), and utilize event-driven push notifications for urgent delay alerts where supported.

---

### 5. Cloud Relay Dependency for Co-Located Device Transfer - Severity: 🟡 Major
*   **Reviewer Lens:** `egm-inventive-catalyst` & `egm-zoom-out`
*   **Affected Scope:** Feature 2 (Desktop-to-Mobile Transfer), Open Question 1 (Lines 54, 137)
*   **Discrepancy Description**: Open Question 1 proposes complex mDNS peer discovery for offline transfer. In practice, conference venue Wi-Fi routers enforce client isolation (blocking mDNS/multicast).
*   **Rationale & Recommendation**: Apply **Extraction / Taking Out**: resolve Open Question 1 by rejecting mDNS, and support offline desktop-to-mobile transfer by serializing bookmarked talk IDs directly into an on-screen QR code rendered on the desktop, scanned directly by the mobile device.

---

### 6. Route Namespace & Canonical Screen Binding Inconsistencies - Severity: 🟡 Major
*   **Reviewer Lens:** `egm-zoom-out`
*   **Affected Scope:** Lines 169–185 (Website Catalog W01–W11), Lines 226–236 (Admin Panel AP01–AP08)
*   **Discrepancy Description**:
    1.  The document lists routes as `/{slug}/...` with `attendee.*` names. The actual registered Laravel routes are prefixed with `/event/{event:slug}/...` and named `event.*` (`event.home`, `event.my-day`, `event.quest`, `event.explore`, `event.guide`).
    2.  Screen W04 (`/{slug}/schedule`) and W07 (`/{slug}/people`) are not standalone routes; schedule browsing is integrated into `event.home`, and networking directory is inside `event.explore`.
    3.  AP07 and AP08 are assigned to a fictional `Admin\MobileController`, confusing it with `MobileAppController.php` (which manages server-driven app settings).
*   **Rationale & Recommendation**: Correct the route names, URI prefixes, and view controller bindings in the screen catalog to match `routes/web.php`.

---

### 7. Upstream Scraper Ingestion vs. Schedule Delay Clashing - Severity: 🟢 Nit
*   **Reviewer Lens:** `egm-inventive-catalyst`
*   **Affected Scope:** Features 18 & 26 (Delay Broadcaster vs. Hourly Ingestion Crons)
*   **Discrepancy Description**: If upstream `event-day-hourly-refresh` cron writes directly to the same schedule timestamp columns as manual organizer delay overrides, hourly scraper jobs risk overwriting on-site delay broadcasts.
*   **Rationale & Recommendation**: Apply **Segmentation**: store delay offsets as an append-only delta overlay layer (`session_delay_overlays`) calculated dynamically at query time (`effective_start = scheduled_start + delay_offset`).

---

### 8. Elevation of Generator Scripts into Core Architecture - Severity: 🟢 Nit
*   **Reviewer Lens:** `egm-zoom-out`
*   **Affected Scope:** Lines 268–281 (Target Files & Verification Plan)
*   **Discrepancy Description**: Design document generators (`build_figcraft_multipage.mjs`, `generate_specimen_html.mjs`) are listed under core system implementation files.
*   **Rationale & Recommendation**: Relegate FigCraft specimen scripts to tooling and design documentation sections.
