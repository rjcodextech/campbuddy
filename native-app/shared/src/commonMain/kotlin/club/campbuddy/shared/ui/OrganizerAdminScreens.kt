package club.campbuddy.shared.ui

import androidx.compose.foundation.background
import androidx.compose.foundation.layout.Arrangement
import androidx.compose.foundation.layout.Box
import androidx.compose.foundation.layout.Column
import androidx.compose.foundation.layout.ExperimentalLayoutApi
import androidx.compose.foundation.layout.FlowRow
import androidx.compose.foundation.layout.PaddingValues
import androidx.compose.foundation.layout.Row
import androidx.compose.foundation.layout.fillMaxSize
import androidx.compose.foundation.layout.fillMaxWidth
import androidx.compose.foundation.layout.height
import androidx.compose.foundation.layout.padding
import androidx.compose.foundation.layout.size
import androidx.compose.foundation.layout.width
import androidx.compose.foundation.lazy.LazyColumn
import androidx.compose.foundation.lazy.LazyListScope
import androidx.compose.foundation.lazy.items
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
import androidx.compose.ui.text.font.FontWeight
import androidx.compose.ui.text.input.KeyboardType
import androidx.compose.ui.unit.dp
import androidx.compose.ui.unit.sp
import club.campbuddy.shared.PlatformBackHandler
import club.campbuddy.shared.data.ApiClient
import club.campbuddy.shared.data.Loaded
import club.campbuddy.shared.model.ManagerForm
import club.campbuddy.shared.model.NewEventForm
import club.campbuddy.shared.model.OrgManager
import club.campbuddy.shared.model.OrganizerEvent

// The admin's platform pages in the app: the dashboard, event managers, and under More: errors, feedback,
// manager activity, maintenance. Same data as the web panel's pages (OrganizerAdminController).

@Composable
private fun PageBody(content: LazyListScope.() -> Unit) {
    LazyColumn(Modifier.fillMaxSize(), contentPadding = PaddingValues(start = 16.dp, end = 16.dp, top = 16.dp, bottom = 96.dp), verticalArrangement = Arrangement.spacedBy(12.dp), content = content)
}

private fun <T> LazyListScope.loadedPage(state: LoadState<T>, reload: () -> Unit, onExpired: () -> Unit, ready: LazyListScope.(T) -> Unit) {
    when (state) {
        LoadState.Loading -> item { Loading() }
        is LoadState.Failed -> item { OrgFailed(state.message, reload, onExpired) }
        is LoadState.Ready -> ready(state.value)
    }
}

/** An event the app only knows by id and name (from a list row): the event screen loads the rest. */
private fun eventStub(id: Long, name: String?) = OrganizerEvent(id = id, slug = "", name = name ?: "WordCamp", status = "")

// ---- dashboard -------------------------------------------------------------------------------------------

@Composable
internal fun AdminDashboardPage(api: ApiClient, token: String, onEvent: (OrganizerEvent) -> Unit, onTab: (String) -> Unit, onExpired: () -> Unit) {
    var stamp by remember { mutableStateOf(0) }
    var more by remember { mutableStateOf<String?>(null) }
    val loader = rememberLoader("admin-dashboard:$stamp") { Loaded(api.adminDashboard(token), false) }

    if (more != null) {
        MoreSubPage(api, token, more!!, onEvent, onExpired) { more = null }
        return
    }

    PageBody {
        loadedPage(loader.state.value, loader.reload, onExpired) { d ->
            item {
                val e = d.errors
                if (e.total == 0) {
                    OrgSection("All good", "check", "No system problem and no failed fetch in 7 days") {}
                } else {
                    OrgSection(
                        plural(e.total, "problem") + " to look at", "alert",
                        listOfNotNull(e.critical.takeIf { it > 0 }?.let { "$it critical" }, e.system.takeIf { it > 0 }?.let { plural(it, "system check") }, e.fetch.takeIf { it > 0 }?.let { plural(it, "failed fetch", "failed fetches") }).joinToString(" · "),
                        action = { OrgButton("Open", { more = "errors" }, kind = if (e.critical > 0) BtnKind.Danger else BtnKind.Quiet) },
                    ) {}
                }
            }
            item {
                StatGrid(listOf(
                    Stat("WordCamps", d.eventsTotal.toString(), "calendar", Tone.Primary) { onTab("events") },
                    Stat("Upcoming live", d.upcomingCount.toString(), "clock", Tone.Ok) { onTab("events") },
                    Stat("Drafts waiting", d.draftCount.toString(), "pencil", if (d.draftCount > 0) Tone.Warn else Tone.Muted) { onTab("events") },
                    Stat("Attendees", d.rosterCount.toString(), "users", Tone.Primary),
                    Stat("Deal leads", d.leadCount.toString() + if (d.recentLeadCount > 0) " (+${d.recentLeadCount})" else "", "inbox", Tone.Accent),
                    Stat("Managers", d.managerCount.toString(), "briefcase", Tone.Primary) { onTab("managers") },
                    Stat("Feedback", d.feedbackCount.toString(), "heart", Tone.Accent) { more = "feedback" },
                    Stat("Errors", d.errors.total.toString(), "alert", if (d.errors.total > 0) Tone.Danger else Tone.Ok) { more = "errors" },
                    Stat("Activity", "Log", "activity", Tone.Muted) { more = "activity" },
                ))
            }
            if (d.nextEvents.isNotEmpty()) {
                item { SectionTitle("Next WordCamps") }
                items(d.nextEvents, key = { "next-${it.id}" }) { EventRowCard(it, onOpen = { onEvent(it) }) }
            }
            if (d.drafts.isNotEmpty()) {
                item { SectionTitle("Drafts to review (${d.draftCount})") }
                items(d.drafts, key = { "draft-${it.id}" }) { EventRowCard(it, onOpen = { onEvent(it) }) }
            }
            item { MaintenanceCard(api, token, d.lastPurge?.at, d.lastPurge?.by, d.lastDataFetch) { stamp++ } }
        }
    }
}

