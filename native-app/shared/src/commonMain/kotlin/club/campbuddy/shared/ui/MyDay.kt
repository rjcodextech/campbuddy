package club.campbuddy.shared.ui

import androidx.compose.foundation.border
import androidx.compose.foundation.background
import androidx.compose.foundation.layout.ExperimentalLayoutApi
import androidx.compose.foundation.layout.FlowRow
import androidx.compose.foundation.layout.height
import androidx.compose.foundation.layout.heightIn
import androidx.compose.foundation.layout.offset
import androidx.compose.ui.text.style.TextDecoration
import androidx.compose.foundation.text.BasicTextField
import androidx.compose.ui.graphics.Color
import androidx.compose.ui.graphics.SolidColor
import androidx.compose.ui.text.font.FontWeight
import androidx.compose.ui.unit.sp
import androidx.compose.foundation.clickable
import androidx.compose.foundation.layout.Arrangement
import androidx.compose.foundation.layout.Box
import androidx.compose.foundation.layout.PaddingValues
import androidx.compose.foundation.layout.Row
import androidx.compose.foundation.layout.Column
import androidx.compose.foundation.layout.fillMaxSize
import androidx.compose.foundation.layout.fillMaxWidth
import androidx.compose.foundation.layout.width
import androidx.compose.foundation.layout.padding
import androidx.compose.foundation.layout.size
import androidx.compose.foundation.lazy.LazyColumn
import androidx.compose.foundation.lazy.items
import androidx.compose.foundation.shape.RoundedCornerShape
import androidx.compose.material3.AlertDialog
import androidx.compose.material3.FilterChip
import androidx.compose.material3.MaterialTheme
import androidx.compose.material3.OutlinedTextField
import androidx.compose.material3.Text
import androidx.compose.material3.TextButton
import androidx.compose.runtime.Composable
import androidx.compose.runtime.LaunchedEffect
import androidx.compose.runtime.getValue
import androidx.compose.runtime.mutableStateOf
import androidx.compose.runtime.remember
import androidx.compose.runtime.rememberCoroutineScope
import androidx.compose.runtime.saveable.rememberSaveable
import androidx.compose.runtime.setValue
import androidx.compose.ui.Alignment
import androidx.compose.ui.Modifier
import androidx.compose.ui.semantics.Role
import androidx.compose.ui.semantics.contentDescription
import androidx.compose.ui.semantics.role
import androidx.compose.ui.semantics.semantics
import androidx.compose.ui.unit.dp
import club.campbuddy.shared.data.NotificationState
import club.campbuddy.shared.domain.EventClock
import club.campbuddy.shared.domain.Reminders
import club.campbuddy.shared.model.EventDetail
import club.campbuddy.shared.model.Session
import kotlinx.coroutines.launch
import kotlinx.datetime.Clock

/**
 * My Day: the full schedule and "My schedule". A star saves a session on this phone (nothing is
 * sent anywhere); the first save asks once about reminders, and a "no" is never asked again.
 */
