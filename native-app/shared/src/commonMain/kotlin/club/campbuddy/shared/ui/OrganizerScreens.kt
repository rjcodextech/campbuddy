package club.campbuddy.shared.ui

import androidx.compose.foundation.layout.widthIn
import androidx.compose.ui.text.style.TextAlign
import androidx.compose.foundation.background
import androidx.compose.foundation.border
import androidx.compose.foundation.clickable
import androidx.compose.foundation.horizontalScroll
import androidx.compose.foundation.layout.Arrangement
import androidx.compose.foundation.layout.Box
import androidx.compose.foundation.layout.Column
import androidx.compose.foundation.layout.PaddingValues
import androidx.compose.foundation.layout.Row
import androidx.compose.foundation.layout.fillMaxHeight
import androidx.compose.foundation.layout.fillMaxSize
import androidx.compose.foundation.layout.fillMaxWidth
import androidx.compose.foundation.layout.height
import androidx.compose.foundation.layout.imePadding
import androidx.compose.foundation.layout.navigationBarsPadding
import androidx.compose.foundation.layout.padding
import androidx.compose.foundation.layout.size
import androidx.compose.foundation.layout.statusBarsPadding
import androidx.compose.foundation.lazy.LazyColumn
import androidx.compose.foundation.lazy.items
import androidx.compose.foundation.rememberScrollState
import androidx.compose.foundation.shape.CircleShape
import androidx.compose.foundation.shape.RoundedCornerShape
import androidx.compose.foundation.verticalScroll
import androidx.compose.material3.MaterialTheme
import androidx.compose.material3.Text
import androidx.compose.runtime.Composable
import androidx.compose.runtime.CompositionLocalProvider
import androidx.compose.runtime.getValue
import androidx.compose.runtime.mutableStateOf
import androidx.compose.runtime.remember
import androidx.compose.runtime.rememberCoroutineScope
import androidx.compose.runtime.setValue
import androidx.compose.ui.Alignment
import androidx.compose.ui.Modifier
import androidx.compose.ui.draw.clip
import androidx.compose.ui.graphics.Color
import androidx.compose.ui.semantics.Role
import androidx.compose.ui.text.font.FontWeight
import androidx.compose.ui.text.input.KeyboardType
import androidx.compose.ui.text.style.TextOverflow
import androidx.compose.ui.unit.dp
import androidx.compose.ui.unit.sp
import club.campbuddy.shared.PlatformBackHandler
import club.campbuddy.shared.data.ApiClient
import club.campbuddy.shared.data.ApiException
import club.campbuddy.shared.data.Loaded
import club.campbuddy.shared.data.Repository
import club.campbuddy.shared.model.OrganizerEvent
import kotlinx.coroutines.launch

/**
 * What the organizer screens remember while the app runs (not on disk): the open tab, the events filters and each
 * event's last tab, so coming back from an event finds the list as it was left.
 */
internal object OrgMemory {
    var homeTab = "dashboard"
    var query = ""
    var status: String? = null
    var visibility: String? = null
    var whenFilter: String? = null
    val eventTabs = mutableMapOf<Long, String>()
}