@Composable
private fun MaintenanceCard(api: ApiClient, token: String, purgedAt: String?, purgedBy: String?, lastFetch: club.campbuddy.shared.model.LastFetch?, onDone: () -> Unit) {
    val actions = rememberOrgActions()
    var ask by remember { mutableStateOf<String?>(null) }
    OrgSection("Maintenance", "settings", "The web dashboard's two buttons") {
        KeyValue("Last cache clear", listOfNotNull(orgWhen(purgedAt), purgedBy).joinToString(" · ").ifBlank { "Never" })
        KeyValue("Last data fetch", lastFetch?.let { listOfNotNull(it.event, it.status, orgWhen(it.at)).joinToString(" · ") } ?: "Never")
        Row(horizontalArrangement = Arrangement.spacedBy(8.dp)) {
            OrgButton("Refresh event data", { ask = "refresh" }, Modifier.weight(1f), icon = "refresh", enabled = !actions.busy)
            OrgButton("Clear cache", { ask = "purge" }, Modifier.weight(1f), kind = BtnKind.Danger, icon = "trash", enabled = !actions.busy)
        }
        if (actions.busy) Text("Working… this can take a minute.", style = MaterialTheme.typography.bodySmall)
    }
    when (ask) {
        "refresh" -> ConfirmSheet("Refresh event data?", "Fetches every live WordCamp's schedule, speakers and sponsors now. It calls each WordCamp site, so it takes a while.", "Refresh", danger = false, onConfirm = {
            actions.run({ api.refreshAllData(token).message }, onDone)
        }, onClose = { ask = null })
        "purge" -> ConfirmSheet("Clear the cache?", "The website and every installed app drop saved copies and reload once. Don't do this in the middle of a session.", "Clear cache", onConfirm = {
            actions.run({ api.purgeCache(token).message }, onDone)
        }, onClose = { ask = null })
    }
}

// ---- event managers --------------------------------------------------------------------------------------

@OptIn(ExperimentalLayoutApi::class)
@Composable
internal fun AdminManagersPage(api: ApiClient, token: String, onExpired: () -> Unit) {
    var query by remember { mutableStateOf("") }
    var stamp by remember { mutableStateOf(0) }
    var editing by remember { mutableStateOf<OrgManager?>(null) }
    var adding by remember { mutableStateOf(false) }
    var activeOnly by remember { mutableStateOf<Boolean?>(null) }
    val searched = rememberDebounced(query)
    val loader = rememberLoader("admin-managers:$searched:$stamp") { Loaded(api.adminManagers(token, searched), false) }

    PageBody {
        item { OrgButton("Add event manager", { adding = true }, Modifier.fillMaxWidth(), kind = BtnKind.Primary, icon = "plus") }
        item { OrgSearch(query, { query = it }, "Search name or e-mail") }
        item { OrgFilterRow(null, listOf<Pair<Boolean?, String>>(null to "All", true to "On", false to "Switched off"), activeOnly) { activeOnly = it } }
        loadedPage(loader.state.value, loader.reload, onExpired) { all ->
            val list = all.filter { activeOnly == null || it.isActive == activeOnly }
            item { Text(plural(list.size, "manager"), style = MaterialTheme.typography.bodySmall) }
            if (list.isEmpty()) item { EmptyNote("No event manager here yet. Add one and pick their WordCamps.", "briefcase") }
            items(list, key = { it.id }) { m ->
                Card(onClick = { editing = m }) {
                    Column(verticalArrangement = Arrangement.spacedBy(6.dp)) {
                        Row(verticalAlignment = Alignment.CenterVertically, horizontalArrangement = Arrangement.spacedBy(8.dp)) {
                            Column(Modifier.weight(1f)) {
                                Text(m.name, style = MaterialTheme.typography.titleMedium.copy(fontSize = 15.sp))
                                Text(m.email + (m.phone?.let { " · $it" } ?: ""), style = MaterialTheme.typography.bodySmall)
                            }
                            OrgBadge(if (m.isActive) "On" else "Off", if (m.isActive) Tone.Ok else Tone.Muted)
                        }
                        FlowRow(horizontalArrangement = Arrangement.spacedBy(4.dp), verticalArrangement = Arrangement.spacedBy(4.dp)) {
                            if (m.events.isEmpty()) OrgBadge("No WordCamp", Tone.Warn)
                            m.events.forEach { OrgBadge(it.name, Tone.Primary, "calendar") }
                        }
                        Text("Last sign-in: " + (orgWhen(m.lastLoginAt) ?: "never on the web"), style = MaterialTheme.typography.bodySmall)
                    }
                }
            }
        }
    }
    if (adding || editing != null) ManagerSheet(api, token, editing, onClose = { adding = false; editing = null }, onSaved = { adding = false; editing = null; stamp++ })
}

