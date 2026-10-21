package club.campbuddy.shared.ui

import androidx.compose.foundation.background
import androidx.compose.foundation.border
import androidx.compose.foundation.clickable
import androidx.compose.foundation.horizontalScroll
import androidx.compose.foundation.layout.Arrangement
import androidx.compose.foundation.layout.Box
import androidx.compose.foundation.layout.Column
import androidx.compose.foundation.layout.ExperimentalLayoutApi
import androidx.compose.foundation.layout.FlowRow
import androidx.compose.foundation.layout.PaddingValues
import androidx.compose.foundation.layout.Row
import androidx.compose.foundation.layout.fillMaxSize
import androidx.compose.foundation.layout.fillMaxWidth
import androidx.compose.foundation.layout.padding
import androidx.compose.foundation.layout.size
import androidx.compose.foundation.lazy.LazyColumn
import androidx.compose.foundation.lazy.LazyListScope
import androidx.compose.foundation.lazy.items
import androidx.compose.foundation.rememberScrollState
import androidx.compose.foundation.shape.CircleShape
import androidx.compose.foundation.shape.RoundedCornerShape
import androidx.compose.material3.MaterialTheme
import androidx.compose.material3.Text
import androidx.compose.runtime.Composable
import androidx.compose.runtime.getValue
import androidx.compose.runtime.mutableStateOf
import androidx.compose.runtime.remember
import androidx.compose.runtime.setValue
import androidx.compose.ui.Alignment
import androidx.compose.ui.Modifier
import androidx.compose.ui.draw.clip
import androidx.compose.ui.semantics.Role
import androidx.compose.ui.text.font.FontWeight
import androidx.compose.ui.text.input.KeyboardType
import androidx.compose.ui.unit.dp
import androidx.compose.ui.unit.sp
import club.campbuddy.shared.data.ApiClient
import club.campbuddy.shared.data.Loaded
import club.campbuddy.shared.domain.EventClock
import club.campbuddy.shared.model.DealForm
import club.campbuddy.shared.model.DelayRequest
import club.campbuddy.shared.model.DetailsForm
import club.campbuddy.shared.model.EventDetails
import club.campbuddy.shared.model.EventInformation
import club.campbuddy.shared.model.KeyLabel
import club.campbuddy.shared.model.OrgDeal
import club.campbuddy.shared.model.OrgQuest
import club.campbuddy.shared.model.OrgTable
import club.campbuddy.shared.model.OrganizerAttendeeEntry
import club.campbuddy.shared.model.OrganizerOverview
import club.campbuddy.shared.model.QuestForm
import club.campbuddy.shared.model.TableForm
import club.campbuddy.shared.model.UpdateMemoriesRequest
import kotlinx.datetime.Clock
import kotlinx.datetime.toLocalDateTime
import kotlinx.serialization.json.JsonPrimitive

/** What every tab of one event needs. */
internal class EventCtx(
    val api: ApiClient,
    val token: String,
    val eventId: Long,
    val slug: String,
    val name: String,
    val isAdmin: Boolean,
    val overview: OrganizerOverview?,
    val reloadOverview: () -> Unit,
    val open: (String) -> Unit,
    val onExpired: () -> Unit,
)

/** A tab's scrolling body, with room at the bottom for the message strip. */
@Composable
private fun TabBody(content: LazyListScope.() -> Unit) {
    LazyColumn(Modifier.fillMaxSize(), contentPadding = PaddingValues(start = 16.dp, end = 16.dp, top = 16.dp, bottom = 96.dp), verticalArrangement = Arrangement.spacedBy(12.dp), content = content)
}

/** The usual three states of a tab's load, drawn as list items. */
private fun <T> LazyListScope.loaded(state: LoadState<T>, reload: () -> Unit, onExpired: () -> Unit, ready: LazyListScope.(T) -> Unit) {
    when (state) {
        LoadState.Loading -> item { Loading() }
        is LoadState.Failed -> item { OrgFailed(state.message, reload, onExpired) }
        is LoadState.Ready -> ready(state.value)
    }
}

private fun String.orNull(): String? = trim().ifBlank { null }

// ---- overview --------------------------------------------------------------------------------------------

@Composable
internal fun OverviewTab(ctx: EventCtx, loader: Loader<OrganizerOverview>, onPreview: () -> Unit) {
    val brand = LocalBrand.current
    TabBody {
        loaded(loader.state.value, loader.reload, ctx.onExpired) { ov ->
            item {
                Card {
                    Column(verticalArrangement = Arrangement.spacedBy(8.dp)) {
                        Row(Modifier.fillMaxWidth().horizontalScroll(rememberScrollState()), horizontalArrangement = Arrangement.spacedBy(6.dp)) {
                            ov.timing?.let { OrgBadge(it.label, timingTone(it.state), "clock") }
                            OrgBadge(statusLabel(ov.status), statusTone(ov.status))
                            OrgBadge(if (ov.isVisible) "Visible" else "Hidden", if (ov.isVisible) Tone.Ok else Tone.Warn, if (ov.isVisible) "eye" else "eye-off")
                            OrgBadge(if (ctx.isAdmin) "Admin" else "Manager", Tone.Primary, if (ctx.isAdmin) "lock" else "briefcase")
                        }
                        Text(ov.name, style = MaterialTheme.typography.titleLarge)
                        Text(eventDates(ov.startsOn, ov.endsOn) + " · " + ov.timezone, style = MaterialTheme.typography.bodySmall)
                    }
                }
            }
            if (ov.counts.openReports > 0) item {
                AlertCard("siren", plural(ov.counts.openReports, "profile") + " reported", "Look at the reports and remove or dismiss them.", Tone.Danger, "Open reports") { ctx.open("reports") }
            }
            val delays = ov.delay.filter { it.minutes != 0 }
            if (delays.isNotEmpty()) item {
                AlertCard("clock", "Running late", delays.joinToString("\n") { it.text ?: "${it.minutes} min late" }, Tone.Warn, "Change") { ctx.open("delay") }
            }
            item { SectionTitle("At a glance") }
            item {
                StatGrid(buildList {
                    add(Stat("Attendees", ov.counts.roster.toString(), "users", Tone.Primary) { ctx.open("attendees") })
                    add(Stat("In discovery", ov.counts.discovery.toString(), "hand", Tone.Accent) { ctx.open("attendees") })
                    add(Stat("Open reports", ov.counts.openReports.toString(), "siren", if (ov.counts.openReports > 0) Tone.Danger else Tone.Ok) { ctx.open("reports") })
                    add(Stat("Sessions", ov.counts.sessions.toString(), "calendar", Tone.Muted))
                    add(Stat("Speakers", ov.counts.speakers.toString(), "mic", Tone.Muted))
                    add(Stat("Sponsors", ov.counts.sponsors.toString(), "briefcase", Tone.Muted))
                    add(Stat("Quests", ov.counts.quests.toString(), "star", Tone.Primary) { ctx.open("quests") })
                    add(Stat("Tables", ov.counts.tables.toString(), "wrench", Tone.Primary) { ctx.open("tables") })
                    if (ctx.isAdmin) add(Stat("Own deals", ov.counts.deals.toString(), "tag", Tone.Primary) { ctx.open("deals") })
                    else add(Stat("Push phones", ov.memories.subscribersCount.toString(), "bell", Tone.Muted) { ctx.open("notifications") })
                })
            }
            item { SectionTitle("Quick actions") }
            item {
                val actions = buildList {
                    add(Triple("Running late", "clock", "delay"))
                    add(Triple("Edit details", "pencil", "details"))
                    add(Triple("Venue & Wifi", "wifi", "info"))
                    add(Triple("Roles", "users", "attendees"))
                    add(Triple("Memories", "camera", "memories"))
                    add(Triple("Notifications", "bell", "notifications"))
                    add(Triple("Social media", "megaphone", "social"))
                    if (ctx.isAdmin) { add(Triple("Deals", "tag", "deals")); add(Triple("Deal leads", "inbox", "leads")); add(Triple("Activity", "activity", "activity")) }
                }
                Column(verticalArrangement = Arrangement.spacedBy(8.dp)) {
                    actions.chunked(2).forEach { row ->
                        Row(Modifier.fillMaxWidth(), horizontalArrangement = Arrangement.spacedBy(8.dp)) {
                            row.forEach { (label, icon, key) -> OrgButton(label, { ctx.open(key) }, Modifier.weight(1f), icon = icon) }
                            if (row.size == 1) Box(Modifier.weight(1f))
                        }
                    }
                    OrgButton("Open the attendee view", onPreview, Modifier.fillMaxWidth(), kind = BtnKind.Primary, icon = "eye")
                }
            }
            item {
                OrgSection("What attendees see", "map-pin", "From Event information") {
                    KeyValue("Venue", ov.info.venue)
                    KeyValue("Wifi", ov.info.wifi)
                    KeyValue("Registration", ov.info.registrationInfo)
                    KeyValue("Emergency", ov.info.emergencyContact)
                    KeyValue("Contributor Day", ov.info.contributorDayLocation)
                    if (listOf(ov.info.venue, ov.info.wifi, ov.info.registrationInfo, ov.info.emergencyContact).all { it.isNullOrBlank() }) Text("Nothing filled in yet.", style = MaterialTheme.typography.bodySmall)
                    OrgButton("Edit information", { ctx.open("info") }, icon = "pencil")
                }
            }
        }
    }
}