@OptIn(ExperimentalLayoutApi::class)
@Composable
fun MyDayTab(event: EventDetail, sessions: List<Session>, offline: Boolean) {
    val env = LocalEnv.current
    val words = LocalWords.current
    val brand = LocalBrand.current
    val user = env.user
    val scope = rememberCoroutineScope()
    val zone = EventClock.zone(event.timezone)
    var query by rememberSaveable { mutableStateOf("") }
    var mine by rememberSaveable { mutableStateOf(false) }
    var dayFilter by rememberSaveable { mutableStateOf<String?>(null) }
    var trackFilter by rememberSaveable { mutableStateOf<String?>(null) }
    var typeFilter by rememberSaveable { mutableStateOf<String?>(null) }
    var moreOpen by rememberSaveable { mutableStateOf(false) }
    var planFilter by rememberSaveable { mutableStateOf("all") }
    var message by remember { mutableStateOf<String?>(null) }
    var askFor by remember { mutableStateOf<Boolean>(false) }

    // A "running late" change moves times: keep what is saved in step.
    LaunchedEffect(sessions) { user.refreshBookmarks(event.slug, sessions) }

    val savedIds = user.bookmarks(event.slug).map { it.sessionId }.toSet()
    val statusById = user.bookmarks(event.slug).associate { it.sessionId to it.status }
    val nowInstant = Clock.System.now()
    fun sessionDone(sx: Session): Boolean {
        val start = EventClock.startOf(sx) ?: return statusById[sx.id] != null
        val end = EventClock.endOf(sx)?.takeIf { it > start } ?: kotlinx.datetime.Instant.fromEpochSeconds(start.epochSeconds + 30 * 60)
        return statusById[sx.id] != null || end <= nowInstant
    }
    val meetings = user.meetings(event.slug)
    val planPeople = meetings.filter { it.status != "skipped" }
    val hiddenPeople = meetings.filter { it.status == "skipped" }
    val savedSessions = sessions.filter { it.id in savedIds }
    val planTotal = savedSessions.size + planPeople.size
    val planDone = savedSessions.count { sessionDone(it) } + planPeople.count { it.status != null }
    val counts = mapOf(
        "all" to planTotal,
        "todo" to savedSessions.count { !sessionDone(it) } + planPeople.count { it.status == null },
        "done" to savedSessions.count { sessionDone(it) && statusById[it.id] != "missed" } + planPeople.count { it.status == "met" },
        "missed" to savedSessions.count { statusById[it.id] == "missed" } + planPeople.count { it.status == "missed" },
        "hidden" to hiddenPeople.size,
    )
    fun inFilter(done: Boolean, missed: Boolean): Boolean = when (planFilter) {
        "todo" -> !done; "done" -> done && !missed; "missed" -> missed; "hidden" -> false; else -> true
    }
    val pool = if (mine) savedSessions.filter { inFilter(sessionDone(it), statusById[it.id] == "missed") } else sessions
    val names = (rememberLoader("speakers:${event.slug}") { env.repository.speakers(event.slug) }.state.value as? LoadState.Ready)
        ?.value.orEmpty().associate { it.id to it.name }
    val shown = pool.filter { s ->
        (query.isBlank() || s.title.contains(query, ignoreCase = true) || s.trackNames.any { t -> t.contains(query, ignoreCase = true) } ||
            s.speakerIds.any { names[it]?.contains(query, ignoreCase = true) == true }) &&
            (dayFilter == null || EventClock.startOf(s)?.let { EventClock.day(it, zone).toString() } == dayFilter) &&
            (trackFilter == null || trackFilter in s.trackNames) && (typeFilter == null || s.sessionType == typeFilter)
    }
    val byDay = shown.groupBy { s -> EventClock.startOf(s)?.let { EventClock.day(it, zone) } }
        .toList().sortedBy { it.first?.toEpochDays() ?: Int.MAX_VALUE }

    fun star(session: Session) {
        val overlaps = user.overlapping(event.slug, session)
        val saved = user.toggle(event.slug, session)
        message = if (saved && overlaps.isNotEmpty()) "This overlaps with ${overlaps.first().title.ifBlank { "another saved session" }}. Both are saved." else null
        val notes = user.notifications
        if (saved && !notes.enabled && !notes.declined && !notes.denied) askFor = true
    }

    if (askFor) {
        AlertDialog(
            onDismissRequest = { askFor = false },
            title = { Text("Reminders") },
            text = { Text("Want CampBuddy to remind you a few minutes before the sessions you save start?") },
            confirmButton = {
                LinkButton("Remind me", onClick = {
                    askFor = false
                    scope.launch {
                        val granted = env.deps.reminders.requestPermission()
                        user.saveNotifications(if (granted) NotificationState(enabled = true) else NotificationState(denied = true))
                    }
                })
            },
            dismissButton = {
                LinkButton("No thanks", onClick = { askFor = false; user.saveNotifications(NotificationState(declined = true)) })
            },
        )
    }

    val speakerNames = names
    val days = sessions.mapNotNull { s -> EventClock.startOf(s)?.let { EventClock.day(it, zone) } }.distinct().sorted()
    val now = Clock.System.now()

    LazyColumn(Modifier.fillMaxSize(), contentPadding = PaddingValues(16.dp), verticalArrangement = Arrangement.spacedBy(12.dp)) {
        item { Text(words("tabs.my_day"), style = MaterialTheme.typography.headlineSmall) }
        if (offline) item { OfflineNote() }
        item {
            Row(horizontalArrangement = Arrangement.spacedBy(12.dp)) {
                Segment("Full schedule", selected = !mine) { mine = false }
                Segment("My schedule", selected = mine) { mine = true }
            }
        }
        item { SearchField(query) { query = it } }
        message?.let { text -> item { Text(text, style = MaterialTheme.typography.bodyMedium.copy(color = brand.primary)) } }
        if (days.size > 1) {
            item {
                Column(verticalArrangement = Arrangement.spacedBy(8.dp)) {
                    Text("DAY", style = MaterialTheme.typography.labelMedium.copy(letterSpacing = 1.sp))
                    FlowRow(horizontalArrangement = Arrangement.spacedBy(8.dp), verticalArrangement = Arrangement.spacedBy(8.dp)) {
                        DayPill("All days", selected = dayFilter == null) { dayFilter = null }
                        days.forEach { d -> DayPill(dayChip(d), selected = dayFilter == d.toString()) { dayFilter = d.toString() } }
                    }
                }
            }
        }
        item {
            val tracks = sessions.flatMap { it.trackNames }.distinct().sorted()
            val types = sessions.mapNotNull { it.sessionType }.distinct().sorted()
            if (tracks.size + types.size > 1) {
                Column(verticalArrangement = Arrangement.spacedBy(12.dp)) {
                    val pill = RoundedCornerShape(50)
                    Row(
                        Modifier.heightIn(min = 44.dp).background(brand.surface, pill).border(1.dp, brand.line, pill).clickable(role = Role.Button) { moreOpen = !moreOpen }.padding(horizontal = 16.dp),
                        horizontalArrangement = Arrangement.spacedBy(8.dp), verticalAlignment = Alignment.CenterVertically,
                    ) {
                        LineIcon("filter", brand.ink, size = 16.dp)
                        Text("More filters", style = MaterialTheme.typography.labelLarge.copy(color = brand.ink))
                    }
                    if (moreOpen) {
                        if (tracks.size > 1) {
                            Text("TRACK", style = MaterialTheme.typography.labelMedium.copy(letterSpacing = 0.72.sp))
                            FlowRow(horizontalArrangement = Arrangement.spacedBy(8.dp), verticalArrangement = Arrangement.spacedBy(8.dp)) {
                                DayPill("All tracks", selected = trackFilter == null) { trackFilter = null }
                                tracks.forEach { t -> DayPill(t, selected = trackFilter == t) { trackFilter = t } }
                            }
                        }
                        if (types.size > 1) {
                            Text("TYPE", style = MaterialTheme.typography.labelMedium.copy(letterSpacing = 0.72.sp))
                            FlowRow(horizontalArrangement = Arrangement.spacedBy(8.dp), verticalArrangement = Arrangement.spacedBy(8.dp)) {
                                DayPill("All types", selected = typeFilter == null) { typeFilter = null }
                                types.forEach { t -> DayPill(when (t) { "session" -> "Talk"; "custom" -> "Activity"; else -> t.replaceFirstChar { it.uppercase() } }, selected = typeFilter == t) { typeFilter = t } }
                            }
                        }
                    }
                }
            }
        }
        if (mine && planTotal + hiddenPeople.size > 0) {
            if (planTotal > 0) item(key = "plan-summary") { PlanSummary(planDone, planTotal) }
            item(key = "plan-filters") { PlanFilters(counts, planFilter) { planFilter = it } }
        }
        if (shown.isEmpty() && !(mine && planPeople.isNotEmpty())) {
            item {
                if (mine && planTotal + hiddenPeople.size == 0) PlanEmpty("Your day is still empty", "Tap the ★ next to any session in Full schedule to save it here. Tip: pick three or four, and leave room for the hallway track.")
                else if (mine) Text("Nothing in this filter.", style = MaterialTheme.typography.bodyMedium)
                else if (sessions.isEmpty()) PlanEmpty("The schedule isn't published yet", "Organizers usually share it a few weeks before the event. It will show up here by itself once they do.")
                else Text("No sessions match. Try clearing a filter or the search.", style = MaterialTheme.typography.bodyMedium)
            }
        }

        byDay.forEach { (day, list) ->
            item(key = "day-$day") {
                Card {
                    Column {
                        Text(
                            (day?.let { fullDayLabel(it) } ?: "Time to be announced").uppercase(),
                            style = MaterialTheme.typography.labelLarge.copy(color = brand.muted, letterSpacing = 0.84.sp),
                            modifier = Modifier.padding(bottom = 8.dp),
                        )
                        val ordered = list.sortedBy { EventClock.startOf(it) }
                        ordered.forEachIndexed { i, session ->
                            SessionRow(
                                session, zone, now,
                                speakers = session.speakerIds.mapNotNull { speakerNames[it] },
                                saved = session.id in savedIds,
                                last = i == ordered.lastIndex,
                                onStar = { star(session) },
                                status = if (mine) statusById[session.id] else null,
                                started = mine && (EventClock.startOf(session)?.let { it <= nowInstant } == true),
                                onStatus = { st -> user.setSessionStatus(event.slug, session.id, st) },
                            )
                        }
                    }
                }
            }
        }
        if (mine) {
            val people = (if (planFilter == "hidden") hiddenPeople else planPeople.filter { inFilter(it.status != null, it.status == "missed") })
                .sortedBy { it.at ?: "~" }
            item(key = "people-title") { Text(if (planFilter == "hidden") "Hidden people" else "People to meet", style = MaterialTheme.typography.headlineSmall, modifier = Modifier.padding(top = 8.dp)) }
            if (people.isEmpty()) {
                item(key = "people-empty") {
                    Text(
                        if (planPeople.isEmpty() && hiddenPeople.isEmpty()) "No one yet. In Explore → People, tap + Meet next to anyone and add a note." else "No one in this filter.",
                        style = MaterialTheme.typography.bodyMedium.copy(color = brand.muted),
                    )
                }
            }
            people.forEach { m -> item(key = "person-${m.rosterId}") { PersonPlanCard(event.slug, m, hidden = planFilter == "hidden") } }
        }
        item(key = "data-controls") { DataControls(event.slug) }
    }
}