@OptIn(ExperimentalLayoutApi::class)
@Composable
private fun ManagerSheet(api: ApiClient, token: String, manager: OrgManager?, onClose: () -> Unit, onSaved: () -> Unit) {
    val actions = rememberOrgActions()
    val options = rememberLoader("admin-event-options") { Loaded(api.adminEventOptions(token), false) }
    var name by remember { mutableStateOf(manager?.name.orEmpty()) }
    var email by remember { mutableStateOf(manager?.email.orEmpty()) }
    var phone by remember { mutableStateOf(manager?.phone.orEmpty()) }
    var password by remember { mutableStateOf("") }
    var active by remember { mutableStateOf(manager?.isActive ?: true) }
    var events by remember { mutableStateOf(manager?.events?.map { it.id }?.toSet() ?: emptySet()) }
    var find by remember { mutableStateOf("") }
    var askDelete by remember { mutableStateOf(false) }
    val valid = name.isNotBlank() && email.isNotBlank() && phone.isNotBlank() && (manager != null || (password.length >= 8 && events.isNotEmpty()))

    Sheet(if (manager == null) "New event manager" else manager.name, onClose, actions = {
        SheetActions(if (actions.busy) "Saving…" else "Save", {
            val form = ManagerForm(name.trim(), email.trim(), phone.trim(), password.ifBlank { null }, active, events.toList())
            actions.run({ if (manager == null) api.addManager(token, form).message else api.updateManager(token, manager.id, form).message }, onSaved)
        }, "Cancel", onClose, enabled = valid && !actions.busy)
    }) {
        OrgField("Name", name, { name = it }, max = 255)
        OrgField("E-mail (sign-in)", email, { email = it }, keyboard = KeyboardType.Email, max = 255)
        OrgField("Phone", phone, { phone = it }, keyboard = KeyboardType.Phone, max = 20)
        OrgField(if (manager == null) "Password" else "New password", password, { password = it }, password = true, max = 72, help = if (manager == null) "At least 8 characters; tell them yourself" else "Leave empty to keep it; a new one signs them out everywhere")
        OrgToggle("Can sign in", active, { active = it }, "Switching off signs them out of the app and the web panel")
        Text("WordCamps they manage (${events.size})", style = MaterialTheme.typography.titleMedium.copy(fontSize = 15.sp))
        OrgSearch(find, { find = it }, "Find a WordCamp")
        when (val s = options.state.value) {
            LoadState.Loading -> Loading()
            is LoadState.Failed -> ErrorState(s.message, options.reload)
            is LoadState.Ready -> {
                val shown = s.value.filter { find.isBlank() || it.name.contains(find, ignoreCase = true) || it.id in events }.sortedByDescending { it.id in events }.take(40)
                FlowRow(horizontalArrangement = Arrangement.spacedBy(6.dp), verticalArrangement = Arrangement.spacedBy(6.dp)) {
                    shown.forEach { o ->
                        CbChip(selected = o.id in events, onClick = { events = if (o.id in events) events - o.id else events + o.id }, label = { Text(o.name + (o.startsOn?.let { " · ${it.take(4)}" } ?: "")) })
                    }
                }
            }
        }
        if (manager != null) OrgButton("Delete this manager…", { askDelete = true }, Modifier.fillMaxWidth(), kind = BtnKind.Danger, icon = "trash")
    }
    if (askDelete && manager != null) ConfirmSheet("Delete ${manager.name}?", "They can no longer sign in. Their past changes stay in the activity log.", "Delete", onConfirm = {
        actions.run({ api.deleteManager(token, manager.id).message }, onSaved)
    }, onClose = { askDelete = false })
}

