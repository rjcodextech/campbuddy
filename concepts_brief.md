# CampBuddy APK UI Evolution: Tactile Card-First System

* **Author:** rjcodextech
* **Date:** 2026-11-24

## Overview

This proposal establishes an ergonomic 5-screen tactile hierarchy for the CampBuddy mobile APK, replacing the current 7-tab layout to resolve attendee drop-off and communication friction during WordCamps. The design adheres strictly to CampBuddy's official brand guidelines (Baloo 2 display typography, Poppins UI copy, Navy `#1E293B`, Coral `#FF4D2D`, Ochre `#D97706`, and Paper Soft `#FFF7ED`).

Per project directives:
- The standalone language screen is removed.
- 100% of the 16 existing capabilities are preserved with zero backend modifications or schema changes.
- Multi-lens Goldfish reviews (`egm-design`, `egm-codebase-details`, `egm-zoom-out`, `egm-comprehension`, `egm-implementation-readiness`, `egm-structural`, `egm-inventive-catalyst`) have completed and their findings are integrated below.

---

## Analysis & Multi-Lens Review Synthesis

### 1. UX & Physical Ergonomics (`egm-design`)
- **Radii Consistency:** All components adhere to `Radii` tokens from `Theme.kt`: Cards `20.dp` (`Radii.card`), Pills `50%` (`Radii.pill`), Rows `14.dp` (`Radii.row`), and Chips `6.dp`.
- **Touch Target Normalization (Blocker Resolved):** Top bar utility actions (`👑 Staff`, `❓ Guide`, `🔄 Switcher`) require explicit `48.dp x 48.dp` tap targets (`Modifier.size(48.dp)`) despite visual icon containers rendering at `32.dp`.
- **Sticky Inset Decoupling:** The emergency SOS Helpdesk Bar (`#FCA5A5` outline, `#DC2626` warning, Coral button) remains pinned above system navigation bars, while tertiary legal links (`Privacy`, `Terms`, `Data Request`) are moved into the scrollable body to prevent visual crowding.

### 2. Codebase & Compose Multiplatform Alignment (`egm-codebase-details`)
- **Consolidation of Existing Tabs:**
  - `home` -> Screen 1: `LiveEventDashboard` (Hero + My People strip + 2x3 Grid Hub + Sticky SOS Anchor).
  - `picker` -> Screen 2: `PickerScreen` (Country filter chips + Desktop-to-Mobile sync card).
  - `my-day` -> Screen 3: `MyDayTab` (Schedule health banner + conflict detection + 10m local reminders).
  - `camp-card` -> Screen 4: `CampCardTab` (Claimed badge banner + QR rendering via existing `libs.qrcode` + interest wave matching).
  - `explore`, `quest`, `contribute`, `memories` -> Screen 5: `ExploreHubTab` (Role chips + Contributor roadmap + Sponsor steals/deals + GDPR data request).
- **Native Dependency Audit:**
  - QR Code generation uses `libs.qrcode` (`QRCode.ofSquares()`) already present in `build.gradle.kts`.
  - Image fetching uses `coil3.compose` with Ktor network fetcher (`App.kt:97-99`).
  - Offline storage uses `KeyValueStore` backed by `EncryptedSharedPreferences` on Android.

### 3. Strategic Anti-Drop-Off Mechanics (`egm-zoom-out`)
- **Mitigating Corridor Navigation Paralysis:** Screen 1 acts as an air-traffic controller, giving attendees answers to *"Where do I go in the next 10 minutes?"* in under 2 seconds.
- **Handling Venue Wi-Fi Outages:** `Repository.warmUp(slug)` caches all sessions, speakers, sponsors, quests, and legal docs locally. The status indicator (`● Offline Ready`) provides immediate psychological reassurance.
- **Breaking Social Isolation:** The "My People & Matches" strip immediately visible on Screen 1 lowers barriers to interaction by connecting attendees through shared WordPress tags (`Gutenberg`, `Core`, `WooCommerce`).

### 4. Implementation Readiness & Backend Audit (`egm-implementation-readiness`)
- **Zero Backend Changes Required:** All 16 features map to existing endpoints in `Repository.kt`:
  - Schedule & Clock: `Repository.sessions(slug)` & `Repository.event(slug)`
  - Live Delays: `Repository.delays(slug)`
  - Sponsor Deals & Swag: `Repository.deals(slug)` & `Repository.freeSteals(slug)`
  - Contributor Tables & Quests: `Repository.tables(slug)` & `Repository.quests(slug)`
  - Attendee Roster & Roles: `Repository.roster(slug)` & `Repository.discovery(slug)`
  - Legal & GDPR Erasure: `Repository.legal(slug)`
- **Resource Footprint:** Countdown timer in `EventScreens.kt` updates on a 30-second interval (`delay(30_000)`), keeping battery consumption negligible.