/** Admin / Event manager sign-in. The token goes to the platform store; the password is never kept. */
@Composable
fun OrganizerLoginScreen(api: ApiClient, repository: Repository, deviceName: String, onSignedIn: () -> Unit, onBack: () -> Unit) {
    val brand = LocalBrand.current
    val scope = rememberCoroutineScope()
    var email by remember { mutableStateOf("") }
    var password by remember { mutableStateOf("") }
    var show by remember { mutableStateOf(false) }
    var role by remember { mutableStateOf("manager") }
    var busy by remember { mutableStateOf(false) }
    var error by remember { mutableStateOf<String?>(null) }

    PlatformBackHandler(true, onBack)

    Column(Modifier.fillMaxSize().background(brand.background).statusBarsPadding()) {
        OrgHeader("Event staff sign-in", "For WordCamp organizers and administrators", onBack = onBack)
        Column(Modifier.weight(1f).verticalScroll(rememberScrollState()).imePadding().padding(16.dp), verticalArrangement = Arrangement.spacedBy(14.dp)) {
            Text("Attendees never need to sign in. This is for the people running a WordCamp.", style = MaterialTheme.typography.bodyMedium.copy(color = brand.muted))
            Row(Modifier.fillMaxWidth(), horizontalArrangement = Arrangement.spacedBy(10.dp)) {
                RoleCard("manager", role, "Event manager", "Your assigned WordCamps", "briefcase", Modifier.weight(1f)) { role = it }
                RoleCard("admin", role, "Administrator", "Every WordCamp + platform", "lock", Modifier.weight(1f)) { role = it }
            }
            Card {
                Column(verticalArrangement = Arrangement.spacedBy(12.dp)) {
                    OrgField("E-mail", email, { email = it }, keyboard = KeyboardType.Email, max = 255)
                    Row(verticalAlignment = Alignment.CenterVertically) {
                        OrgField("Password", password, { password = it }, Modifier.weight(1f), keyboard = KeyboardType.Password, password = !show, max = 255)
                        OrgIconButton(if (show) "eye-off" else "eye", if (show) "Hide password" else "Show password", { show = !show }, filled = false)
                    }
                    error?.let {
                        Row(
                            Modifier.fillMaxWidth().background(brand.danger.copy(alpha = 0.08f), RoundedCornerShape(8.dp)).padding(10.dp),
                            horizontalArrangement = Arrangement.spacedBy(8.dp), verticalAlignment = Alignment.CenterVertically,
                        ) {
                            LineIcon("siren", brand.danger, size = 16.dp)
                            Text(it, style = MaterialTheme.typography.bodyMedium.copy(color = brand.danger))
                        }
                    }
                    PrimaryButton(if (busy) "Signing in…" else "Sign in", enabled = !busy && email.isNotBlank() && password.isNotBlank(), modifier = Modifier.fillMaxWidth(), onClick = {
                        busy = true
                        error = null
                        scope.launch {
                            try {
                                val login = api.login(email.trim(), password, role, deviceName)
                                repository.saveOrganizer(login.token, login.role, login.user.name)
                                OrgMemory.homeTab = if (login.role == "admin") "dashboard" else "events"
                                onSignedIn()
                            } catch (e: ApiException) {
                                error = e.message
                            } catch (e: Exception) {
                                error = "Could not reach CampBuddy. Check your connection."
                            } finally {
                                busy = false
                            }
                        }
                    })
                }
            }
            Text(
                if (role == "admin") "Admins see every WordCamp and the platform pages: dashboard, errors, feedback, event managers and maintenance. A sign-in lasts 12 hours."
                else "Managers run their own WordCamps: details, information, running late, reports, attendees and roles, quests, Contributor Day, memories. A sign-in lasts 7 days.",
                style = MaterialTheme.typography.bodySmall,
            )
        }
    }
}

@Composable
private fun RoleCard(key: String, selected: String, title: String, sub: String, icon: String, modifier: Modifier, onPick: (String) -> Unit) {
    val brand = LocalBrand.current
    val on = key == selected
    val shape = RoundedCornerShape(brand.radius.dp)
    Column(
        modifier.clip(shape).background(if (on) brand.primary.copy(alpha = 0.08f) else brand.surface, shape)
            .border(if (on) 2.dp else 1.dp, if (on) brand.primary else brand.line, shape)
            .clickable(role = Role.RadioButton) { onPick(key) }.padding(12.dp),
        verticalArrangement = Arrangement.spacedBy(6.dp),
    ) {
        Box(Modifier.size(32.dp).background(if (on) brand.primary else brand.peach, CircleShape), contentAlignment = Alignment.Center) {
            LineIcon(icon, if (on) Color.White else brand.primary, size = 16.dp)
        }
        Text(title, style = MaterialTheme.typography.titleMedium.copy(fontSize = 15.sp))
        Text(sub, style = MaterialTheme.typography.bodySmall.copy(fontSize = 12.sp, lineHeight = 15.sp))
    }
}

/**
 * The staff hub. An admin gets four tabs at the bottom (Dashboard, Events, Managers, More); a manager gets the list
 * of their events. Messages after a change show at the bottom of the screen.
 */