// ---- more ------------------------------------------------------------------------------------------------

@Composable
internal fun AdminMorePage(api: ApiClient, token: String, onEvent: (OrganizerEvent) -> Unit, onExpired: () -> Unit) {
    var page by remember { mutableStateOf<String?>(null) }
    val current = page
    if (current != null) {
        MoreSubPage(api, token, current, onEvent, onExpired) { page = null }
        return
    }
    PageBody {
        item { MoreRow("activity", "Analytics", "Google Analytics: visitors, WordCamps, pages, sources, devices, with date filters") { page = "analytics" } }
        item { MoreRow("alert", "Errors", "System checks and failed fetches, with filters") { page = "errors" } }
        item { MoreRow("heart", "Feedback", "Ratings and comments from the thank-you card") { page = "feedback" } }
        item { MoreRow("activity", "Manager activity", "Everything event managers changed, by manager or WordCamp") { page = "activity" } }
        item { MoreRow("settings", "Maintenance", "Refresh event data, clear the cache, discover WordCamps") { page = "maintenance" } }
        item {
            Text(
                "The Mobile app settings, legal pages, media library, default deals, free steals, commands, the usage post, reels and artwork uploads stay on the website's admin panel (bigger screens, file uploads, video).",
                style = MaterialTheme.typography.bodySmall,
            )
        }
    }
}

@Composable
private fun MoreRow(icon: String, title: String, sub: String, onClick: () -> Unit) {
    val brand = LocalBrand.current
    Card(onClick = onClick) {
        Row(verticalAlignment = Alignment.CenterVertically, horizontalArrangement = Arrangement.spacedBy(12.dp)) {
            Box(Modifier.size(40.dp).background(brand.peach, CircleShape), contentAlignment = Alignment.Center) { LineIcon(icon, brand.primary, size = 20.dp) }
            Column(Modifier.weight(1f)) {
                Text(title, style = MaterialTheme.typography.titleMedium)
                Text(sub, style = MaterialTheme.typography.bodySmall)
            }
            LineIcon("chevron-right", brand.muted, size = 18.dp)
        }
    }
}

@Composable
private fun MoreSubPage(api: ApiClient, token: String, page: String, onEvent: (OrganizerEvent) -> Unit, onExpired: () -> Unit, onBack: () -> Unit) {
    val brand = LocalBrand.current
    PlatformBackHandler(true, onBack)
    Column(Modifier.fillMaxSize()) {
        Row(Modifier.fillMaxWidth().background(brand.background).padding(start = 4.dp, top = 4.dp, end = 16.dp), verticalAlignment = Alignment.CenterVertically) {
            OrgIconButton("chevron-left", "Back", onBack, filled = false)
            Text(when (page) { "errors" -> "Errors"; "feedback" -> "Feedback"; "activity" -> "Manager activity"; "analytics" -> "Analytics"; else -> "Maintenance" }, style = MaterialTheme.typography.titleLarge)
        }
        Box(Modifier.weight(1f)) {
            when (page) {
                "errors" -> ErrorsPage(api, token, onEvent, onExpired)
                "feedback" -> FeedbackPage(api, token, onExpired)
                "activity" -> ActivityLogPage(api, token, onExpired)
                "analytics" -> AnalyticsPage(api, token, onExpired)
                else -> MaintenancePage(api, token, onExpired)
            }
        }
    }
}

// ---- errors ----------------------------------------------------------------------------------------------

