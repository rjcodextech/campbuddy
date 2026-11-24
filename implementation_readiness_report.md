# Stage 9: Implementation Readiness Report

**Target Document**: `docs/designs/system_architecture_and_screen_catalog.md`  
**Scope Analyzed**: Detailed Implementation Plan (Lines 284–314) & Overall Architectural Traceability  
**Review Personas**:
- Pragmatic Tech Lead (`egm-implementation-readiness`)
- Codebase Details Auditor (`egm-codebase-details`)
- Technical Copy Editor (`egm-style`)

---

### Executive Summary
The Stage 9 review audited the Detailed Implementation Plan for actionable clarity, test harness integration, rollback mechanics, and codebase path alignment. While the high-level architecture, feature matrix, and 38-screen catalog are sound, the implementation section contained **5 Blockers**, **5 Majors**, and **4 Nits**—including unexecutable PHP syntax, non-existent class references (`WordCampScraper.php`), contradictory UI synchronization directives, missing rollback and deployment milestones, and omitted intermediate compilation CLI steps.

---

### Findings

#### 🔴 Blocker

1. **Unexecutable Syntax Invocation (`ScheduledTasks::WHAT[...]`)**
   - **Reviewer**: Pragmatic Tech Lead (`egm-implementation-readiness`), Codebase Details Auditor (`egm-codebase-details`)
   - **Affected Scope**: [docs/designs/system_architecture_and_screen_catalog.md#L288](file:///e:/Sites/InHouse/campbuddy/V2/docs/designs/system_architecture_and_screen_catalog.md#L288)
   - **Discrepancy Description & Rationale**: Step 1.2 commands: `Execute ScheduledTasks::WHAT['fill-missing-photos'] and ScheduledTasks::WHAT['backfill-event-branding']`. In `App\Support\ScheduledTasks`, `WHAT` is an associative array of localized display strings rendered on the admin dashboard, not an executable function or command. Attempting to execute it causes immediate runtime failure. Must provide exact CLI Artisan/Job invocations:
     `php artisan tinker --execute="App\Jobs\FillMissingPhotosJob::dispatchSync(App\Models\Event::firstOrFail());"` and `php artisan tinker --execute="App\Jobs\FetchBrandingAssetsJob::dispatchSync(App\Models\Event::firstOrFail());"` (or `php artisan schedule:run`).

2. **Non-Existent Class and Path References in Ingestion Plan**
   - **Reviewer**: Codebase Details Auditor (`egm-codebase-details`), Pragmatic Tech Lead (`egm-implementation-readiness`)
   - **Affected Scope**: [docs/designs/system_architecture_and_screen_catalog.md#L121](file:///e:/Sites/InHouse/campbuddy/V2/docs/designs/system_architecture_and_screen_catalog.md#L121), [L287](file:///e:/Sites/InHouse/campbuddy/V2/docs/designs/system_architecture_and_screen_catalog.md#L287)
   - **Discrepancy Description & Rationale**: Step 1.1 instructs verifying `WordCampRestClient.php` and `WordCampScraper.php`. `WordCampScraper.php` does not exist in the codebase; scraping logic is embedded directly inside `App\Services\WordCampRestClient` using `Symfony\Component\DomCrawler\Crawler`. Furthermore, Section 2.A references `app/Services/WordCamp/WordCampRestClient.php`; the correct path is `app/Services/WordCampRestClient.php`.

3. **Contradictory UI Scope and Template Directive in Phase 3**
   - **Reviewer**: Technical Copy Editor (`egm-style`), Pragmatic Tech Lead (`egm-implementation-readiness`)
   - **Affected Scope**: [docs/designs/system_architecture_and_screen_catalog.md#L297](file:///e:/Sites/InHouse/campbuddy/V2/docs/designs/system_architecture_and_screen_catalog.md#L297)
   - **Discrepancy Description & Rationale**: Step 3.1 commands: *"Synchronize all 11 Website Frontend Blade views ([resources/views/attendee/](file:///e:/Sites/InHouse/campbuddy/V2/resources/views/attendee/)) with touch-optimized 2×3 action grids and live ticking countdown timers."* According to the Screen Catalog (Platform Tier 1), only **W03** (`event.home` / `home.blade.php`) contains the 2×3 action grid and 30s ticking live countdown timer. Screens W04–W11 feature distinct layouts (timelines, canvas badges, tables, floorplans). Applying 2×3 grids across all 11 views contradicts the catalog design. Additionally, W01 and W02 reside in `resources/views/welcome.blade.php`, not `resources/views/attendee/`.

4. **Complete Omission of Rollout Milestones, Rollback Strategies, and Phase Dependencies**
   - **Reviewer**: Pragmatic Tech Lead (`egm-implementation-readiness`)
   - **Affected Scope**: [docs/designs/system_architecture_and_screen_catalog.md#L284-L311](file:///e:/Sites/InHouse/campbuddy/V2/docs/designs/system_architecture_and_screen_catalog.md#L284-L311)
   - **Discrepancy Description & Rationale**: The plan lacks rollout milestones (Local -> Staging Rehearsal -> Production Canary), gate criteria, and rollback strategies. If a service worker deployment introduces corrupted cache buckets, there is no cache-busting procedure documented (e.g., bumping SW cache from `campbuddy-v3` to `campbuddy-v4` or flipping `sw-flags.json`). Inter-phase dependencies are also unmapped.

5. **Inaccurate Scraper Pipeline Lifecycle Regarding `ScheduleDelay::apply`**
   - **Reviewer**: Codebase Details Auditor (`egm-codebase-details`)
   - **Affected Scope**: [docs/designs/system_architecture_and_screen_catalog.md#L289](file:///e:/Sites/InHouse/campbuddy/V2/docs/designs/system_architecture_and_screen_catalog.md#L289)
   - **Discrepancy Description & Rationale**: Step 1.3 states: "Validate that `ScheduleDelay::apply` preserves database baseline `starts_at` times during hourly scraper refresh runs." `ScheduleDelay::apply` is never executed during scraper ingestion runs (`FetchSpeakersSponsorsSessionsJob`); it is strictly a runtime read projection applied in `EventPageController::cached()` and `SendSessionRemindersJob`. The plan must clarify that scraper sync writes unmodified upstream baseline times to the database without being tainted by active delays.

---

#### 🟡 Major

6. **Bypassing Existing Automated Test Harnesses in Phases 1–4**
   - **Reviewer**: Pragmatic Tech Lead (`egm-implementation-readiness`)
   - **Affected Scope**: [docs/designs/system_architecture_and_screen_catalog.md#L286-L305](file:///e:/Sites/InHouse/campbuddy/V2/docs/designs/system_architecture_and_screen_catalog.md#L286-L305)
   - **Discrepancy Description & Rationale**: Phases 1 through 4 rely on manual/qualitative checks while bypassing existing automated test suites in `package.json`:
     - `npm run test:browser` runs `tests/browser/offline.e2e.mjs` (verifying SW caching, offline fallback, and cache replacement).
     - `npm run test:ui` runs `tests/browser/ui-audit.mjs` (auditing 17 mobile/attendee screens for tap targets and layout integrity).
     - `php artisan test --filter=ScheduleDelayTest` verifies delay cascading and baseline preservation.
     These automated tests must be embedded directly into their respective phases.

7. **Missing Gradle CLI Commands for Kotlin Multiplatform Verification**
   - **Reviewer**: Pragmatic Tech Lead (`egm-implementation-readiness`)
   - **Affected Scope**: [docs/designs/system_architecture_and_screen_catalog.md#L294](file:///e:/Sites/InHouse/campbuddy/V2/docs/designs/system_architecture_and_screen_catalog.md#L294), [L299](file:///e:/Sites/InHouse/campbuddy/V2/docs/designs/system_architecture_and_screen_catalog.md#L299)
   - **Discrepancy Description & Rationale**: Steps 2.3 and 3.3 lack concrete Gradle CLI invocation commands for the Windows environment. Must provide `cd native-app && ./gradlew.bat testDebugUnitTest` and compile check `./gradlew.bat :androidApp:assembleDebug`.

8. **Missing Intermediate FigCraft CLI Compilation Step**
   - **Reviewer**: Codebase Details Auditor (`egm-codebase-details`)
   - **Affected Scope**: [docs/designs/system_architecture_and_screen_catalog.md#L309-L310](file:///e:/Sites/InHouse/campbuddy/V2/docs/designs/system_architecture_and_screen_catalog.md#L309-L310)
   - **Discrepancy Description & Rationale**: Step 5.3 invokes `node tools/build_figcraft_multipage.mjs`, which generates an intermediate JSON command stream (`scratch/figcraft_multipage_script.json`). Producing the final `.figcraft` binary requires:
     `figcraft-cli run scratch/figcraft_multipage_script.json --out docs/designs/campbuddy_all_screens.figcraft`.
     Without this step, `figcraft-cli info` inspects stale artifacts.

9. **Vague Verbs and Missing Acceptance Criteria in Phases 1, 2, and 4**
   - **Reviewer**: Technical Copy Editor (`egm-style`)
   - **Affected Scope**: [docs/designs/system_architecture_and_screen_catalog.md#L287-L304](file:///e:/Sites/InHouse/campbuddy/V2/docs/designs/system_architecture_and_screen_catalog.md#L287-L304)
   - **Discrepancy Description & Rationale**: Verbs such as "Verify", "Validate", "Confirm", and "Ensure" are used without pass/fail criteria or expected assertion outputs. Replace with actionable imperative steps and explicit verification criteria.

10. **Ambiguous Push Dispatch Endpoint Specification in Phase 4**
    - **Reviewer**: Codebase Details Auditor (`egm-codebase-details`)
    - **Affected Scope**: [docs/designs/system_architecture_and_screen_catalog.md#L303](file:///e:/Sites/InHouse/campbuddy/V2/docs/designs/system_architecture_and_screen_catalog.md#L303)
    - **Discrepancy Description & Rationale**: Phase 4 Step 2 refers to `/admin/events/{event}/notifications` for dispatching messages. That route is the GET view; message submission is handled via POST `/admin/events/{event}/broadcasts` by `BroadcastController::store`.

---

#### 🔵 Nit

11. **Redundant Parentheses and Pleonasms in Phase 5**:
    - Lines 307–310 contain redundant explanations like `(verify passing status...)` and pleonasms like `validate successfully`. Streamline into direct CLI commands.
12. **Section Heading Semantic Calibration**:
    - Change `## Detailed Implementation Plan` to `## Implementation Verification & Deployment Readiness Plan`.
13. **Boilerplate Watermark Removal**:
    - Delete line 314 (`*Drafted with EGM*`).
14. **Namespace Directory Alignment in Subsystem Breakdown (Section 2.A)**:
    - Update `app/Services/WordCamp/WordCampRestClient.php` to `app/Services/WordCampRestClient.php` and `app/Services/WordCampNormalizer.php`.