@Composable
private fun AlertCard(icon: String, title: String, text: String, tone: Tone, action: String, onAction: () -> Unit) {
    val brand = LocalBrand.current
    val color = if (tone == Tone.Danger) brand.danger else androidx.compose.ui.graphics.Color(0xFFB45309)
    val shape = RoundedCornerShape(brand.radius.dp)
    Row(
        Modifier.fillMaxWidth().background(color.copy(alpha = 0.08f), shape).border(1.dp, color.copy(alpha = 0.35f), shape).padding(14.dp),
        horizontalArrangement = Arrangement.spacedBy(12.dp), verticalAlignment = Alignment.CenterVertically,
    ) {
        LineIcon(icon, color, size = 22.dp)
        Column(Modifier.weight(1f)) {
            Text(title, style = MaterialTheme.typography.titleMedium.copy(color = color))
            Text(text, style = MaterialTheme.typography.bodySmall)
        }
        OrgButton(action, onAction)
    }
}

// ---- details ---------------------------------------------------------------------------------------------

@Composable
internal fun DetailsTab(ctx: EventCtx, onDeleted: () -> Unit) {
    val loader = rememberLoader("org-details:${ctx.eventId}") { Loaded(ctx.api.eventDetails(ctx.token, ctx.eventId), false) }
    TabBody {
        loaded(loader.state.value, loader.reload, ctx.onExpired) { d ->
            item(key = "form") { DetailsEditor(ctx, d) { loader.reload(); ctx.reloadOverview() } }
            if (ctx.isAdmin) {
                item(key = "data") { DataCard(ctx, d, loader.reload) }
                item(key = "danger") { DangerZone(ctx, d, onDeleted) }
            }
        }
    }
}

@Composable
private fun DetailsEditor(ctx: EventCtx, d: EventDetails, onSaved: () -> Unit) {
    val env = LocalEnv.current
    val actions = rememberOrgActions()
    var name by remember(d) { mutableStateOf(d.displayName) }
    var short by remember(d) { mutableStateOf(d.shortName.orEmpty()) }
    var slug by remember(d) { mutableStateOf(d.slug) }
    var site by remember(d) { mutableStateOf(d.sourceSiteUrl) }
    var starts by remember(d) { mutableStateOf(d.startsOn.orEmpty()) }
    var ends by remember(d) { mutableStateOf(d.endsOn.orEmpty()) }
    var zone by remember(d) { mutableStateOf(d.timezone.orEmpty()) }
    var visible by remember(d) { mutableStateOf(d.isVisible) }
    var status by remember(d) { mutableStateOf(d.status) }

    OrgSection("Event details", "pencil", if (ctx.isAdmin) "Everything about this WordCamp" else "Everything except the status, which an admin sets") {
        Row(horizontalArrangement = Arrangement.spacedBy(6.dp)) {
            d.timing?.let { OrgBadge(it.label, timingTone(it.state), "clock") }
            if (d.datesLocked) OrgBadge("Dates kept as typed", Tone.Muted, "lock")
            if (d.timezoneLocked) OrgBadge("Zone kept as typed", Tone.Muted, "lock")
        }
        OrgField("Display name", name, { name = it }, max = 255)
        OrgField("Short name", short, { short = it }, max = 60, help = "Optional, for small spaces")
        OrgField("URL slug", slug, { slug = it.lowercase().replace(' ', '-') }, max = 191, help = "campbuddy.club/event/${slug.ifBlank { "…" }}")
        OrgField("WordCamp site", site, { site = it }, keyboard = KeyboardType.Uri, max = 500, help = if (ctx.isAdmin) null else "A wordcamp.org address")
        Row(horizontalArrangement = Arrangement.spacedBy(8.dp)) {
            OrgDateField("Starts", starts, { starts = it }, Modifier.weight(1f))
            OrgDateField("Ends", ends, { ends = it }, Modifier.weight(1f))
        }
        Text("Typed dates are kept; leave both empty to take them from central.wordcamp.org again.", style = MaterialTheme.typography.bodySmall)
        OrgField("Time zone", zone, { zone = it }, placeholder = "Asia/Kolkata or +05:30", help = "Blank = read from the WordCamp site (now ${d.timezoneUsed})", max = 64)
        OrgToggle("Visible to attendees", visible, { visible = it }, "Hidden events are not listed in the picker")
        if (ctx.isAdmin) {
            OrgFilterRow("Status", listOf("draft" to "Draft", "approved" to "Approved", "active" to "Active", "archived" to "Archived"), status) { status = it }
        } else {
            Row(verticalAlignment = Alignment.CenterVertically, horizontalArrangement = Arrangement.spacedBy(8.dp)) {
                Text("Status", style = MaterialTheme.typography.bodySmall)
                OrgBadge(statusLabel(d.status), statusTone(d.status))
            }
        }
        Row(horizontalArrangement = Arrangement.spacedBy(8.dp)) {
            OrgButton(if (actions.busy) "Saving…" else "Save details", {
                actions.run({
                    ctx.api.saveEventDetails(ctx.token, ctx.eventId, DetailsForm(slug.trim(), name.trim(), short.orNull(), site.trim(), starts.orNull(), ends.orNull(), zone.orNull(), visible, status)).message
                }, onSaved)
            }, Modifier.weight(1f), kind = BtnKind.Primary, icon = "check", enabled = !actions.busy && name.isNotBlank() && slug.isNotBlank() && site.isNotBlank())
            d.publicUrl?.let { url -> OrgButton("Open page", { env.deps.openUrl(url) }, icon = "external") }
        }
    }
}

@Composable
private fun DataCard(ctx: EventCtx, d: EventDetails, reload: () -> Unit) {
    val actions = rememberOrgActions()
    var running by remember { mutableStateOf<String?>(null) }
    OrgSection("Data from the WordCamp site", "database", "What attendees see right now") {
        d.dataCounts?.let { c ->
            StatGrid(listOf(
                Stat("Sessions", c.sessions?.toString() ?: "—", "calendar", Tone.Muted),
                Stat("Speakers", c.speakers?.toString() ?: "—", "mic", Tone.Muted),
                Stat("Sponsors", c.sponsors?.toString() ?: "—", "briefcase", Tone.Muted),
                Stat("Organizers", c.organizers?.toString() ?: "—", "users", Tone.Muted),
                Stat("Attendees", c.attendees.toString(), "users", Tone.Primary),
            ))
        }
        Text("Each button fetches now and waits for the answer (up to two minutes).", style = MaterialTheme.typography.bodySmall)
        listOf(Triple("schedule", "Refresh schedule", "refresh"), Triple("info", "Fetch information", "map-pin"), Triple("branding", "Re-fetch branding", "camera")).forEach { (job, label, icon) ->
            OrgButton(if (actions.busy && running == job) "Working…" else label, {
                running = job
                actions.run({ ctx.api.runFetch(ctx.token, ctx.eventId, job).message }, { reload(); ctx.reloadOverview() })
            }, Modifier.fillMaxWidth(), icon = icon, enabled = !actions.busy)
        }
        if (d.recentFetches.isNotEmpty()) {
            Text("Recent fetches", style = MaterialTheme.typography.titleMedium.copy(fontSize = 15.sp))
            d.recentFetches.forEach { f ->
                Row(horizontalArrangement = Arrangement.spacedBy(8.dp), verticalAlignment = Alignment.Top) {
                    OrgBadge(f.status, levelTone(f.status))
                    Column(Modifier.weight(1f)) {
                        Text(f.job + (orgWhen(f.at)?.let { " · $it" } ?: ""), style = MaterialTheme.typography.bodySmall)
                        f.message?.let { Text(it, style = MaterialTheme.typography.bodyMedium, maxLines = 3) }
                    }
                }
            }
        }
    }
}