private fun fullDayLabel(date: kotlinx.datetime.LocalDate): String {
    val weekday = date.dayOfWeek.name.lowercase().replaceFirstChar { it.uppercase() }
    val month = date.month.name.lowercase().replaceFirstChar { it.uppercase() }

    return "$weekday, $month ${date.dayOfMonth}"
}

/** The website's two-way switch: the chosen side is peach, the other has a thin border. */
@Composable
private fun Segment(label: String, selected: Boolean, onClick: () -> Unit) {
    val brand = LocalBrand.current
    Box(
        Modifier.heightIn(min = 44.dp)
            .background(if (selected) brand.peach else Color.Transparent, RoundedCornerShape(4.dp))
            .border(1.dp, if (selected) Color.Transparent else brand.line, RoundedCornerShape(4.dp))
            .clickable(role = Role.Tab, onClick = onClick)
            .padding(horizontal = 12.dp, vertical = 8.dp),
        contentAlignment = Alignment.Center,
    ) { Text(label, style = MaterialTheme.typography.labelLarge.copy(color = brand.ink)) }
}

@Composable
internal fun SearchField(value: String, hint: String = "Search sessions or speakers…", onChange: (String) -> Unit) {
    val brand = LocalBrand.current
    val shape = RoundedCornerShape(4.dp)
    Row(
        Modifier.fillMaxWidth().heightIn(min = 46.dp).background(Color.White, shape).border(1.dp, brand.line, shape).padding(horizontal = 12.dp),
        horizontalArrangement = Arrangement.spacedBy(12.dp),
        verticalAlignment = Alignment.CenterVertically,
    ) {
        LineIcon("search", brand.ink, size = 22.dp)
        Box(Modifier.weight(1f)) {
            if (value.isEmpty()) Text(hint, style = MaterialTheme.typography.bodyLarge.copy(color = brand.muted))
            BasicTextField(
                value = value,
                onValueChange = onChange,
                singleLine = true,
                textStyle = MaterialTheme.typography.bodyLarge,
                cursorBrush = SolidColor(brand.primary),
                modifier = Modifier.fillMaxWidth().semantics { contentDescription = hint.removeSuffix("…") },
            )
        }
    }
}