@Composable
private fun ErrorsPage(api: ApiClient, token: String, onEvent: (OrganizerEvent) -> Unit, onExpired: () -> Unit) {
    var period by remember { mutableStateOf("7") }
    var job by remember { mutableStateOf<String?>(null) }
    var result by remember { mutableStateOf<String?>(null) }
    var page by remember { mutableStateOf(1) }
    val loader = rememberLoader("admin-errors:$period:$job:$result:$page") { Loaded(api.adminErrors(token, period, null, job, result, page), false) }
    val ready = (loader.state.value as? LoadState.Ready)?.value

    PageBody {
        loadedPage(loader.state.value, loader.reload, onExpired) { r ->
            item { SectionTitle("System checks (${r.data.system.size})") }
            if (r.data.system.isEmpty()) item { EmptyNote("Every system check passes: scheduler, queue, migrations, event data.", "check") }
            items(r.data.system) { p ->
                Card {
                    Column(verticalArrangement = Arrangement.spacedBy(6.dp)) {
                        Row(verticalAlignment = Alignment.CenterVertically, horizontalArrangement = Arrangement.spacedBy(8.dp)) {
                            OrgBadge(statusLabel(p.level), levelTone(p.level))
                            Text(p.title, style = MaterialTheme.typography.titleMedium.copy(fontSize = 15.sp), modifier = Modifier.weight(1f))
                        }
                        Text(p.detail, style = MaterialTheme.typography.bodyMedium)
                        p.fix?.let { Text("Fix: $it", style = MaterialTheme.typography.bodySmall.copy(fontWeight = FontWeight.SemiBold)) }
                    }
                }
            }
            item { SectionTitle("Failed fetches") }
            item { OrgFilterRow("Period", (ready?.data?.periods ?: emptyList()).map { it.key to it.text }, period) { period = it; page = 1 } }
            item { OrgFilterRow("Job", listOf<Pair<String?, String>>(null to "All") + (ready?.data?.jobs ?: emptyList()).map { it.key to it.text }, job) { job = it; page = 1 } }
            item { OrgFilterRow("Result", listOf<Pair<String?, String>>(null to "Both", "error" to "Failed", "partial" to "Partly"), result) { result = it; page = 1 } }
            if (r.data.fetch.isEmpty()) item { EmptyNote("No failed fetch in this period.", "check") }
            items(r.data.fetch) { f ->
                Card(onClick = f.eventId?.let { id -> { onEvent(eventStub(id, f.event)) } }) {
                    Column(verticalArrangement = Arrangement.spacedBy(4.dp)) {
                        Row(verticalAlignment = Alignment.CenterVertically, horizontalArrangement = Arrangement.spacedBy(6.dp)) {
                            OrgBadge(statusLabel(f.status), levelTone(f.status))
                            OrgBadge(f.job, Tone.Muted)
                            if (f.times > 1) OrgBadge("×${f.times}", Tone.Warn)
                        }
                        Text(f.event ?: "Platform", style = MaterialTheme.typography.titleMedium.copy(fontSize = 15.sp))
                        f.message?.let { Text(it, style = MaterialTheme.typography.bodyMedium, maxLines = 4) }
                        orgWhen(f.lastAt)?.let { Text("Last: $it", style = MaterialTheme.typography.bodySmall) }
                    }
                }
            }
            item { OrgPager(r.meta) { page = it } }
        }
    }
}

// ---- feedback --------------------------------------------------------------------------------------------

@Composable
private fun FeedbackPage(api: ApiClient, token: String, onExpired: () -> Unit) {
    val brand = LocalBrand.current
    var event by remember { mutableStateOf<Long?>(null) }
    var page by remember { mutableStateOf(1) }
    val loader = rememberLoader("admin-feedback:$event:$page") { Loaded(api.adminFeedback(token, event, page), false) }

    PageBody {
        loadedPage(loader.state.value, loader.reload, onExpired) { r ->
            if (r.data.summary.isEmpty()) item { EmptyNote("No feedback yet. It comes from the thank-you card after a WordCamp.", "heart") }
            if (r.data.summary.isNotEmpty()) item {
                OrgFilterRow("WordCamp", listOf<Pair<Long?, String>>(null to "All") + r.data.summary.map { it.eventId to (it.event ?: "#${it.eventId}") }, event) { event = it; page = 1 }
            }
            items(r.data.summary.filter { event == null || it.eventId == event }, key = { "sum-${it.eventId}" }) { s ->
                Card {
                    Column(verticalArrangement = Arrangement.spacedBy(8.dp)) {
                        Row(verticalAlignment = Alignment.CenterVertically) {
                            Column(Modifier.weight(1f)) {
                                Text(s.event ?: "WordCamp", style = MaterialTheme.typography.titleMedium.copy(fontSize = 15.sp))
                                Text(plural(s.answers, "answer") + " · " + plural(s.comments, "comment"), style = MaterialTheme.typography.bodySmall)
                            }
                            Text("★ " + ((s.average * 10).toInt() / 10.0).toString(), style = MaterialTheme.typography.titleLarge.copy(color = brand.primary, fontWeight = FontWeight.ExtraBold))
                        }
                        s.stars.forEachIndexed { i, n ->
                            Row(verticalAlignment = Alignment.CenterVertically, horizontalArrangement = Arrangement.spacedBy(8.dp)) {
                                Text("${5 - i}★", style = MaterialTheme.typography.bodySmall, modifier = Modifier.width(28.dp))
                                Box(Modifier.weight(1f).height(8.dp).background(brand.line, RoundedCornerShape(50))) {
                                    if (n > 0 && s.answers > 0) Box(Modifier.fillMaxWidth(n.toFloat() / s.answers).height(8.dp).background(brand.accent, RoundedCornerShape(50)))
                                }
                                Text(n.toString(), style = MaterialTheme.typography.bodySmall, modifier = Modifier.width(28.dp))
                            }
                        }
                    }
                }
            }
            if (r.data.items.isNotEmpty()) item { SectionTitle("Newest first") }
            items(r.data.items, key = { "fb-${it.id}" }) { f ->
                Card {
                    Column(verticalArrangement = Arrangement.spacedBy(4.dp)) {
                        Row(verticalAlignment = Alignment.CenterVertically) {
                            Text("★".repeat(f.rating.coerceIn(0, 5)) + "☆".repeat((5 - f.rating).coerceIn(0, 5)), style = MaterialTheme.typography.titleMedium.copy(color = brand.accent), modifier = Modifier.weight(1f))
                            orgWhen(f.at)?.let { Text(it, style = MaterialTheme.typography.bodySmall) }
                        }
                        Text(f.comment ?: "No comment", style = MaterialTheme.typography.bodyMedium.copy(color = if (f.comment == null) brand.muted else brand.ink))
                        f.event?.let { Text(it, style = MaterialTheme.typography.bodySmall) }
                    }
                }
            }
            item { OrgPager(r.meta) { page = it } }
        }
    }
}