@Composable
private fun DangerZone(ctx: EventCtx, d: EventDetails, onDeleted: () -> Unit) {
    val actions = rememberOrgActions()
    var ask by remember { mutableStateOf(false) }
    OrgSection("Delete this event", "trash", "Removes the event and everything attendees saved on the server for it") {
        OrgButton("Delete event…", { ask = true }, Modifier.fillMaxWidth(), kind = BtnKind.Danger, icon = "trash")
    }
    if (ask) ConfirmSheet(
        "Delete ${d.displayName}?", "This cannot be undone. Its quests, deals, tables, reports and attendee list go with it.", "Delete",
        onConfirm = { actions.run({ ctx.api.deleteEvent(ctx.token, ctx.eventId, d.slug).message }, onDeleted) },
        onClose = { ask = false }, typeToConfirm = d.slug,
    )
}

// ---- information -----------------------------------------------------------------------------------------

@Composable
internal fun InformationTab(ctx: EventCtx) {
    val loader = rememberLoader("org-info:${ctx.eventId}") { Loaded(ctx.api.eventInformation(ctx.token, ctx.eventId), false) }
    TabBody {
        loaded(loader.state.value, loader.reload, ctx.onExpired) { info -> item { InformationEditor(ctx, info) { loader.reload(); ctx.reloadOverview() } } }
    }
}

@Composable
private fun InformationEditor(ctx: EventCtx, info: EventInformation, onSaved: () -> Unit) {
    val actions = rememberOrgActions()
    var venue by remember(info) { mutableStateOf(info.venue.orEmpty()) }
    var wifi by remember(info) { mutableStateOf(info.wifi.orEmpty()) }
    var registration by remember(info) { mutableStateOf(info.registrationInfo.orEmpty()) }
    var emergency by remember(info) { mutableStateOf(info.emergencyContact.orEmpty()) }
    var contributor by remember(info) { mutableStateOf(info.contributorDayLocation.orEmpty()) }
    var social by remember(info) { mutableStateOf(info.socialEventInfo.orEmpty()) }
    var nearby by remember(info) { mutableStateOf(info.nearbyVenueInfo.orEmpty()) }
    var links by remember(info) { mutableStateOf(info.importantLinks.orEmpty()) }
    var coc by remember(info) { mutableStateOf(info.codeOfConductUrl.orEmpty()) }

    OrgSection("Event information", "map-pin", "Shown on every attendee's Info page; an empty field is left out") {
        OrgField("Venue", venue, { venue = it }, max = 255, placeholder = "B. M. Birla Auditorium, Jaipur")
        OrgField("Wifi", wifi, { wifi = it }, lines = 2, max = 500, placeholder = "Network: WCJaipur · Password: wp2026")
        OrgField("Registration desk", registration, { registration = it }, lines = 3, max = 1000)
        OrgField("Emergency contact & helpdesk", emergency, { emergency = it }, lines = 2, max = 500)
        OrgField("Contributor Day location", contributor, { contributor = it }, max = 255)
        OrgField("Social event", social, { social = it }, lines = 3, max = 1000)
        OrgField("Nearby: food, transport, stay", nearby, { nearby = it }, lines = 3, max = 1000)
        OrgField("Important links", links, { links = it }, lines = 3, max = 2000, help = "One per line")
        OrgField("Code of conduct URL", coc, { coc = it }, keyboard = KeyboardType.Uri, max = 500)
        OrgButton(if (actions.busy) "Saving…" else "Save information", {
            actions.run({
                ctx.api.saveEventInformation(ctx.token, ctx.eventId, EventInformation(venue.orNull(), wifi.orNull(), registration.orNull(), emergency.orNull(), contributor.orNull(), social.orNull(), nearby.orNull(), links.orNull(), coc.orNull())).message
            }, onSaved)
        }, Modifier.fillMaxWidth(), kind = BtnKind.Primary, icon = "check", enabled = !actions.busy)
    }
}

// ---- running late ----------------------------------------------------------------------------------------

@Composable
internal fun DelayTab(ctx: EventCtx) {
    val loader = rememberLoader("org-delay:${ctx.eventId}") { Loaded(ctx.api.delay(ctx.token, ctx.eventId), false) }
    val actions = rememberOrgActions()
    var minutes by remember { mutableStateOf("15") }
    var track by remember { mutableStateOf("") }
    var note by remember { mutableStateOf("") }
    var day by remember { mutableStateOf("") }
    var time by remember { mutableStateOf("") }
    var askClear by remember { mutableStateOf(false) }
    val ready = (loader.state.value as? LoadState.Ready)?.value

    // "From" starts as now at the venue, to the minute (the web form's default).
    androidx.compose.runtime.LaunchedEffect(ready?.timezone) {
        if (ready != null && day.isBlank()) {
            val now = Clock.System.now().toLocalDateTime(EventClock.zone(ready.timezone))
            day = now.date.toString()
            time = now.hour.toString().padStart(2, '0') + ":" + now.minute.toString().padStart(2, '0')
        }
    }

    TabBody {
        loaded(loader.state.value, loader.reload, ctx.onExpired) { data ->
            item {
                OrgSection("Right now", "clock", "Times on every attendee's phone include the delay") {
                    val active = data.active.filter { it.minutes != 0 }
                    if (active.isEmpty()) OrgBadge("On time", Tone.Ok, "check")
                    active.forEach { OrgBadge(it.text ?: "${it.minutes} min late", Tone.Warn, "clock") }
                    if (active.isNotEmpty()) OrgButton("Back on time", { askClear = true }, Modifier.fillMaxWidth(), kind = BtnKind.Danger, icon = "check")
                }
            }
            item {
                OrgSection("Set a delay", "siren", "Venue time (${data.timezone})") {
                    if (data.tracks.isNotEmpty()) OrgFilterRow("Track", listOf("" to "Whole event") + data.tracks.map { it to it }, track) { track = it }
                    OrgFilterRow("Minutes late", listOf("5", "10", "15", "20", "30", "45", "60").map { it to "+$it" }, minutes) { minutes = it }
                    OrgField("Minutes (0 clears)", minutes, { minutes = it.filter(Char::isDigit).take(3) }, keyboard = KeyboardType.Number, help = "Up to 240")
                    Row(horizontalArrangement = Arrangement.spacedBy(8.dp)) {
                        OrgDateField("From day", day, { day = it }, Modifier.weight(1f))
                        OrgTimeField("From time", time, { time = it }, Modifier.weight(1f))
                    }
                    OrgField("Note for attendees", note, { note = it }, max = 140, placeholder = "Lunch ran over")
                    OrgButton(if (actions.busy) "Saving…" else "Set delay", {
                        actions.run({
                            ctx.api.setDelay(ctx.token, ctx.eventId, DelayRequest(minutes.toIntOrNull() ?: 0, track, "${day}T$time", note.orNull()))
                            "Delay saved. Every attendee's phone shows the new times."
                        }, { loader.reload(); ctx.reloadOverview() })
                    }, Modifier.fillMaxWidth(), kind = BtnKind.Primary, icon = "clock", enabled = !actions.busy && day.isNotBlank() && time.isNotBlank())
                }
            }
        }
    }
    if (askClear) ConfirmSheet("Back on time?", "Clears every delay; the schedule shows its own times again.", "Clear delay", danger = false, onConfirm = {
        actions.run({ ctx.api.clearDelay(ctx.token, ctx.eventId); "Schedule is back on time." }, { loader.reload(); ctx.reloadOverview() })
    }, onClose = { askClear = false })
}

// ---- reports ---------------------------------------------------------------------------------------------