### 5. Inventive Contradictions Resolved (`egm-inventive-catalyst`)
- **TRIZ Principle #1 (Segmentation):** Screen 1 handles ambient telemetry (Current session + 4 matches + 6 entry points). Deep data tables are segmented into secondary screens (Screens 3 and 5), allowing rapid hallway parsing without sacrificing multi-track depth.
- **TRIZ Principle #24 (Intermediary):** Stale-while-revalidate SQLite/KeyValueStore caching keeps all event assets locally primed. Dynamic delay announcements poll in the background without blocking the UI.
- **TRIZ Principle #7 (Nested Doll):** Screen 5 unifies all community roles under a single tactile chip carousel (`All`, `Speakers`, `Organizers`, `Sponsors`, `Volunteers`, `Media`), eliminating separate fragmented screens.

---

## 16-Feature Traceability Matrix

| # | Existing Feature | Target Screen & Component | User Action & Access Path |
| :- | :--- | :--- | :--- |
| **1** | **What is CampBuddy** | Screen 1: Top Bar `❓` (Guide trigger) | Opens modal sheet explaining the companion philosophy. |
| **2** | **What is WordCamp** | Screen 1: Top Bar `❓` + "Start Here" banner | First-timer guide explaining tracks, halls, and Contributor Day. |
| **3** | **Live Event Schedule & Status** | Screen 1 (Hero Card) & Screen 3 (My Day) | Live clock, current phase, "Next Up" countdown, full talk lists. |
| **4** | **Notifications & Schedule Reminders** | Screen 3 (My Day & Reminders) | 10-minute prior push/local alerts, conflict warning banner. |
| **5** | **Discover People by Shared Interests** | Screen 1 ("My People") & Screen 4 (CampCard) | Attendee discovery opt-in, interest tags (Gutenberg, REST, Core), wave. |
| **6** | **Sponsor Deals, Free Steals & Swag** | Screen 1 (Grid Tile 6) & Screen 5 (Explore) | Directory of sponsor booths, digital coupons, swag availability. |
| **7** | **Privacy, Terms, Data Deletion/Complaint**| Screen 1 (Scrollable Body) & Screen 5 (Legal) | Direct access to GDPR data export, erasure request, and terms. |
| **8** | **Organizer & Admin Staff Login** | Screen 1: Top Bar `👑 Staff` pill | Enters staff console for live push alerts and badge check-ins. |
| **9** | **Contribution Guide (Gamification)** | Screen 1 (Grid Tile 5) & Screen 5 (Quests) | Step-by-step roadmap for WordCamp Contributor Day tables. |
| **10**| **Prepare for Event / Quests** | Screen 1 (Grid Tile 5) & Screen 5 (Quests) | Interactive checklist ("Scan 3 badges", "Visit 2 booths"). |
| **11**| **Attendee Directory by Roles** | Screen 5 (Explore Hub) | Filter chips: *Speakers, Organizers, Sponsors, Volunteers, Media*. |
| **12**| **CampCard QR & Social Sharing** | Screen 1 (Grid Tile 3) & Screen 4 (CampCard) | Flip QR card sharing LinkedIn, WordPress.org, Twitter, and site. |
| **13**| **Local & Offline-First Operation** | Global Engine (SQLite / KeyValueStore) | Status bar indicator (`● Offline Ready`), all assets cached locally. |
| **14**| **Event Switcher & Country Filter** | Screen 1: Top Bar `🔄` & Screen 2 (Switcher) | Switch active WordCamps, filter by country (*India, Japan, USA*). |
| **15**| **Desktop-to-Mobile Migration** | Screen 2 (Event Switcher) | Enter 6-digit sync code to import bookmarks from desktop browser. |
| **16**| **Claim Profile from Attendee List** | Screen 4 (CampCard) | *"Is this you? Claim your profile"* to verify badge ownership. |

---

## Goals vs. Non-Goals

### In-Scope Goals
- Re-architect the Compose Multiplatform UI across the 5 tactile screens.
- Maintain 100% feature parity with the current Laravel/KMP feature set.
- Implement CampBuddy brand guidelines (Baloo 2 display typography, Poppins UI copy, 20dp card radii, Coral `#FF4D2D`, Navy `#1E293B`, Paper Soft `#FFF7ED`).
- Enforce 48dp minimum touch bounds for all top-bar utilities and accessibility font scaling up to 130%.

### Out-of-Scope Non-Goals
- Adding external chat protocols or unvetted push notifications.
- Standalone language picker onboarding flows.
- Modifying backend Laravel schema or API contracts.

---

## Alternatives Weighed

- **Alternative 1: 7-Tab Bottom Bar (Current Codebase):** Rejected because multiple competing tabs create navigation clutter and increase attendee exit rates during events.
- **Alternative 2: Floating Action Button (FAB) Menu:** Rejected because floating overlays obscure card content on compact displays and break thumb-zone ergonomics.

---

## Verification Artifacts
- **Interactive Visual Preview:** [wireframe.html](file:///C:/Users/sunil/.gemini/antigravity/brain/bd655e0a-5a1d-4d84-917e-eca77c7a4030/wireframe.html)
- **Detailed Specifications:** [wireframe.md](file:///C:/Users/sunil/.gemini/antigravity/brain/bd655e0a-5a1d-4d84-917e-eca77c7a4030/wireframe.md)