// ---- activity --------------------------------------------------------------------------------------------

@Composable
private fun ActivityLogPage(api: ApiClient, token: String, onExpired: () -> Unit) {
    var manager by remember { mutableStateOf<Long?>(null) }
    var event by remember { mutableStateOf<Long?>(null) }
    var page by remember { mutableStateOf(1) }
    val loader = rememberLoader("admin-activity:$manager:$event:$page") { Loaded(api.adminActivity(token, manager, event, page), false) }
    val ready = (loader.state.value as? LoadState.Ready)?.value

    PageBody {
        item { Text("What event managers changed, newest first (kept 90 days).", style = MaterialTheme.typography.bodySmall) }
        ready?.let { r ->
            if (r.managers.isNotEmpty()) item { OrgFilterRow("Manager", listOf<Pair<Long?, String>>(null to "All") + r.managers.map { it.id to it.name }, manager) { manager = it; page = 1 } }
            if (r.events.isNotEmpty()) item { OrgFilterRow("WordCamp", listOf<Pair<Long?, String>>(null to "All") + r.events.map { it.id to it.name }, event) { event = it; page = 1 } }
        }
        loadedPage(loader.state.value, loader.reload, onExpired) { r ->
            if (r.data.isEmpty()) item { EmptyNote("No changes for these filters.", "activity") }
            items(r.data, key = { it.id }) { ActivityCard(it) }
            item { OrgPager(r.meta) { page = it } }
        }
    }
}

// ---- analytics -------------------------------------------------------------------------------------------

/** What each GA report is called on the website's Analytics page, in its order. */
private val REPORT_TITLES = linkedMapOf(
    "daily" to "By day", "wordcamps" to "WordCamps", "new_returning" to "New and returning", "events" to "Events",
    "source" to "Where visits came from", "first_source" to "Where new people came from", "country" to "Countries", "city" to "Cities",
    "device" to "Devices", "os" to "Systems", "browser" to "Browsers", "pages" to "Pages", "hour" to "By hour", "weekday" to "By weekday",
    "display_mode" to "App or browser", "nav_tab" to "Tabs opened", "explore_tab" to "Explore tabs", "saved_sessions" to "Saved sessions",
    "quests" to "Quests done", "deals" to "Deals", "sponsors" to "Sponsors opened", "guide_sections" to "Guide sections", "faq" to "FAQ",
    "install" to "Install", "reminder" to "Reminders", "vitals" to "Web vitals", "exceptions" to "Errors in the app", "api_errors" to "API errors",
)

private val COLUMN_NAMES = mapOf(
    "activeUsers" to "Users", "newUsers" to "New", "sessions" to "Visits", "screenPageViews" to "Views", "eventCount" to "Count",
    "totalUsers" to "Users", "userEngagementDuration" to "Time (s)", "engagedSessions" to "Engaged", "engagementRate" to "Engaged %",
    "customEvent:event_slug" to "WordCamp", "sessionSourceMedium" to "Source / medium", "firstUserSourceMedium" to "Source / medium",
    "pagePath" to "Page", "eventName" to "Event", "newVsReturning" to "Who", "deviceCategory" to "Device", "operatingSystem" to "System",
    "dayOfWeek" to "Day", "customEvent:tab" to "Tab", "customEvent:session_title" to "Session", "customEvent:quest_title" to "Quest",
    "customEvent:offer_title" to "Deal", "customEvent:sponsor_name" to "Sponsor", "customEvent:section" to "Section", "customEvent:question" to "Question",
    "customEvent:platform" to "Platform", "customEvent:outcome" to "Outcome", "customEvent:result" to "Result", "customEvent:metric_name" to "Metric",
    "customEvent:metric_rating" to "Rating", "customEvent:description" to "Error", "customEvent:endpoint" to "Endpoint", "customEvent:method" to "Method",
    "customEvent:status" to "Status", "customEvent:display_mode" to "Mode",
)