@Composable
fun OrganizerHomeScreen(
    api: ApiClient,
    repository: Repository,
    onEvent: (OrganizerEvent) -> Unit,
    onPreviewAttendee: (String) -> Unit = {},
    onSignedOut: () -> Unit,
    onBack: () -> Unit,
) {
    val brand = LocalBrand.current
    val scope = rememberCoroutineScope()
    val token = repository.organizerToken().orEmpty()
    val isAdmin = (repository.organizerRole() ?: "manager") == "admin"
    val staffName = repository.organizerName() ?: (if (isAdmin) "Admin" else "Manager")
    val toast = remember { OrgToast() }
    var tab by remember { mutableStateOf(if (isAdmin) OrgMemory.homeTab else "events") }
    var askSignOut by remember { mutableStateOf(false) }

    PlatformBackHandler(true) { if (isAdmin && tab != "dashboard") { tab = "dashboard"; OrgMemory.homeTab = tab } else onBack() }

    val signOut: () -> Unit = {
        scope.launch {
            runCatching { api.logout(token) }
            repository.forgetOrganizer()
            onSignedOut()
        }
    }
    // An expired or revoked sign-in sends the person back to the picker, signed out.
    val onExpired: () -> Unit = {
        repository.forgetOrganizer()
        onSignedOut()
    }

    CompositionLocalProvider(LocalOrgToast provides toast) {
        Box(Modifier.fillMaxSize().background(brand.background)) {
            Column(Modifier.fillMaxSize().statusBarsPadding()) {
                OrgHeader(
                    title = when (tab) {
                        "dashboard" -> "Dashboard"
                        "managers" -> "Event managers"
                        "more" -> "More"
                        else -> if (isAdmin) "All WordCamps" else "My WordCamps"
                    },
                    subtitle = (if (isAdmin) "Administrator · " else "Event manager · ") + staffName,
                    onBack = onBack,
                ) {
                    OrgIconButton("log-out", "Sign out", { askSignOut = true })
                }
                Box(Modifier.weight(1f)) {
                    when (tab) {
                        "dashboard" -> AdminDashboardPage(api, token, onEvent, onTab = { tab = it; OrgMemory.homeTab = it }, onExpired = onExpired)
                        "managers" -> AdminManagersPage(api, token, onExpired)
                        "more" -> AdminMorePage(api, token, onEvent, onExpired)
                        else -> EventsListPage(api, token, isAdmin, onEvent, onPreviewAttendee, onExpired)
                    }
                }
                if (isAdmin) {
                    OrgBottomBar(
                        listOf(Triple("dashboard", "Dashboard", "home"), Triple("events", "Events", "calendar"), Triple("managers", "Managers", "users"), Triple("more", "More", "columns")),
                        tab,
                    ) { tab = it; OrgMemory.homeTab = it }
                }
            }
            OrgToastHost(toast)
        }
    }

    if (askSignOut) {
        ConfirmSheet("Sign out?", "You will need your e-mail and password to sign in again on this phone.", "Sign out", onConfirm = signOut, onClose = { askSignOut = false })
    }
}

@Composable
internal fun OrgBottomBar(tabs: List<Triple<String, String, String>>, selected: String, onSelect: (String) -> Unit) {
    val brand = LocalBrand.current
    Column(Modifier.fillMaxWidth().background(brand.surface).navigationBarsPadding()) {
        Box(Modifier.fillMaxWidth().height(1.dp).background(brand.line))
        FitTabsRow {
            tabs.forEach { (key, label, icon) ->
                val on = key == selected
                Column(
                    Modifier.fillMaxHeight().clickable(role = Role.Tab, onClick = { onSelect(key) }).padding(horizontal = 2.dp),
                    horizontalAlignment = Alignment.CenterHorizontally,
                    verticalArrangement = Arrangement.Center,
                ) {
                    Box(Modifier.fillMaxWidth().widthIn(max = 48.dp).height(26.dp).background(if (on) brand.peach else Color.Transparent, RoundedCornerShape(50)), contentAlignment = Alignment.Center) {
                        LineIcon(icon, if (on) brand.primary else brand.muted, size = 20.dp)
                    }
                    Text(label, style = MaterialTheme.typography.labelMedium.copy(fontSize = 11.sp, lineHeight = 13.sp, color = if (on) brand.primaryText else brand.muted), maxLines = 2, textAlign = TextAlign.Center, modifier = Modifier.padding(top = 3.dp))
                }
            }
        }
    }
}