@Composable
internal fun ReportsTab(ctx: EventCtx) {
    val loader = rememberLoader("org-reports:${ctx.eventId}") { Loaded(ctx.api.reports(ctx.token, ctx.eventId), false) }
    val actions = rememberOrgActions()
    var removing by remember { mutableStateOf<Pair<String, String>?>(null) }
    TabBody {
        loaded(loader.state.value, loader.reload, ctx.onExpired) { data ->
            item { SectionTitle("Open (${data.open.size})") }
            if (data.open.isEmpty()) item { EmptyNote("No profile is reported. Attendees can report a profile from People.", "check") }
            items(data.open, key = { it.discoveryId }) { report ->
                Card {
                    Column(verticalArrangement = Arrangement.spacedBy(8.dp)) {
                        Row(verticalAlignment = Alignment.CenterVertically, horizontalArrangement = Arrangement.spacedBy(8.dp)) {
                            Column(Modifier.weight(1f)) {
                                Text(report.profile?.name ?: "Unnamed profile", style = MaterialTheme.typography.titleMedium)
                                report.profile?.profession?.let { Text(it, style = MaterialTheme.typography.bodySmall) }
                            }
                            OrgBadge(plural(report.reports.size, "report"), Tone.Danger)
                        }
                        report.reports.forEach { r ->
                            Column(Modifier.fillMaxWidth().background(LocalBrand.current.background, RoundedCornerShape(8.dp)).padding(10.dp)) {
                                Text(r.label, style = MaterialTheme.typography.titleMedium.copy(fontSize = 14.sp))
                                r.note?.let { Text("“$it”", style = MaterialTheme.typography.bodyMedium) }
                                orgWhen(r.at)?.let { Text(it, style = MaterialTheme.typography.bodySmall) }
                            }
                        }
                        if (!report.profileStillThere) Text("The profile is already gone.", style = MaterialTheme.typography.bodySmall)
                        Row(horizontalArrangement = Arrangement.spacedBy(8.dp)) {
                            if (report.profileStillThere) OrgButton("Remove profile", { removing = report.discoveryId to (report.profile?.name ?: "this profile") }, Modifier.weight(1f), kind = BtnKind.Danger, icon = "trash", enabled = !actions.busy)
                            OrgButton("Dismiss", { actions.run({ ctx.api.dismissReported(ctx.token, ctx.eventId, report.discoveryId); "Reports dismissed." }, { loader.reload(); ctx.reloadOverview() }) }, Modifier.weight(1f), icon = "check", enabled = !actions.busy)
                        }
                    }
                }
            }
            if (data.handled.isNotEmpty()) {
                item { SectionTitle("Handled lately") }
                items(data.handled) { h ->
                    Card {
                        Row(verticalAlignment = Alignment.CenterVertically, horizontalArrangement = Arrangement.spacedBy(8.dp)) {
                            Column(Modifier.weight(1f)) {
                                Text(h.profile?.name ?: "Unnamed profile", style = MaterialTheme.typography.titleMedium.copy(fontSize = 15.sp))
                                Text(h.reason.replace('_', ' ') + (orgWhen(h.at)?.let { " · $it" } ?: ""), style = MaterialTheme.typography.bodySmall)
                            }
                            OrgBadge(statusLabel(h.status), if (h.status == "removed") Tone.Danger else Tone.Muted)
                        }
                    }
                }
            }
        }
    }
    removing?.let { (id, who) ->
        ConfirmSheet("Remove $who?", "The profile disappears from every phone within seconds and its reports are closed.", "Remove", onConfirm = {
            actions.run({ ctx.api.removeReported(ctx.token, ctx.eventId, id); "Profile removed." }, { loader.reload(); ctx.reloadOverview() })
        }, onClose = { removing = null })
    }
}

// ---- attendees -------------------------------------------------------------------------------------------

private val ROLE_FALLBACK = listOf(
    KeyLabel("organizer", "Organizer"), KeyLabel("speaker", "Speaker"), KeyLabel("volunteer", "Volunteer"), KeyLabel("microsponsor", "Microsponsor"),
    KeyLabel("sponsor", "Sponsor"), KeyLabel("media_partner", "Media Partner"), KeyLabel("table_lead", "Table Lead"),
)

@Composable
internal fun AttendeesTab(ctx: EventCtx) {
    var query by remember { mutableStateOf("") }
    var role by remember { mutableStateOf<String?>(null) }
    var status by remember { mutableStateOf<String?>(null) }
    var page by remember { mutableStateOf(1) }
    var stamp by remember { mutableStateOf(0) }
    var open by remember { mutableStateOf<OrganizerAttendeeEntry?>(null) }
    val searched = rememberDebounced(query)
    val loader = rememberLoader("org-attendees:${ctx.eventId}:$searched:$role:$status:$page:$stamp") {
        Loaded(ctx.api.organizerAttendeePage(ctx.token, ctx.eventId, searched, role, status, page), false)
    }
    val roles = (loader.state.value as? LoadState.Ready)?.value?.meta?.roles?.takeIf { it.isNotEmpty() } ?: ROLE_FALLBACK

    TabBody {
        item { OrgSearch(query, { query = it; page = 1 }, "Search attendee name") }
        item { OrgFilterRow("Role", listOf<String?>(null).map { it to "Everyone" } + listOf<Pair<String?, String>>("none" to "No role") + roles.map { it.key to it.text }, role) { role = it; page = 1 } }
        if (ctx.isAdmin) item { OrgFilterRow("List", listOf<Pair<String?, String>>(null to "Listed", "suppressed" to "Suppressed", "all" to "All"), status) { status = it; page = 1 } }
        loaded(loader.state.value, loader.reload, ctx.onExpired) { result ->
            item { Text(plural(result.meta.total, "attendee") + (if (searched.isNotBlank()) " for “$searched”" else ""), style = MaterialTheme.typography.bodySmall) }
            if (result.data.isEmpty()) item { EmptyNote("Nobody here. The attendee list comes from the WordCamp site's tickets.", "users") }
            items(result.data, key = { it.id }) { entry -> AttendeeRow(entry, roles) { open = entry } }
            item { OrgPager(result.meta) { page = it } }
        }
    }
    open?.let { entry -> AttendeeSheet(ctx, entry, roles, onClose = { open = null }, onChanged = { open = null; stamp++ }) }
}

@OptIn(ExperimentalLayoutApi::class)
@Composable
private fun AttendeeRow(entry: OrganizerAttendeeEntry, roles: List<KeyLabel>, onClick: () -> Unit) {
    val brand = LocalBrand.current
    Card(onClick = onClick) {
        Row(verticalAlignment = Alignment.CenterVertically, horizontalArrangement = Arrangement.spacedBy(12.dp)) {
            Box(Modifier.size(42.dp).clip(CircleShape).background(brand.peach), contentAlignment = Alignment.Center) {
                RemoteImage(entry.avatarUrl, Modifier.size(42.dp)) { Text(entry.name.take(1).uppercase(), style = MaterialTheme.typography.titleMedium.copy(color = brand.primary)) }
            }
            Column(Modifier.weight(1f), verticalArrangement = Arrangement.spacedBy(4.dp)) {
                Text(entry.name, style = MaterialTheme.typography.titleMedium.copy(fontSize = 15.sp))
                FlowRow(horizontalArrangement = Arrangement.spacedBy(4.dp), verticalArrangement = Arrangement.spacedBy(4.dp)) {
                    if (entry.isSuppressed) OrgBadge("Suppressed", Tone.Danger, "eye-off")
                    entry.roles.forEach { r -> OrgBadge(roles.firstOrNull { it.key == r }?.text ?: r, Tone.Primary) }
                    if (entry.roles.isEmpty() && !entry.isSuppressed) OrgBadge("Attendee", Tone.Muted)
                    if (entry.inDiscovery) OrgBadge("In People", Tone.Accent, "hand")
                    if (entry.hasCampCard) OrgBadge("Camp Card", Tone.Muted, "id-card")
                }
            }
            LineIcon("chevron-right", brand.muted, size = 18.dp)
        }
    }
}