/** Admin → Analytics in the app: the website page's GA4 reports, with its period, dates, stream and bots filters. */
@Composable
private fun AnalyticsPage(api: ApiClient, token: String, onExpired: () -> Unit) {
    var period by remember { mutableStateOf("10") }
    var from by remember { mutableStateOf("") }
    var to by remember { mutableStateOf("") }
    var stream by remember { mutableStateOf<String?>(null) }
    var hideBots by remember { mutableStateOf(true) }
    var fresh by remember { mutableStateOf(0) }
    val custom = period == "custom"
    // Custom dates are asked for only once both are picked.
    val asked = if (custom && (from.isBlank() || to.isBlank())) "10" else period
    val loader = rememberLoader("admin-analytics:$asked:$from:$to:$stream:$hideBots:$fresh") {
        Loaded(api.adminAnalytics(token, asked, from.ifBlank { null }, to.ifBlank { null }, stream, hideBots, fresh > 0), false)
    }
    val data = (loader.state.value as? LoadState.Ready)?.value

    PageBody {
        item { OrgFilterRow("Period", (data?.periods ?: emptyList()).map { it.key to it.text }.ifEmpty { listOf("10" to "Last 10 days") }, period) { period = it } }
        if (custom) item {
            Row(horizontalArrangement = Arrangement.spacedBy(8.dp)) {
                OrgDateField("From", from, { from = it }, Modifier.weight(1f))
                OrgDateField("To", to, { to = it }, Modifier.weight(1f))
            }
        }
        if ((data?.streams?.size ?: 0) > 1) item { OrgFilterRow("Stream", data!!.streams.map { it.key to it.text }, data.filters.stream) { stream = it } }
        item {
            Row(verticalAlignment = Alignment.CenterVertically, horizontalArrangement = Arrangement.spacedBy(8.dp)) {
                Box(Modifier.weight(1f)) { OrgToggle("Hide bots", hideBots, { hideBots = it }) }
                OrgButton("Fresh numbers", { fresh++ }, icon = "refresh")
            }
        }
        loadedPage(loader.state.value, loader.reload, onExpired) { d ->
            val problem = d.error
            if (problem != null) {
                item { EmptyNote(problem, "alert") }
            } else {
                item {
                    Text(
                        "${d.filters.from} – ${d.filters.to}" + (orgWhen(d.fetchedAt)?.let { " · read $it (kept an hour)" } ?: "") +
                            if (d.filters.invalid) " · those dates cannot be read, showing the default" else "",
                        style = MaterialTheme.typography.bodySmall,
                    )
                }
                d.reports["totals"]?.firstOrNull()?.let { t ->
                    item {
                        fun n(k: String) = t[k] ?: "0"
                        val rate = (n("engagementRate").toDoubleOrNull() ?: 0.0) * 100
                        val users = n("activeUsers").toDoubleOrNull() ?: 0.0
                        val avg = if (users > 0) ((n("userEngagementDuration").toDoubleOrNull() ?: 0.0) / users).toInt() else 0
                        StatGrid(listOf(
                            Stat("Users", n("activeUsers"), "users", Tone.Primary),
                            Stat("New users", n("newUsers"), "sparkles", Tone.Accent),
                            Stat("Visits", n("sessions"), "compass", Tone.Primary),
                            Stat("Engaged", "${rate.toInt()}%", "heart", Tone.Ok),
                            Stat("Views", n("screenPageViews"), "eye", Tone.Muted),
                            Stat("Avg time", "${avg / 60}m ${avg % 60}s", "clock", Tone.Muted),
                        ))
                    }
                }
                REPORT_TITLES.forEach { (key, title) ->
                    val rows = d.reports[key].orEmpty()
                    if (rows.isNotEmpty()) item(key = "r-$key") { ReportTable(title, rows, d.events) }
                }
            }
        }
    }
}