/** A failed load: a signed-out token goes back to the picker, anything else can be tried again. */
@Composable
internal fun OrgFailed(message: String, onRetry: () -> Unit, onExpired: () -> Unit) {
    if (message.contains("Sign in again")) {
        androidx.compose.runtime.LaunchedEffect(Unit) { onExpired() }
        return
    }
    ErrorState(message, onRetry)
}

// ---- events list -----------------------------------------------------------------------------------------

@Composable
private fun EventsListPage(api: ApiClient, token: String, isAdmin: Boolean, onEvent: (OrganizerEvent) -> Unit, onPreview: (String) -> Unit, onExpired: () -> Unit) {
    var query by remember { mutableStateOf(OrgMemory.query) }
    var status by remember { mutableStateOf(OrgMemory.status) }
    var visibility by remember { mutableStateOf(OrgMemory.visibility) }
    var whenFilter by remember { mutableStateOf(OrgMemory.whenFilter) }
    var showFilters by remember { mutableStateOf(status != null || visibility != null) }
    var newEvent by remember { mutableStateOf(false) }
    val searched = rememberDebounced(query)
    val actions = rememberOrgActions()
    androidx.compose.runtime.SideEffect { OrgMemory.query = query; OrgMemory.status = status; OrgMemory.visibility = visibility; OrgMemory.whenFilter = whenFilter }

    val loader = rememberLoader("org-events:$searched:$status:$visibility:$whenFilter") { Loaded(api.organizerEvents(token, searched, status, visibility, whenFilter), false) }
    val active = listOf(status, visibility).count { it != null }

    LazyColumn(Modifier.fillMaxSize(), contentPadding = PaddingValues(16.dp), verticalArrangement = Arrangement.spacedBy(12.dp)) {
        item {
            Row(verticalAlignment = Alignment.CenterVertically, horizontalArrangement = Arrangement.spacedBy(8.dp)) {
                OrgSearch(query, { query = it }, "Search by name, slug or short name", Modifier.weight(1f))
                OrgIconButton("filter", "Filters", { showFilters = !showFilters }, tint = if (active > 0) LocalBrand.current.primary else null)
            }
        }
        item {
            OrgFilterRow(null, listOf(null to "All", "upcoming" to "Upcoming", "past" to "Past", "undated" to "No date"), whenFilter) { whenFilter = it }
        }
        if (showFilters) item {
            Card {
                Column(verticalArrangement = Arrangement.spacedBy(10.dp)) {
                    OrgFilterRow("Status", listOf(null to "Any", "draft" to "Draft", "approved" to "Approved", "active" to "Active", "archived" to "Archived"), status) { status = it }
                    OrgFilterRow("Visibility", listOf(null to "Any", "visible" to "Visible", "hidden" to "Hidden"), visibility) { visibility = it }
                    if (active > 0) LinkButton("Clear filters", { status = null; visibility = null })
                }
            }
        }
        if (isAdmin) item {
            Row(Modifier.fillMaxWidth(), horizontalArrangement = Arrangement.spacedBy(8.dp)) {
                OrgButton("New event", { newEvent = true }, Modifier.weight(1f), kind = BtnKind.Primary, icon = "plus")
                OrgButton(if (actions.busy) "Queuing…" else "Discover WordCamps", { actions.run({ api.discoverEvents(token).message }) }, Modifier.weight(1f), icon = "compass", enabled = !actions.busy)
            }
        }
        when (val s = loader.state.value) {
            LoadState.Loading -> item { Loading() }
            is LoadState.Failed -> item { OrgFailed(s.message, loader.reload, onExpired) }
            is LoadState.Ready -> {
                item { Text(plural(s.value.size, "WordCamp") + if (s.value.size >= 200) " (first 200 — narrow the search)" else "", style = MaterialTheme.typography.bodySmall) }
                if (s.value.isEmpty()) item { EmptyNote(if (isAdmin) "No WordCamp matches these filters." else "No WordCamp of yours matches. An admin assigns WordCamps to you.", "calendar") }
                items(s.value, key = { it.id }) { event -> EventRowCard(event, onOpen = { onEvent(event) }, onPreview = { onPreview(event.slug) }) }
            }
        }
    }

    if (newEvent) NewEventSheet(api, token, onClose = { newEvent = false }, onCreated = { newEvent = false; loader.reload(); onEvent(it) })
}