@Composable
private fun AttendeeSheet(ctx: EventCtx, entry: OrganizerAttendeeEntry, roles: List<KeyLabel>, onClose: () -> Unit, onChanged: () -> Unit) {
    val actions = rememberOrgActions()
    var chosen by remember(entry) { mutableStateOf(entry.roles.toSet()) }
    Sheet(entry.name, onClose, actions = {
        if (!entry.isSuppressed) SheetActions(if (actions.busy) "Saving…" else "Save roles", {
            actions.run({ ctx.api.updateOrganizerAttendeeRoles(ctx.token, ctx.eventId, entry.id, roles.map { it.key }.filter { it in chosen }); "Roles of ${entry.name} saved." }, onChanged)
        }, "Cancel", onClose, enabled = !actions.busy)
    }) {
        if (entry.isSuppressed) {
            Text("Suppressed: not on the public attendee list. Restore to change roles.", style = MaterialTheme.typography.bodyMedium)
        } else {
            Text("Roles show as badges on the attendee list and in People. Ones found automatically are marked auto; switching one off overrides it.", style = MaterialTheme.typography.bodySmall)
            roles.forEach { r ->
                val auto = r.key in entry.autoRoles
                OrgToggle(r.text + if (auto) "  · auto" else "", r.key in chosen, { on -> chosen = if (on) chosen + r.key else chosen - r.key })
            }
        }
        if (entry.talks.isNotEmpty()) {
            Text("Talks", style = MaterialTheme.typography.titleMedium.copy(fontSize = 15.sp))
            entry.talks.forEach { Text("• $it", style = MaterialTheme.typography.bodyMedium) }
        }
        if (ctx.isAdmin) {
            Text("Admin", style = MaterialTheme.typography.titleMedium.copy(fontSize = 15.sp))
            if (entry.isSuppressed) {
                OrgButton("Restore to the list", { actions.run({ ctx.api.attendeeAction(ctx.token, ctx.eventId, entry.id, "unsuppress").message }, onChanged) }, Modifier.fillMaxWidth(), icon = "eye")
            } else {
                OrgButton("Suppress from the list", { actions.run({ ctx.api.attendeeAction(ctx.token, ctx.eventId, entry.id, "suppress").message }, onChanged) }, Modifier.fillMaxWidth(), kind = BtnKind.Danger, icon = "eye-off")
            }
            if (entry.inDiscovery) OrgButton("Unlink from People profile", { actions.run({ ctx.api.attendeeAction(ctx.token, ctx.eventId, entry.id, "release-claim").message }, onChanged) }, Modifier.fillMaxWidth(), icon = "hand")
            if (entry.hasCampCard) OrgButton("Remove shared Camp Card", { actions.run({ ctx.api.attendeeAction(ctx.token, ctx.eventId, entry.id, "remove-card").message }, onChanged) }, Modifier.fillMaxWidth(), kind = BtnKind.Danger, icon = "id-card")
        }
    }
}

// ---- quests ----------------------------------------------------------------------------------------------

@Composable
internal fun QuestsTab(ctx: EventCtx) {
    var stamp by remember { mutableStateOf(0) }
    val loader = rememberLoader("org-quests:${ctx.eventId}:$stamp") { Loaded(ctx.api.orgQuests(ctx.token, ctx.eventId), false) }
    val actions = rememberOrgActions()
    var editing by remember { mutableStateOf<OrgQuest?>(null) }
    var adding by remember { mutableStateOf(false) }
    var deleting by remember { mutableStateOf<OrgQuest?>(null) }
    var showHidden by remember { mutableStateOf(true) }

    TabBody {
        item { OrgButton("Add checklist item", { adding = true }, Modifier.fillMaxWidth(), kind = BtnKind.Primary, icon = "plus") }
        item { OrgFilterRow(null, listOf(true to "All items", false to "Shown only"), showHidden) { showHidden = it } }
        loaded(loader.state.value, loader.reload, ctx.onExpired) { quests ->
            val list = if (showHidden) quests else quests.filter { it.isActive }
            item { Text("${quests.count { it.isActive }} shown · ${quests.count { !it.isActive }} hidden", style = MaterialTheme.typography.bodySmall) }
            if (list.isEmpty()) item { EmptyNote("No quests yet. Add the first checklist item.", "star") }
            items(list, key = { it.id }) { q ->
                Card {
                    Column(verticalArrangement = Arrangement.spacedBy(6.dp)) {
                        Row(verticalAlignment = Alignment.Top, horizontalArrangement = Arrangement.spacedBy(8.dp)) {
                            Column(Modifier.weight(1f)) {
                                Text(q.title, style = MaterialTheme.typography.titleMedium.copy(fontSize = 15.sp))
                                q.description?.let { Text(it, style = MaterialTheme.typography.bodySmall, maxLines = 3) }
                            }
                            Text("#${q.sortOrder}", style = MaterialTheme.typography.bodySmall)
                        }
                        Row(horizontalArrangement = Arrangement.spacedBy(6.dp), verticalAlignment = Alignment.CenterVertically) {
                            OrgBadge(if (q.isActive) "Shown" else "Hidden", if (q.isActive) Tone.Ok else Tone.Muted, if (q.isActive) "eye" else "eye-off")
                            if (q.source != "event") OrgBadge("Default list", Tone.Muted)
                            Box(Modifier.weight(1f))
                            OrgIconButton(if (q.isActive) "eye-off" else "eye", if (q.isActive) "Hide" else "Show", {
                                actions.run({ ctx.api.updateQuest(ctx.token, ctx.eventId, q.id, QuestForm(q.title, q.description, q.sortOrder, !q.isActive)).message }, { stamp++ })
                            })
                            OrgIconButton("pencil", "Edit", { editing = q })
                            OrgIconButton("trash", "Remove", { deleting = q }, tint = LocalBrand.current.danger)
                        }
                    }
                }
            }
        }
    }
    if (adding || editing != null) QuestSheet(ctx, editing, onClose = { adding = false; editing = null }, onSaved = { adding = false; editing = null; stamp++; ctx.reloadOverview() })
    deleting?.let { q ->
        ConfirmSheet("Remove “${q.title}”?", "Attendees who ticked it keep nothing of it. To keep it for later, hide it instead.", "Remove", onConfirm = {
            actions.run({ ctx.api.deleteQuest(ctx.token, ctx.eventId, q.id).message }, { stamp++; ctx.reloadOverview() })
        }, onClose = { deleting = null })
    }
}

@Composable
private fun QuestSheet(ctx: EventCtx, quest: OrgQuest?, onClose: () -> Unit, onSaved: () -> Unit) {
    val actions = rememberOrgActions()
    var title by remember { mutableStateOf(quest?.title.orEmpty()) }
    var description by remember { mutableStateOf(quest?.description.orEmpty()) }
    var order by remember { mutableStateOf(quest?.sortOrder?.toString().orEmpty()) }
    var active by remember { mutableStateOf(quest?.isActive ?: true) }
    Sheet(if (quest == null) "New checklist item" else "Edit checklist item", onClose, actions = {
        SheetActions(if (actions.busy) "Saving…" else "Save", {
            val form = QuestForm(title.trim(), description.orNull(), order.toIntOrNull(), active)
            actions.run({ if (quest == null) ctx.api.addQuest(ctx.token, ctx.eventId, form).message else ctx.api.updateQuest(ctx.token, ctx.eventId, quest.id, form).message }, onSaved)
        }, "Cancel", onClose, enabled = title.isNotBlank() && !actions.busy)
    }) {
        OrgField("Title", title, { title = it }, max = 255)
        OrgField("Description", description, { description = it }, lines = 3, max = 1000)
        OrgField("Position", order, { order = it.filter(Char::isDigit).take(5) }, keyboard = KeyboardType.Number, help = "Lower comes first; empty = at the end")
        OrgToggle("Shown to attendees", active, { active = it })
    }
}

// ---- contributor tables ----------------------------------------------------------------------------------

