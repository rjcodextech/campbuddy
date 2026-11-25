# Missing & Undesigned APK Screens Audit (Compared Against M01–M32)

All 32 designed APK screens (`M01`–`M32`) in [specimen.html](file:///e:/Sites/InHouse/campbuddy/V2/docs/designs/specimen.html) have been synced into `native-app/shared`.

Below is the verified list of **screens, sheets, dialogs, and sub-views present in the APK codebase (`native-app/shared`)** that do **not** yet have dedicated standalone FigCraft designs in `M01`–`M32`:

---

## A. Attendee Sub-Flows & Sheets (`native-app/shared`)

1. **Onboarding Intro, Steps 1, 3, 4, 5 & Completion Summary** (`Onboarding.kt`)
   - *Designed so far*: Only Step 2 (`M03 · Personalize Interests`).
   - *Missing designs*:
     - Intro prompt card (`Optional · 30 seconds`, `Not now` / `Start →`)
     - Step 1 (`Is this your first WordCamp?` binary cards)
     - Step 3 (`Joining Contributor Day?` binary cards)
     - Step 4 (`Who would you like to meet?` & `Your profession` fields)
     - Step 5 (`Your WordPress.org name`) + `You are set` summary card
2. **Meet Sheet & Native Date / Time Pickers** (`Plan.kt`)
   - *Missing designs*:
     - `MeetSheet` (`ADD TO PEOPLE TO MEET`, note input, `"Any time at the event"` vs `"At a set time"`, `.ics` & Google Calendar actions)
     - Native `DatePicker` and `TimePicker` sheets for scheduled 1-on-1 meetings
3. **Attendee Discovery Join Form, Wave Dialog & Report Profile Sheet** (`People.kt`)
   - *Designed so far*: Promo card + match list + chat (`M15`, `M16`).
   - *Missing designs*:
     - `JoinForm` (Pick name / Type name / Stay anonymous selector, interest tag picker, `WhoToMeetField`, WordPress.org input, DPDP consent checkbox)
     - `WaveDialog` (`Send a wave` sheet with optional first name & intro note)
     - `ReportDialog` (`Report this profile` sheet with `Discovery.REPORT_REASONS` & note)
     - Leave Discovery Confirmation Sheet (`Leave attendee discovery?`)
4. **Contributor Day Team Detail Sheet & Interest Filter Quiz** (`MoreTabs.kt`)
   - *Designed so far*: Intro + Table Leads (`M20`).
   - *Missing designs*:
     - `What kind of work do you enjoy?` interactive interest quiz (`CONTRIB_QUESTION_TAGS` + `See my matches`)
     - `Team` Detail Bottom Sheet (`Technical background helps` / `No technical background needed`, `Who it suits`, `A beginner-friendly task`, `At their table`, leads)
5. **Full Legal Reader Sheet (`Privacy Notice` & `Terms of Use`)** (`LegalSheet.kt`)
   - *Designed so far*: Help Desk tab & quick switcher pills (`M11`).
   - *Missing designs*:
     - Dedicated Privacy Policy full document sheet (Data Categories table, Rights cards, Grievance Officer section)
     - Dedicated Terms of Use & Code of Conduct full document sheet
6. **Device Data Erase Confirmation Sheet & Event Updates Banner Card** (`Settings.kt`, `EventScreens.kt`)
   - *Missing designs*:
     - `Clear everything on this phone?` destructive confirmation sheet (`DataControls`)
     - `Offline Ready / Cached Data` banner states & `Move plan from laptop / PC` expanded 6-digit sync input state (`DesktopSyncCard`)
7. **Live Native Camera QR Scanner Hardware Integration** (`M14` parity note)
   - *Current APK implementation*: `QrConnectSheet` (manual/deep-link URL connect sheet) because `native-app` currently has no CameraX / MLKit dependencies configured in `gradle/libs.versions.toml`.
   - *Next step if native camera scan is desired*: Add CameraX + MLKit Barcode Scanner dependencies and `CAMERA` permission screen/rationales.

---

## B. Organizer & SuperAdmin Sub-Screens (`OrganizerScreens.kt`, `OrganizerEventTabs.kt`, `OrganizerAdminScreens.kt`)

8. **Organizer / Admin Searchable Events Directory & New Event Sheet** (`OrganizerScreens.kt`, `OrganizerAdminScreens.kt`)
   - *Missing designs*:
     - `EventsListPage` (Search bar, filter drawer with Status/Visibility/When filters, Admin `New event` / `Discover WordCamps` buttons, `Manage` + `Attendee view` event cards)
     - `NewEventSheet` (Admin create-new-WordCamp modal)
9. **Organizer Event Details, Info Editor, Notification Triggers & Danger Zone** (`OrganizerEventTabs.kt`)
   - *Missing designs*:
     - `DetailsTab` (`DetailsEditor` form, `DataCard` live re-fetch schedule/info/branding triggers + fetch log, `DangerZone` type-to-confirm event deletion sheet)
     - `InformationTab` (`InformationEditor` venue/Wi-Fi/registration/emergency editor)
     - `NotificationsTab` (`NotifyRow` triggers for photo album & WordPress.tv announcements)
     - `EventActivityTab` (Per-event audit feed)
10. **Organizer CRUD Modal Sheets** (`OrganizerEventTabs.kt`)
    - *Missing designs*:
      - `AttendeeSheet` (Assign/remove roles for a roster member)
      - `QuestSheet` (Add / Edit custom quest item)
      - `TableSheet` (Add / Edit Contributor Day table & table leads)
      - `DealSheet` (Add / Edit sponsor deal & lead capture config)
11. **SuperAdmin `More` Hub & 5 Platform Sub-Pages** (`OrganizerAdminScreens.kt`)
    - *Designed so far*: Dashboard (`M30`), Event Managers (`M31`), combined Analytics & Leads (`M32`).
    - *Missing designs*:
      - `AdminMorePage` (Menu: Analytics, Errors, Feedback, Manager activity, Maintenance)
      - `ErrorsPage` (7-day / 30-day filterable system checks & failed fetch error list)
      - `FeedbackPage` (Rating breakdown & thank-you card attendee comments)
      - `ActivityLogPage` (Cross-event manager audit trail)
      - `MaintenancePage` (Dedicated global refresh/cache purge screen)
      - `ManagerSheet` (Add / Edit Event Manager, assign WordCamps, deactivate/delete confirmation)