@Composable
internal fun EventRowCard(event: OrganizerEvent, onOpen: () -> Unit, onPreview: (() -> Unit)? = null) {
    val brand = LocalBrand.current
    Card(onClick = onOpen) {
        Column(verticalArrangement = Arrangement.spacedBy(8.dp)) {
            Row(Modifier.fillMaxWidth(), verticalAlignment = Alignment.Top, horizontalArrangement = Arrangement.spacedBy(10.dp)) {
                Column(Modifier.weight(1f), verticalArrangement = Arrangement.spacedBy(2.dp)) {
                    Text(event.name, style = MaterialTheme.typography.titleMedium, maxLines = 2, overflow = TextOverflow.Ellipsis)
                    Text(eventDates(event.startsOn, event.endsOn), style = MaterialTheme.typography.bodySmall)
                }
                LineIcon("chevron-right", brand.muted, size = 20.dp)
            }
            Row(Modifier.fillMaxWidth().horizontalScroll(rememberScrollState()), horizontalArrangement = Arrangement.spacedBy(6.dp)) {
                event.timing?.let { OrgBadge(it.label, timingTone(it.state), "clock") }
                if (event.status.isNotBlank()) OrgBadge(statusLabel(event.status), statusTone(event.status))
                if (!event.isVisible) OrgBadge("Hidden", Tone.Warn, "eye-off")
                if (event.rosterCount > 0) OrgBadge(plural(event.rosterCount, "attendee"), Tone.Muted, "users")
                if (event.openReports > 0) OrgBadge(plural(event.openReports, "report"), Tone.Danger, "siren")
            }
            if (onPreview != null && event.slug.isNotBlank()) {
                Row(Modifier.fillMaxWidth(), horizontalArrangement = Arrangement.spacedBy(8.dp)) {
                    OrgButton("Manage", onOpen, Modifier.weight(1f), kind = BtnKind.Primary, icon = "settings")
                    OrgButton("Attendee view", onPreview, Modifier.weight(1f), icon = "eye")
                }
            }
        }
    }
}

// ---- one event -------------------------------------------------------------------------------------------