@Composable
internal fun TablesTab(ctx: EventCtx) {
    var stamp by remember { mutableStateOf(0) }
    val loader = rememberLoader("org-tables:${ctx.eventId}:$stamp") { Loaded(ctx.api.orgTables(ctx.token, ctx.eventId), false) }
    val actions = rememberOrgActions()
    var editing by remember { mutableStateOf<OrgTable?>(null) }
    var adding by remember { mutableStateOf(false) }
    var deleting by remember { mutableStateOf<OrgTable?>(null) }
    val data = (loader.state.value as? LoadState.Ready)?.value

    TabBody {
        item { OrgButton("Add table", { adding = true }, Modifier.fillMaxWidth(), kind = BtnKind.Primary, icon = "plus", enabled = data != null) }
        loaded(loader.state.value, loader.reload, ctx.onExpired) { d ->
            if (d.tables.isEmpty()) item { EmptyNote("No Contributor Day tables yet. Attendees see them in Contribute.", "wrench") }
            items(d.tables, key = { it.id }) { t ->
                Card {
                    Column(verticalArrangement = Arrangement.spacedBy(6.dp)) {
                        Row(verticalAlignment = Alignment.CenterVertically, horizontalArrangement = Arrangement.spacedBy(6.dp)) {
                            Text(t.name, style = MaterialTheme.typography.titleMedium.copy(fontSize = 15.sp), modifier = Modifier.weight(1f))
                            if (t.source == "site") OrgBadge("From the site", Tone.Muted, "globe")
                        }
                        if (t.place.isNotBlank()) Text(t.place, style = MaterialTheme.typography.bodySmall)
                        if (t.leads.isNotBlank()) Text("Leads: ${t.leads}", style = MaterialTheme.typography.bodyMedium)
                        t.note?.let { Text(it, style = MaterialTheme.typography.bodySmall) }
                        Row(horizontalArrangement = Arrangement.spacedBy(6.dp), verticalAlignment = Alignment.CenterVertically) {
                            Text("#${t.sortOrder}", style = MaterialTheme.typography.bodySmall, modifier = Modifier.weight(1f))
                            OrgIconButton("pencil", "Edit", { editing = t })
                            OrgIconButton("trash", "Remove", { deleting = t }, tint = LocalBrand.current.danger)
                        }
                    }
                }
            }
        }
    }
    if (data != null && (adding || editing != null)) TableSheet(ctx, editing, data.teams, data.names, onClose = { adding = false; editing = null }, onSaved = { adding = false; editing = null; stamp++; ctx.reloadOverview() })
    deleting?.let { t ->
        ConfirmSheet("Remove ${t.name}?", "It disappears from Contribute, and its leads lose the Table Lead badge.", "Remove", onConfirm = {
            actions.run({ ctx.api.deleteTable(ctx.token, ctx.eventId, t.id).message }, { stamp++; ctx.reloadOverview() })
        }, onClose = { deleting = null })
    }
}

@OptIn(ExperimentalLayoutApi::class)
@Composable
private fun TableSheet(ctx: EventCtx, table: OrgTable?, teams: List<KeyLabel>, names: List<String>, onClose: () -> Unit, onSaved: () -> Unit) {
    val actions = rememberOrgActions()
    var team by remember { mutableStateOf(table?.team ?: teams.firstOrNull()?.key.orEmpty()) }
    var title by remember { mutableStateOf(table?.title.orEmpty()) }
    var track by remember { mutableStateOf(table?.track.orEmpty()) }
    var floor by remember { mutableStateOf(table?.floor.orEmpty()) }
    var number by remember { mutableStateOf(table?.tableNo.orEmpty()) }
    var leads by remember { mutableStateOf(table?.leads.orEmpty()) }
    var note by remember { mutableStateOf(table?.note.orEmpty()) }
    var order by remember { mutableStateOf(table?.sortOrder?.toString().orEmpty()) }
    // The name being typed after the last comma, matched against the attendee list.
    val typing = leads.substringAfterLast(',').trim()
    val suggestions = if (typing.length >= 2) names.filter { it.contains(typing, ignoreCase = true) && it != typing }.take(6) else emptyList()

    Sheet(if (table == null) "New table" else "Edit table", onClose, actions = {
        SheetActions(if (actions.busy) "Saving…" else "Save", {
            val form = TableForm(team, title.orNull(), track.orNull(), floor.orNull(), number.orNull(), leads.orNull(), note.orNull(), order.toIntOrNull())
            actions.run({ if (table == null) ctx.api.addTable(ctx.token, ctx.eventId, form).message else ctx.api.updateTable(ctx.token, ctx.eventId, table.id, form).message }, onSaved)
        }, "Cancel", onClose, enabled = team.isNotBlank() && (team != "other" || title.isNotBlank()) && !actions.busy)
    }) {
        Text("Team", style = MaterialTheme.typography.titleMedium.copy(fontSize = 15.sp))
        FlowRow(horizontalArrangement = Arrangement.spacedBy(6.dp), verticalArrangement = Arrangement.spacedBy(6.dp)) {
            teams.forEach { t -> CbChip(selected = t.key == team, onClick = { team = t.key }, label = { Text(t.text) }) }
        }
        OrgField(if (team == "other") "Table name (required)" else "Title (optional)", title, { title = it }, max = 80)
        Row(horizontalArrangement = Arrangement.spacedBy(8.dp)) {
            OrgField("Floor", floor, { floor = it }, Modifier.weight(1f), max = 40)
            OrgField("Table no.", number, { number = it }, Modifier.weight(1f), max = 20)
        }
        OrgField("Room / track", track, { track = it }, max = 80)
        OrgField("Table leads", leads, { leads = it }, lines = 2, max = 500, help = "Comma-separated; names on the attendee list get the Table Lead badge")
        if (suggestions.isNotEmpty()) FlowRow(horizontalArrangement = Arrangement.spacedBy(6.dp), verticalArrangement = Arrangement.spacedBy(6.dp)) {
            suggestions.forEach { name ->
                CbChip(selected = false, onClick = {
                    val before = leads.substringBeforeLast(',', "").trim()
                    leads = (if (before.isEmpty()) "" else "$before, ") + name + ", "
                }, label = { Text(name) })
            }
        }
        OrgField("Note", note, { note = it }, lines = 2, max = 300)
        OrgField("Position", order, { order = it.filter(Char::isDigit).take(4) }, keyboard = KeyboardType.Number)
    }
}

// ---- memories --------------------------------------------------------------------------------------------

@Composable
internal fun MemoriesOrgTab(ctx: EventCtx) {
    var stamp by remember { mutableStateOf(0) }
    val loader = rememberLoader("org-memories:${ctx.eventId}:$stamp") { Loaded(ctx.api.organizerOverview(ctx.token, ctx.eventId), false) }
    TabBody {
        loaded(loader.state.value, loader.reload, ctx.onExpired) { ov -> item { MemoriesEditor(ctx, ov) { stamp++ } } }
    }
}

@Composable
private fun MemoriesEditor(ctx: EventCtx, ov: OrganizerOverview, onSaved: () -> Unit) {
    val actions = rememberOrgActions()
    val m = ov.memories
    var photos by remember(m) { mutableStateOf(m.photoAlbumUrl.orEmpty()) }
    var videos by remember(m) { mutableStateOf(m.wordpressTvUrl.orEmpty()) }
    var notify by remember { mutableStateOf<String?>(null) }

    OrgSection("Photos & talk videos", "camera", "Attendees see them on Memories after the event") {
        OrgField("Photo album link", photos, { photos = it }, keyboard = KeyboardType.Uri, max = 500, placeholder = "https://photos.google.com/…")
        OrgField("WordPress.tv link", videos, { videos = it }, keyboard = KeyboardType.Uri, max = 500, placeholder = "https://wordpress.tv/event/…")
        OrgButton(if (actions.busy) "Saving…" else "Save links", {
            actions.run({ ctx.api.updateOrganizerMemories(ctx.token, ctx.eventId, UpdateMemoriesRequest(photos.orNull(), videos.orNull())); "Links saved." }, onSaved)
        }, Modifier.fillMaxWidth(), kind = BtnKind.Primary, icon = "check", enabled = !actions.busy)
    }
    Box(Modifier.padding(top = 12.dp)) {
        OrgSection("Tell attendees", "bell", plural(m.subscribersCount, "phone") + " get event notifications") {
            NotifyRow("Photos", m.photoAlbumUrl, m.photoAlbumNotifiedAt) { notify = "photos" }
            NotifyRow("Talk videos", m.wordpressTvUrl, m.wordpressTvNotifiedAt) { notify = "videos" }
        }
    }
    notify?.let { kind ->
        ConfirmSheet("Send the ${if (kind == "photos") "photos" else "videos"} notification?", "One push to ${plural(m.subscribersCount, "phone")}. Send it once the link works.", "Send", danger = false, onConfirm = {
            actions.run({ ctx.api.notifyOrganizerMemories(ctx.token, ctx.eventId, kind).message }, onSaved)
        }, onClose = { notify = null })
    }
}

