# 3.12 Explore — Sponsors & Event Information

[← Index](00-index.md) · Previous: [3.11 Onboarding](11-onboarding.md) · Next: [3.13 Data controls →](13-data-controls.md)

| ID | Requirement |
|---|---|
| EI1 | **Sponsors**: name, description, booth location (where organizers provide it), website, and a link into any active Deals ([3.7](07-deals.md)) from that sponsor — sourced from the REST ingestion in [finding 0.1](../00-findings.md). |
| EI2 | **Event Information**: venue, important links, wifi details (only if organizers supply them — never fabricated), social event info, registration info, Contributor Day location, code of conduct, emergency/contact info, and any other organizer-supplied nearby-venue info. This is where V1's "More" tab content lives now ([1.2](../01-project-overview.md#12-navigation--information-architecture)) — folded into Explore rather than kept as its own top-level tab. |
| EI3 | Every field in EI2 is admin-editable per event ([9](../09-admin-panel.md)) and simply omitted from display when an organizer hasn't supplied it — never a placeholder or a broken-looking blank field. |

> **Current implementation note:** EI2's fields render as plain text rows, not dead links — only Code of Conduct/Important Links (which have real URLs) and Emergency Contact (auto-detected as `tel:`/`mailto:` when the value looks like a phone number or email) are actually tappable. Sponsor listings (EI1) also show the event's own logo/name at the top of this panel — see [3.2](02-branding.md)'s current-implementation note.

> **Current implementation note (auto-fetched Event Information):** EI2's fields are now **filled in automatically** from what the event itself publishes, instead of being typed in for every event (`FetchEventInfoJob` → `EventInfoFetcher`). The rule throughout is *blank rather than wrong*: a field gets a value only where a source clearly states it, never a guess or a placeholder — which is EI3, enforced at the source.
>
> | Field | Where it comes from |
> |---|---|
> | Venue | **central.wordcamp.org**'s record for the event (matched on the exact site URL, never a fuzzy title): venue name + address on one line, else the registered city. See [finding 0.8](../00-findings.md). |
> | Important links | The event site's own front page plus its Tickets, Schedule, Location, Contact and FAQ pages — whichever exist. |
> | Code of conduct | The site's Code of Conduct page URL. |
> | Registration info | The first sentences of the Tickets page's opening paragraphs, only if they're about tickets (a FAQ answer further down doesn't count). |
> | Contributor Day location | A sentence on the Contributor Day page that says where it happens, else a labelled "Venue: …" line next to "Contributor Day" (some sites put both days on their Location page). |
> | Emergency / contact | A phone number the page labels as an emergency/medical/first-aid line, else the organizers' email from the **Contact** page, else an `@wordcamp.org` alias from the Code of Conduct / FAQ. Never the shared `report@wordcamp.org`, and never a personal address that only appears inside prose. |
> | Wifi | A sentence that mentions wifi **and** gives a network or password ("wifi will be available" alone is not information). |
> | Social event, Nearby venues | The opening paragraph of a page whose slug/title says after-party / social / networking, or accommodation / hotels / travel. |
>
> Most events state only some of these; the rest stay blank and are simply omitted from Explore → Event Info.
>
> **Sources & fallback:** pages are read through each site's own `wp-json/wp/v2/pages` (structured, one request for the page list + one for their content); if a site's REST API is off or blocked it **falls back to scraping** the homepage's own links and each page's HTML (`WordCampSitePages`). Page matching is deliberately strict — an exact slug beats a partial one (`/tickets/` over `/ticket-countdown/`), and a long, specific page such as `/evening-programme-november-12th/` is never mistaken for "the schedule".
>
> **Admin edits are never overwritten.** `events.info_fetched` remembers exactly what the last fetch wrote. A field whose current value differs from that snapshot was typed by an admin and is left alone (and reported in the fetch log); a field still holding the fetched value is refreshed — or blanked if the source no longer has it. Clearing a field hands it back to auto-fill. The admin form says which is which under every field ("Auto-filled from the WordCamp site" / "Edited by you — kept when fetching"). If neither source answers at all (an outage) **nothing changes**, so bad connectivity can't wipe good data.
>
> **When it runs:** daily at 02:00 for `approved` and `active` events (`ingest-event-info`), immediately when an event is approved, on **Fetch latest** in the admin (runs synchronously so the result shows on the redirect), and as a step of `php artisan campbuddy:ingest {slug}`. Each run writes a `fetch_log` row (`job_type = event_info`) saying how many of the nine fields were found and where from. Discovery still seeds a lone `venue` (the city) into new events, recorded as machine-filled so the first fetch can replace it with the real venue.