/** One WordCamp's tabs, as on the web panel: what a manager can do, and for an admin also deals, leads and activity. */
@Composable
fun OrganizerEventScreen(
    api: ApiClient,
    repository: Repository,
    event: OrganizerEvent,
    onBack: () -> Unit,
    onPreviewAttendee: (String) -> Unit = {},
) {
    val brand = LocalBrand.current
    val token = repository.organizerToken().orEmpty()
    val isAdmin = (repository.organizerRole() ?: "manager") == "admin"
    val toast = remember { OrgToast() }
    var tab by remember { mutableStateOf(OrgMemory.eventTabs[event.id] ?: "overview") }
    var stamp by remember { mutableStateOf(0) }
    val overview = rememberLoader("org-overview:${event.id}:$stamp") { Loaded(api.organizerOverview(token, event.id), false) }
    val ov = (overview.state.value as? LoadState.Ready)?.value
    val slug = ov?.slug ?: event.slug
    val name = ov?.name ?: event.name

    val tabs = buildList {
        add(Triple("overview", "Overview", "home"))
        add(Triple("details", "Details", "pencil"))
        add(Triple("info", "Information", "map-pin"))
        add(Triple("broadcast", "Announce", "megaphone"))
        add(Triple("delay", "Running late", "clock"))
        add(Triple("reports", "Reports" + ((ov?.counts?.openReports ?: event.openReports).takeIf { it > 0 }?.let { " ($it)" } ?: ""), "siren"))
        add(Triple("attendees", "Attendees", "users"))
        add(Triple("quests", "Quests", "star"))
        add(Triple("tables", "Contributor Day", "wrench"))
        add(Triple("memories", "Memories", "camera"))
        add(Triple("notifications", "Notifications", "bell"))
        add(Triple("social", "Social media", "share"))
        if (isAdmin) {
            add(Triple("deals", "Deals", "tag"))
            add(Triple("leads", "Deal leads", "inbox"))
            add(Triple("activity", "Activity", "activity"))
        }
    }
    fun open(key: String) { tab = key; OrgMemory.eventTabs[event.id] = key }

    PlatformBackHandler(true) { if (tab != "overview") open("overview") else onBack() }

    CompositionLocalProvider(LocalOrgToast provides toast) {
        Box(Modifier.fillMaxSize().background(brand.background)) {
            Column(Modifier.fillMaxSize().statusBarsPadding()) {
                OrgHeader(name, listOfNotNull(eventDates(ov?.startsOn ?: event.startsOn, ov?.endsOn ?: event.endsOn), (ov?.status ?: event.status).takeIf { it.isNotBlank() }?.let(::statusLabel)).joinToString(" · "), onBack = onBack) {
                    if (slug.isNotBlank()) OrgIconButton("eye", "Attendee view", { onPreviewAttendee(slug) })
                    OrgIconButton("refresh", "Reload", { stamp++ })
                }
                Row(
                    Modifier.fillMaxWidth().background(brand.surface).horizontalScroll(rememberScrollState()).padding(horizontal = 12.dp, vertical = 8.dp),
                    horizontalArrangement = Arrangement.spacedBy(6.dp),
                ) {
                    tabs.forEach { (key, label, icon) -> TabPill(label, icon, key == tab) { open(key) } }
                }
                Box(Modifier.fillMaxWidth().height(1.dp).background(brand.line))
                Box(Modifier.weight(1f)) {
                    androidx.compose.runtime.key(tab, stamp) {
                        val ctx = EventCtx(api, token, event.id, slug, name, isAdmin, ov, reloadOverview = overview.reload, open = ::open, onExpired = onBack)
                        when (tab) {
                            "details" -> DetailsTab(ctx, onDeleted = onBack)
                            "info" -> InformationTab(ctx)
                            "delay" -> DelayTab(ctx)
                            "broadcast" -> BroadcastTab(ctx)
                            "reports" -> ReportsTab(ctx)
                            "attendees" -> AttendeesTab(ctx)
                            "quests" -> QuestsTab(ctx)
                            "tables" -> TablesTab(ctx)
                            "memories" -> MemoriesOrgTab(ctx)
                            "notifications" -> NotificationsTab(ctx)
                            "social" -> SocialTab(ctx)
                            "deals" -> DealsTab(ctx)
                            "leads" -> LeadsTab(ctx)
                            "activity" -> EventActivityTab(ctx)
                            else -> OverviewTab(ctx, overview, onPreview = { if (slug.isNotBlank()) onPreviewAttendee(slug) })
                        }
                    }
                }
            }
            OrgToastHost(toast)
        }
    }
}

@Composable
private fun TabPill(label: String, icon: String, selected: Boolean, onClick: () -> Unit) {
    val brand = LocalBrand.current
    val shape = RoundedCornerShape(50)
    Row(
        Modifier.height(38.dp).clip(shape).background(if (selected) brand.primaryText else brand.background, shape)
            .border(1.dp, if (selected) brand.primary else brand.line, shape)
            .clickable(role = Role.Tab, onClick = onClick).padding(horizontal = 12.dp),
        horizontalArrangement = Arrangement.spacedBy(6.dp),
        verticalAlignment = Alignment.CenterVertically,
    ) {
        LineIcon(icon, if (selected) Color.White else brand.ink, size = 15.dp)
        Text(label, style = MaterialTheme.typography.labelLarge.copy(color = if (selected) Color.White else brand.ink, fontWeight = FontWeight.SemiBold, fontSize = 13.sp), maxLines = 1)
    }
}