@Composable
private fun NotifyRow(label: String, url: String?, sentAt: String?, onSend: () -> Unit) {
    Row(verticalAlignment = Alignment.CenterVertically, horizontalArrangement = Arrangement.spacedBy(8.dp)) {
        Column(Modifier.weight(1f)) {
            Text(label, style = MaterialTheme.typography.titleMedium.copy(fontSize = 15.sp))
            Text(
                when {
                    sentAt != null -> "Sent " + (orgWhen(sentAt) ?: "")
                    url.isNullOrBlank() -> "Save the link first"
                    else -> "Ready to send"
                },
                style = MaterialTheme.typography.bodySmall,
            )
        }
        OrgButton(if (sentAt != null) "Send again" else "Send", onSend, icon = "bell", enabled = !url.isNullOrBlank())
    }
}

// ---- notifications ---------------------------------------------------------------------------------------

@Composable
internal fun NotificationsTab(ctx: EventCtx) {
    val loader = rememberLoader("org-notifications:${ctx.eventId}") { Loaded(ctx.api.eventNotifications(ctx.token, ctx.eventId), false) }
    TabBody {
        item { Text("Every push CampBuddy sends about this WordCamp, and whether it went out. Photos and videos are sent from Memories.", style = MaterialTheme.typography.bodySmall) }
        loaded(loader.state.value, loader.reload, ctx.onExpired) { rows ->
            items(rows, key = { it.key }) { n ->
                Card {
                    Column(verticalArrangement = Arrangement.spacedBy(6.dp)) {
                        Row(verticalAlignment = Alignment.CenterVertically, horizontalArrangement = Arrangement.spacedBy(8.dp)) {
                            Text(n.title, style = MaterialTheme.typography.titleMedium.copy(fontSize = 15.sp), modifier = Modifier.weight(1f))
                            OrgBadge(statusLabel(n.state), levelTone(n.state))
                        }
                        Text(n.`when`, style = MaterialTheme.typography.bodySmall)
                        Text(n.text, style = MaterialTheme.typography.bodyMedium)
                        Text(n.status, style = MaterialTheme.typography.bodySmall.copy(fontWeight = FontWeight.SemiBold))
                    }
                }
            }
        }
    }
}

// ---- deals (admin) ---------------------------------------------------------------------------------------

@Composable
internal fun DealsTab(ctx: EventCtx) {
    var stamp by remember { mutableStateOf(0) }
    val loader = rememberLoader("org-deals:${ctx.eventId}:$stamp") { Loaded(ctx.api.orgDeals(ctx.token, ctx.eventId), false) }
    val actions = rememberOrgActions()
    var editing by remember { mutableStateOf<OrgDeal?>(null) }
    var adding by remember { mutableStateOf(false) }
    var deleting by remember { mutableStateOf<OrgDeal?>(null) }

    TabBody {
        item { OrgButton("Add a deal for this WordCamp", { adding = true }, Modifier.fillMaxWidth(), kind = BtnKind.Primary, icon = "plus") }
        loaded(loader.state.value, loader.reload, ctx.onExpired) { d ->
            item { SectionTitle("This WordCamp's deals (${d.own.size})") }
            if (d.own.isEmpty()) item { EmptyNote("No deals of its own. Default deals for the country still show.", "tag") }
            items(d.own, key = { "own-${it.id}" }) { deal ->
                DealCard(deal) {
                    OrgIconButton(if (deal.isActive) "eye-off" else "eye", if (deal.isActive) "Switch off" else "Switch on", {
                        actions.run({ ctx.api.updateDeal(ctx.token, ctx.eventId, deal.id, deal.toForm(isActive = !deal.isActive)).message }, { stamp++ })
                    })
                    OrgIconButton("pencil", "Edit", { editing = deal })
                    OrgIconButton("trash", "Remove", { deleting = deal }, tint = LocalBrand.current.danger)
                }
            }
            item { SectionTitle("Default deals here (${d.defaults.size})") }
            item { Text("Set in Admin → Default deals on the website; here you can hide one at this WordCamp.", style = MaterialTheme.typography.bodySmall) }
            items(d.defaults, key = { "def-${it.id}" }) { deal ->
                DealCard(deal, extra = deal.countries) {
                    OrgButton(if (deal.hiddenHere) "Show here" else "Hide here", {
                        actions.run({ ctx.api.dealVisibility(ctx.token, ctx.eventId, deal.id, !deal.hiddenHere).message }, { stamp++ })
                    }, icon = if (deal.hiddenHere) "eye" else "eye-off")
                }
            }
        }
    }
    if (adding || editing != null) DealSheet(ctx, editing, onClose = { adding = false; editing = null }, onSaved = { adding = false; editing = null; stamp++; ctx.reloadOverview() })
    deleting?.let { deal ->
        ConfirmSheet("Remove ${deal.name}?", "Its leads stay in Deal leads.", "Remove", onConfirm = {
            actions.run({ ctx.api.deleteDeal(ctx.token, ctx.eventId, deal.id).message }, { stamp++; ctx.reloadOverview() })
        }, onClose = { deleting = null })
    }
}

private fun OrgDeal.toForm(isActive: Boolean = this.isActive) = DealForm(
    brand, website, title, highlight, description ?: title, terms, couponCode, url.orEmpty(), ctaLabel, opensInApp, icon, sortOrder, isActive, captureLeads,
)

@OptIn(ExperimentalLayoutApi::class)
@Composable
private fun DealCard(deal: OrgDeal, extra: String? = null, tools: @Composable () -> Unit) {
    Card {
        Column(verticalArrangement = Arrangement.spacedBy(6.dp)) {
            Row(verticalAlignment = Alignment.Top, horizontalArrangement = Arrangement.spacedBy(8.dp)) {
                deal.icon?.takeIf { it.isNotBlank() }?.let { Text(it, style = MaterialTheme.typography.titleLarge) }
                Column(Modifier.weight(1f)) {
                    Text(deal.name, style = MaterialTheme.typography.titleMedium.copy(fontSize = 15.sp))
                    Text(deal.title + (deal.highlight?.let { " · $it" } ?: ""), style = MaterialTheme.typography.bodyMedium)
                    deal.description?.let { Text(it, style = MaterialTheme.typography.bodySmall, maxLines = 2) }
                }
            }
            FlowRow(horizontalArrangement = Arrangement.spacedBy(4.dp), verticalArrangement = Arrangement.spacedBy(4.dp)) {
                OrgBadge(if (deal.isActive) "On" else "Off", if (deal.isActive) Tone.Ok else Tone.Muted)
                if (deal.hiddenHere) OrgBadge("Hidden here", Tone.Warn, "eye-off")
                if (deal.captureLeads) OrgBadge(plural(deal.leadsCount, "lead"), Tone.Primary, "inbox")
                deal.couponCode?.let { OrgBadge("Code $it", Tone.Accent, "tag") }
                extra?.let { OrgBadge(it, Tone.Muted, "globe") }
            }
            Row(horizontalArrangement = Arrangement.spacedBy(4.dp, Alignment.End), verticalAlignment = Alignment.CenterVertically, modifier = Modifier.fillMaxWidth()) { tools() }
        }
    }
}