@Composable
private fun ReportTable(title: String, rows: List<Map<String, String>>, events: Map<String, String>) {
    val brand = LocalBrand.current
    var all by remember { mutableStateOf(false) }
    val columns = rows.first().keys.toList()
    OrgSection(title, null, plural(rows.size, "row")) {
        Row(Modifier.fillMaxWidth()) {
            columns.forEachIndexed { i, c ->
                Text(COLUMN_NAMES[c] ?: c.substringAfter(':'), style = MaterialTheme.typography.labelMedium, modifier = Modifier.weight(if (i == 0) 2f else 1f), maxLines = 1)
            }
        }
        (if (all) rows else rows.take(10)).forEach { row ->
            Row(Modifier.fillMaxWidth().background(brand.background, Radii.input).padding(vertical = 4.dp, horizontal = 4.dp)) {
                columns.forEachIndexed { i, c ->
                    val raw = row[c].orEmpty()
                    val shown = when {
                        c == "customEvent:event_slug" -> events[raw] ?: raw.ifBlank { "(none)" }
                        c == "date" && raw.length == 8 -> raw.substring(6) + "/" + raw.substring(4, 6)
                        c == "engagementRate" -> "${((raw.toDoubleOrNull() ?: 0.0) * 100).toInt()}%"
                        else -> raw.ifBlank { "(not set)" }
                    }
                    Text(shown, style = MaterialTheme.typography.bodySmall.copy(color = if (i == 0) brand.ink else brand.muted, fontSize = 12.sp), modifier = Modifier.weight(if (i == 0) 2f else 1f), maxLines = 2)
                }
            }
        }
        if (rows.size > 10) LinkButton(if (all) "Show top 10" else "Show all ${rows.size}", { all = !all })
    }
}


// ---- maintenance -----------------------------------------------------------------------------------------

@Composable
private fun MaintenancePage(api: ApiClient, token: String, onExpired: () -> Unit) {
    var stamp by remember { mutableStateOf(0) }
    val loader = rememberLoader("admin-maintenance:$stamp") { Loaded(api.adminDashboard(token), false) }
    val actions = rememberOrgActions()
    PageBody {
        loadedPage(loader.state.value, loader.reload, onExpired) { d ->
            item { MaintenanceCard(api, token, d.lastPurge?.at, d.lastPurge?.by, d.lastDataFetch) { stamp++ } }
            item {
                OrgSection("Discover WordCamps", "compass", "Looks for new WordCamps on central.wordcamp.org; new ones arrive as drafts") {
                    OrgButton(if (actions.busy) "Queuing…" else "Discover now", { actions.run({ api.discoverEvents(token).message }) }, Modifier.fillMaxWidth(), icon = "compass", enabled = !actions.busy)
                }
            }
        }
    }
}

// ---- new event -------------------------------------------------------------------------------------------

@Composable
internal fun NewEventSheet(api: ApiClient, token: String, onClose: () -> Unit, onCreated: (OrganizerEvent) -> Unit) {
    val actions = rememberOrgActions()
    var name by remember { mutableStateOf("") }
    var short by remember { mutableStateOf("") }
    var slug by remember { mutableStateOf("") }
    var slugTouched by remember { mutableStateOf(false) }
    var site by remember { mutableStateOf("https://") }
    var starts by remember { mutableStateOf("") }
    var ends by remember { mutableStateOf("") }
    var zone by remember { mutableStateOf("") }
    var status by remember { mutableStateOf("draft") }
    var visible by remember { mutableStateOf(true) }
    var created by remember { mutableStateOf<OrganizerEvent?>(null) }

    created?.let { e -> androidx.compose.runtime.LaunchedEffect(e.id) { onCreated(e) } }

    Sheet("New WordCamp", onClose, actions = {
        SheetActions(if (actions.busy) "Creating…" else "Create", {
            actions.run({
                val reply = api.addEvent(token, NewEventForm(slug.trim(), name.trim(), short.trim().ifBlank { null }, site.trim(), starts.ifBlank { null }, ends.ifBlank { null }, zone.trim().ifBlank { null }, visible, status))
                created = reply.data
                reply.message
            })
        }, "Cancel", onClose, enabled = name.isNotBlank() && slug.isNotBlank() && site.length > 8 && !actions.busy)
    }) {
        OrgField("Display name", name, { name = it; if (!slugTouched) slug = it.lowercase().replace(Regex("[^a-z0-9]+"), "-").trim('-') }, max = 255, placeholder = "WordCamp Jaipur 2027")
        OrgField("Short name", short, { short = it }, max = 60)
        OrgField("URL slug", slug, { slug = it.lowercase().replace(' ', '-'); slugTouched = true }, max = 191)
        OrgField("WordCamp site", site, { site = it }, keyboard = KeyboardType.Uri, max = 500)
        Row(horizontalArrangement = Arrangement.spacedBy(8.dp)) {
            OrgDateField("Starts", starts, { starts = it }, Modifier.weight(1f))
            OrgDateField("Ends", ends, { ends = it }, Modifier.weight(1f))
        }
        OrgField("Time zone", zone, { zone = it }, placeholder = "Blank = from the site", max = 64)
        OrgFilterRow("Status", listOf("draft" to "Draft", "approved" to "Approved", "active" to "Active", "archived" to "Archived"), status) { status = it }
        OrgToggle("Visible to attendees", visible, { visible = it })
    }
}