@Composable
private fun DayPill(label: String, selected: Boolean, onClick: () -> Unit) {
    val brand = LocalBrand.current
    val shape = RoundedCornerShape(50)
    Row(
        Modifier.heightIn(min = 48.dp)
            .background(if (selected) brand.accent else brand.surface, shape)
            .border(1.dp, if (selected) brand.accent else brand.line, shape)
            .clickable(role = Role.Button, onClick = onClick)
            .padding(horizontal = 20.dp),
        horizontalArrangement = Arrangement.spacedBy(8.dp),
        verticalAlignment = Alignment.CenterVertically,
    ) {
        if (selected) Text("✓", style = MaterialTheme.typography.labelLarge.copy(color = brand.ink))
        Text(label, style = MaterialTheme.typography.labelLarge.copy(color = brand.ink))
    }
}

@Composable
private fun SessionRow(session: Session, zone: kotlinx.datetime.TimeZone, now: kotlinx.datetime.Instant, speakers: List<String>, saved: Boolean, last: Boolean, onStar: () -> Unit, status: String? = null, started: Boolean = false, onStatus: (String) -> Unit = {}) {
    val brand = LocalBrand.current
    var open by remember { mutableStateOf(false) }
    val start = EventClock.startOf(session)
    val end = EventClock.endOf(session)
    val live = start != null && end != null && now >= start && now < end
    val hasMore = !session.description.isNullOrBlank()
    val type = when (session.sessionType) { "session" -> "Talk"; "custom" -> "Activity"; null -> null; else -> session.sessionType.replaceFirstChar { it.uppercase() } }

    Column(Modifier.fillMaxWidth().background(if (live) brand.peach else Color.Transparent)) {
        Row(Modifier.fillMaxWidth().padding(vertical = 12.dp), horizontalArrangement = Arrangement.spacedBy(12.dp), verticalAlignment = Alignment.Top) {
            Text(
                start?.let { EventClock.time(it, zone) } ?: "TBA",
                style = MaterialTheme.typography.labelMedium.copy(color = brand.muted, fontWeight = FontWeight.SemiBold),
                modifier = Modifier.padding(top = 2.dp).width(64.dp),
            )
            Column(Modifier.weight(1f)) {
                Row(
                    Modifier.fillMaxWidth().heightIn(min = 21.dp).clickable(enabled = hasMore, role = Role.Button) { open = !open },
                    verticalAlignment = Alignment.CenterVertically, horizontalArrangement = Arrangement.spacedBy(4.dp),
                ) {
                    SoftUnderlineText(
                        session.title,
                        style = MaterialTheme.typography.bodyLarge.copy(lineHeight = 20.8.sp),
                        modifier = Modifier.weight(1f, fill = false),
                    )
                    if (hasMore) LineIcon("chevron-down", brand.muted, size = 14.dp)
                }
                if (speakers.isNotEmpty()) Text(speakers.joinToString(", "), style = MaterialTheme.typography.bodyMedium.copy(color = brand.muted), modifier = Modifier.padding(top = 4.dp))
                val tags = listOfNotNull(session.trackNames.firstOrNull(), type).joinToString(" · ").uppercase()
                if (live || tags.isNotEmpty()) {
                    Row(Modifier.padding(top = 4.dp), horizontalArrangement = Arrangement.spacedBy(12.dp), verticalAlignment = Alignment.CenterVertically) {
                        if (live) Text("● LIVE NOW", style = MaterialTheme.typography.labelMedium.copy(color = brand.primary, letterSpacing = 0.72.sp))
                        if (tags.isNotEmpty()) Text(tags, style = MaterialTheme.typography.labelMedium.copy(letterSpacing = 0.72.sp, fontWeight = FontWeight.SemiBold))
                    }
                }
                if (started) {
                    Row(Modifier.padding(top = 8.dp), horizontalArrangement = Arrangement.spacedBy(8.dp)) {
                        StatusButton("✓ Attended", status == "attended", false) { onStatus("attended") }
                        StatusButton("✗ Missed", status == "missed", true) { onStatus("missed") }
                    }
                }
                if (open && hasMore) Text(session.description.orEmpty(), style = MaterialTheme.typography.bodyMedium, modifier = Modifier.padding(top = 8.dp))
            }
            Box(
                Modifier.offset(x = 10.dp).size(44.dp).clickable(role = Role.Button, onClick = onStar)
                    .semantics { contentDescription = if (saved) "Remove from My Day" else "Save to My Day"; role = Role.Button },
                contentAlignment = Alignment.Center,
            ) { LineIcon("star", if (saved) brand.accent else brand.muted, size = 22.dp, filled = saved) }
        }
        if (!last) Box(Modifier.fillMaxWidth().height(1.dp).background(brand.line))
    }
}

/** Plans every saved session's reminder again whenever something changes (and the person said yes). */
@Composable
fun ReminderSync() {
    val env = LocalEnv.current
    val user = env.user

    LaunchedEffect(user.revision, user.notifications.enabled) {
        if (!user.notifications.enabled) {
            env.deps.reminders.replaceAll(emptyList())
            return@LaunchedEffect
        }
        if (!env.deps.reminders.hasPermission()) return@LaunchedEffect

        val now = Clock.System.now().toEpochMilliseconds()
        val planned = user.allBookmarks().flatMap { (slug, list) ->
            list.map { b -> Reminders.forSession(slug, slug, Session(id = b.sessionId, title = b.title, startsAt = b.startsAt, endsAt = b.endsAt), now) }
        }
        env.deps.reminders.replaceAll(Reminders.soonest(planned))
    }
}

private fun dayChip(date: kotlinx.datetime.LocalDate): String {
    val weekday = date.dayOfWeek.name.take(3).lowercase().replaceFirstChar { it.uppercase() }
    val month = date.month.name.take(3).lowercase().replaceFirstChar { it.uppercase() }

    return "$weekday, $month ${date.dayOfMonth}"
}