@Composable
private fun DealSheet(ctx: EventCtx, deal: OrgDeal?, onClose: () -> Unit, onSaved: () -> Unit) {
    val actions = rememberOrgActions()
    var brandName by remember { mutableStateOf(deal?.brand.orEmpty()) }
    var website by remember { mutableStateOf(deal?.website.orEmpty()) }
    var title by remember { mutableStateOf(deal?.title.orEmpty()) }
    var highlight by remember { mutableStateOf(deal?.highlight.orEmpty()) }
    var description by remember { mutableStateOf(deal?.description.orEmpty()) }
    var terms by remember { mutableStateOf(deal?.terms.orEmpty()) }
    var coupon by remember { mutableStateOf(deal?.couponCode.orEmpty()) }
    var url by remember { mutableStateOf(deal?.url.orEmpty()) }
    var cta by remember { mutableStateOf(deal?.ctaLabel.orEmpty()) }
    var icon by remember { mutableStateOf(deal?.icon.orEmpty()) }
    var order by remember { mutableStateOf(deal?.sortOrder?.toString().orEmpty()) }
    var active by remember { mutableStateOf(deal?.isActive ?: true) }
    var leads by remember { mutableStateOf(deal?.captureLeads ?: false) }
    var inApp by remember { mutableStateOf(deal?.opensInApp ?: true) }

    Sheet(if (deal == null) "New deal" else "Edit deal", onClose, actions = {
        SheetActions(if (actions.busy) "Saving…" else "Save", {
            val form = DealForm(brandName.orNull(), website.orNull(), title.trim(), highlight.orNull(), description.trim(), terms.orNull(), coupon.orNull(), url.trim(), cta.orNull(), inApp, icon.orNull(), order.toIntOrNull(), active, leads)
            actions.run({ if (deal == null) ctx.api.addDeal(ctx.token, ctx.eventId, form).message else ctx.api.updateDeal(ctx.token, ctx.eventId, deal.id, form).message }, onSaved)
        }, "Cancel", onClose, enabled = title.isNotBlank() && description.isNotBlank() && url.isNotBlank() && !actions.busy)
    }) {
        OrgField("Company", brandName, { brandName = it }, max = 120)
        OrgField("Website shown", website, { website = it }, max = 120, placeholder = "example.com")
        OrgField("Title", title, { title = it }, max = 120, placeholder = "20% off yearly plans")
        OrgField("Highlight", highlight, { highlight = it }, max = 40, placeholder = "FREE")
        OrgField("Description", description, { description = it }, lines = 3, max = 500)
        OrgField("Terms", terms, { terms = it }, lines = 2, max = 255)
        OrgField("Coupon code", coupon, { coupon = it }, max = 60)
        OrgField("Link", url, { url = it }, keyboard = KeyboardType.Uri, max = 500, placeholder = "https://…")
        OrgField("Button text", cta, { cta = it }, max = 40, placeholder = "Get the deal")
        Row(horizontalArrangement = Arrangement.spacedBy(8.dp)) {
            OrgField("Emoji", icon, { icon = it }, Modifier.weight(1f), max = 10)
            OrgField("Position", order, { order = it.filter(Char::isDigit).take(5) }, Modifier.weight(1f), keyboard = KeyboardType.Number)
        }
        OrgToggle("On", active, { active = it })
        OrgToggle("Ask for contact details first", leads, { leads = it }, "The form's questions are set on the website")
        OrgToggle("Open inside the app", inApp, { inApp = it })
        if (deal?.hasLogo == true) Text("This deal has a logo from the media library; it is kept.", style = MaterialTheme.typography.bodySmall)
    }
}

// ---- deal leads (admin) ----------------------------------------------------------------------------------

@Composable
internal fun LeadsTab(ctx: EventCtx) {
    val env = LocalEnv.current
    var offer by remember { mutableStateOf<Long?>(null) }
    var from by remember { mutableStateOf("") }
    var to by remember { mutableStateOf("") }
    var page by remember { mutableStateOf(1) }
    val loader = rememberLoader("org-leads:${ctx.eventId}:$offer:$from:$to:$page") { Loaded(ctx.api.orgLeads(ctx.token, ctx.eventId, offer, from.orNull(), to.orNull(), page), false) }
    val offers = (loader.state.value as? LoadState.Ready)?.value?.offers.orEmpty()
    val exporting = rememberOrgActions()

    TabBody {
        if (offers.isNotEmpty() || offer != null) item { OrgFilterRow("Deal", listOf<Pair<Long?, String>>(null to "All deals") + offers.map { it.id to it.name }, offer) { offer = it; page = 1 } }
        item {
            Row(horizontalArrangement = Arrangement.spacedBy(8.dp)) {
                OrgDateField("From", from, { from = it; page = 1 }, Modifier.weight(1f))
                OrgDateField("To", to, { to = it; page = 1 }, Modifier.weight(1f))
            }
        }
        loaded(loader.state.value, loader.reload, ctx.onExpired) { result ->
            item { Text(plural(result.meta.total, "lead") + " · personal data, handle with care", style = MaterialTheme.typography.bodySmall) }
            if (result.meta.total > 0) item {
                OrgButton(if (exporting.busy) "Making the file…" else "Export CSV (these filters)", {
                    exporting.run({
                        val csv = ctx.api.exportLeads(ctx.token, ctx.eventId, offer, from.orNull(), to.orNull())
                        env.deps.shareFile("deal-leads-${ctx.slug}.csv", "text/csv", csv)
                        "CSV ready to share."
                    })
                }, Modifier.fillMaxWidth(), icon = "download", enabled = !exporting.busy)
            }
            if (result.data.isEmpty()) item { EmptyNote("No leads for these filters.", "inbox") }
            items(result.data, key = { it.id }) { lead ->
                Card {
                    Column(verticalArrangement = Arrangement.spacedBy(4.dp)) {
                        Row(verticalAlignment = Alignment.CenterVertically) {
                            Text(lead.name ?: "—", style = MaterialTheme.typography.titleMedium.copy(fontSize = 15.sp), modifier = Modifier.weight(1f))
                            lead.deal?.let { OrgBadge(it, Tone.Primary, "tag") }
                        }
                        lead.company?.let { Text(it, style = MaterialTheme.typography.bodySmall) }
                        lead.email?.let { e -> Text(e, style = MaterialTheme.typography.bodyMedium.copy(color = LocalBrand.current.primary), modifier = Modifier.clickable { env.deps.openUrl("mailto:$e") }) }
                        lead.mobile?.let { p -> Text(p, style = MaterialTheme.typography.bodyMedium.copy(color = LocalBrand.current.primary), modifier = Modifier.clickable { env.deps.openUrl("tel:$p") }) }
                        val choices = lead.choices.joinToString(", ") { (it as? JsonPrimitive)?.content ?: it.toString() }
                        if (choices.isNotBlank()) Text("Chose: $choices", style = MaterialTheme.typography.bodySmall)
                        orgWhen(lead.at)?.let { Text(it, style = MaterialTheme.typography.bodySmall) }
                    }
                }
            }
            item { OrgPager(result.meta) { page = it } }
        }
    }
}

// ---- activity (admin) ------------------------------------------------------------------------------------

@Composable
internal fun EventActivityTab(ctx: EventCtx) {
    var page by remember { mutableStateOf(1) }
    val loader = rememberLoader("org-activity:${ctx.eventId}:$page") { Loaded(ctx.api.eventActivity(ctx.token, ctx.eventId, page), false) }
    TabBody {
        item { Text("What this WordCamp's managers changed (web panel and app), newest first. Admin changes are not listed.", style = MaterialTheme.typography.bodySmall) }
        loaded(loader.state.value, loader.reload, ctx.onExpired) { result ->
            if (result.data.isEmpty()) item { EmptyNote("No manager changes yet.", "activity") }
            items(result.data, key = { it.id }) { ActivityCard(it, showEvent = false) }
            item { OrgPager(result.meta) { page = it } }
        }
    }
}

@Composable
internal fun ActivityCard(row: club.campbuddy.shared.model.ActivityRow, showEvent: Boolean = true) {
    Card {
        Column(verticalArrangement = Arrangement.spacedBy(4.dp)) {
            Row(verticalAlignment = Alignment.CenterVertically, horizontalArrangement = Arrangement.spacedBy(6.dp)) {
                OrgBadge(statusLabel(row.section), Tone.Primary)
                OrgBadge(statusLabel(row.action), if (row.action == "removed") Tone.Danger else Tone.Muted)
                Box(Modifier.weight(1f))
                orgWhen(row.at)?.let { Text(it, style = MaterialTheme.typography.bodySmall.copy(fontSize = 11.5.sp)) }
            }
            Text(row.summary, style = MaterialTheme.typography.bodyMedium)
            Text(listOfNotNull(row.manager, row.event.takeIf { showEvent }).joinToString(" · "), style = MaterialTheme.typography.bodySmall)
        }
    }
}
