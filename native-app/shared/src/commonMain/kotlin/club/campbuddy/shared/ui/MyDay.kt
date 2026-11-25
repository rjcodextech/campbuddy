package club.campbuddy.shared.ui

import androidx.compose.ui.text.style.TextOverflow
import androidx.compose.ui.draw.shadow
import androidx.compose.ui.draw.clip
import androidx.compose.foundation.shape.CircleShape
import androidx.compose.foundation.layout.BoxWithConstraints
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
fun MyDayTab(event: EventDetail, sessions: List<Session>, offline: Boolean, startMine: Boolean = false) {
    val env = LocalEnv.current
    val words = LocalWords.current
    val brand = LocalBrand.current
    val user = env.user
    val scope = rememberCoroutineScope()
    val zone = EventClock.zone(event.timezone)
    var query by rememberSaveable { mutableStateOf("") }
    var mine by rememberSaveable(event.slug, startMine) { mutableStateOf(startMine) }
    var detailSession by remember { mutableStateOf<Session?>(null) }
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
    val speakersById = (rememberLoader("speakers:${event.slug}") { env.repository.speakers(event.slug) }.state.value as? LoadState.Ready)
        ?.value.orEmpty().associateBy { it.id }
    val names = speakersById.mapValues { it.value.name }
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
        Sheet(
            "Reminders", onClose = { askFor = false },
            actions = {
                SheetActions(
                    "Remind me",
                    {
                        askFor = false
                        scope.launch {
                            val granted = env.deps.reminders.requestPermission()
                            user.saveNotifications(if (granted) NotificationState(enabled = true) else NotificationState(denied = true))
                            if (!granted) TurnedDown.now()
                        }
                    },
                    "No thanks", { askFor = false; user.saveNotifications(NotificationState(declined = true)); TurnedDown.now() },
                )
            },
        ) { Text("Want CampBuddy to remind you a few minutes before the sessions you save start?", style = MaterialTheme.typography.bodyLarge) }
    }

    detailSession?.let { sx ->
        SessionDetailSheet(
            session = sx,
            zone = zone,
            speakers = sx.speakerIds.mapNotNull { speakersById[it] },
            saved = sx.id in savedIds,
            alertsEnabled = user.notifications.enabled,
            onToggleSave = { star(sx) },
            onToggleReminder = { askFor = true },
            onOpenUrl = { url -> env.deps.openUrl(url) },
            onClose = { detailSession = null },
        )
    }

    val speakerNames = names
    val days = sessions.mapNotNull { s -> EventClock.startOf(s)?.let { EventClock.day(it, zone) } }.distinct().sorted()
    val now = Clock.System.now()

    val allOverlaps = remember(savedSessions) {
        savedSessions.flatMap { s ->
            user.overlapping(event.slug, s).map { b -> minOf(s.id, b.sessionId) to maxOf(s.id, b.sessionId) }
        }.distinct()
    }
    val conflictCount = allOverlaps.size
    val totalMinutesPlanned = savedSessions.sumOf { s ->
        val start = EventClock.startOf(s)
        val end = EventClock.endOf(s)
        if (start != null && end != null && end > start) ((end.epochSeconds - start.epochSeconds) / 60).toInt() else 30
    }
    val hoursText = if (totalMinutesPlanned >= 60) "${totalMinutesPlanned / 60}h ${totalMinutesPlanned % 60}m" else "${totalMinutesPlanned}m"

    LazyColumn(Modifier.fillMaxSize(), contentPadding = PaddingValues(16.dp), verticalArrangement = Arrangement.spacedBy(12.dp)) {
        item { Text(words("tabs.my_day"), style = MaterialTheme.typography.headlineSmall) }
        if (offline) item { OfflineNote() }
        item(key = "schedule-health") {
            ScheduleHealthBanner(
                savedCount = savedSessions.size,
                hoursText = hoursText,
                conflictCount = conflictCount,
                alertsEnabled = user.notifications.enabled,
                onToggleAlerts = { askFor = true },
            )
        }
        if (conflictCount > 0) {
            item(key = "schedule-conflicts") {
                ConflictWarningCard(
                    conflictPairs = allOverlaps,
                    sessions = sessions,
                    onRemove = { s -> user.toggle(event.slug, s) },
                )
            }
        }
        item(key = "notifications-off") { NotificationsOffNotice(event.slug, event.firstDay, event.lastDay, zone) }
        item {
            Row(
                Modifier.fillMaxWidth().clip(RoundedCornerShape(50)).background(Color(0xFFE2E8F0)).padding(4.dp),
                horizontalArrangement = Arrangement.spacedBy(4.dp),
            ) {
                Segment("Full schedule", selected = !mine, modifier = Modifier.weight(1f)) { mine = false }
                Segment("My schedule (${savedSessions.size})", selected = mine, modifier = Modifier.weight(1f)) { mine = true }
            }
        }
        item { SearchField(query) { query = it } }
        message?.let { text -> item { Text(text, style = MaterialTheme.typography.bodyMedium.copy(color = brand.primaryText)) } }
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
            if (planTotal > 0) item(key = "plan-summary") {
                PlanSummary(planDone, planTotal) {
                    val file = planCalendar(event.slug, savedSessions, planPeople)
                    if (file.size > 80 && savedSessions.any { EventClock.startOf(it) != null } || planPeople.any { it.at != null }) env.deps.shareFile("${event.slug}-my-plan.ics", "text/calendar", file)
                    else message = "Nothing with a time yet. Save a session or add someone to meet first."
                }
            }
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
                                speakers = session.speakerIds.mapNotNull { speakersById[it] },
                                saved = session.id in savedIds,
                                last = i == ordered.lastIndex,
                                onStar = { star(session) },
                                onOpenDetail = { detailSession = session },
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
        item(key = "your-choices") { NotificationChoicesCard(event.slug) }
    }
}

private fun fullDayLabel(date: kotlinx.datetime.LocalDate): String {
    val weekday = date.dayOfWeek.name.lowercase().replaceFirstChar { it.uppercase() }
    val month = date.month.name.lowercase().replaceFirstChar { it.uppercase() }

    return "$weekday, $month ${date.dayOfMonth}"
}

/** Pill switcher segment matching M07 / M09. */
@Composable
private fun Segment(label: String, selected: Boolean, modifier: Modifier = Modifier, onClick: () -> Unit) {
    val brand = LocalBrand.current
    Box(
        modifier.heightIn(min = 40.dp)
            .clip(RoundedCornerShape(50))
            .background(if (selected) Color.White else Color.Transparent, RoundedCornerShape(50))
            .clickable(role = Role.Tab, onClick = onClick)
            .padding(horizontal = 12.dp, vertical = 8.dp),
        contentAlignment = Alignment.Center,
    ) {
        Text(
            label,
            style = MaterialTheme.typography.labelLarge.copy(
                color = if (selected) brand.primaryText else brand.muted,
                fontWeight = if (selected) FontWeight.Bold else FontWeight.SemiBold,
                fontSize = 13.sp,
            ),
            maxLines = 1,
            overflow = TextOverflow.Ellipsis,
        )
    }
}

@Composable
internal fun SearchField(value: String, hint: String = "Search sessions or speakers…", onChange: (String) -> Unit) {
    val brand = LocalBrand.current
    val shape = Radii.input
    Row(
        Modifier.fillMaxWidth().heightIn(min = 46.dp).background(Color.White, shape).border(1.dp, brand.line, shape).padding(horizontal = 12.dp),
        horizontalArrangement = Arrangement.spacedBy(12.dp),
        verticalAlignment = Alignment.CenterVertically,
    ) {
        LineIcon("search", brand.ink, size = 22.dp)
        Box(Modifier.weight(1f)) {
            // One line like the field itself (a long hint on a narrow phone ends in … rather than growing the box).
            if (value.isEmpty()) Text(hint, style = MaterialTheme.typography.bodyLarge.copy(color = brand.muted), maxLines = 1, overflow = TextOverflow.Ellipsis)
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
        Modifier.heightIn(min = 38.dp)
            .clip(shape)
            .background(if (selected) brand.ink else Color.White, shape)
            .border(1.dp, if (selected) brand.ink else brand.line, shape)
            .clickable(role = Role.Button, onClick = onClick)
            .padding(horizontal = 16.dp, vertical = 7.dp),
        horizontalArrangement = Arrangement.spacedBy(6.dp),
        verticalAlignment = Alignment.CenterVertically,
    ) {
        if (selected) LineIcon("check", Color.White, size = 14.dp)
        Text(label, style = MaterialTheme.typography.labelLarge.copy(color = if (selected) Color.White else brand.ink, fontSize = 13.sp))
    }
}

@Composable
private fun SessionRow(
    session: Session,
    zone: kotlinx.datetime.TimeZone,
    now: kotlinx.datetime.Instant,
    speakers: List<club.campbuddy.shared.model.Speaker>,
    saved: Boolean,
    last: Boolean,
    onStar: () -> Unit,
    onOpenDetail: () -> Unit = {},
    status: String? = null,
    started: Boolean = false,
    onStatus: (String) -> Unit = {},
) {
    val brand = LocalBrand.current
    val start = EventClock.startOf(session)
    val end = EventClock.endOf(session)
    val live = start != null && end != null && now >= start && now < end
    val type = when (session.sessionType) { "session" -> if (speakers.isNotEmpty()) "Talk" else null; "custom" -> "Activity"; null -> null; else -> session.sessionType.replaceFirstChar { it.uppercase() } }
    val timeLabel = start?.let { EventClock.time(it, zone) } ?: "TBA"
    val room = session.trackNames.firstOrNull()

    Column(
        Modifier
            .fillMaxWidth()
            .padding(vertical = 5.dp)
            .clip(RoundedCornerShape(16.dp))
            .background(Color.White)
            .border(if (live) 1.5.dp else 1.dp, if (live) Color(0xFFFB923C) else brand.line, RoundedCornerShape(16.dp))
            .clickable(role = Role.Button, onClick = onOpenDetail)
            .padding(14.dp),
        verticalArrangement = Arrangement.spacedBy(8.dp),
    ) {
        Row(Modifier.fillMaxWidth(), horizontalArrangement = Arrangement.SpaceBetween, verticalAlignment = Alignment.CenterVertically) {
            Box(
                Modifier.clip(RoundedCornerShape(50))
                    .background(if (live) brand.peach else Color(0xFFF1F5F9))
                    .padding(horizontal = 10.dp, vertical = 4.dp),
            ) {
                Text(
                    if (live) "Live · $timeLabel" else timeLabel,
                    style = MaterialTheme.typography.labelSmall.copy(
                        color = if (live) brand.primaryText else brand.muted,
                        fontWeight = FontWeight.Bold,
                    ),
                )
            }
            if (!room.isNullOrBlank()) {
                Text(
                    room,
                    style = MaterialTheme.typography.labelSmall.copy(color = brand.muted, fontWeight = FontWeight.SemiBold),
                    maxLines = 1,
                    overflow = TextOverflow.Ellipsis,
                )
            }
        }

        Text(
            session.title,
            style = MaterialTheme.typography.titleMedium.copy(fontWeight = FontWeight.Bold, fontSize = 15.5.sp, lineHeight = 20.sp, color = brand.ink),
        )

        Row(Modifier.fillMaxWidth(), horizontalArrangement = Arrangement.SpaceBetween, verticalAlignment = Alignment.CenterVertically) {
            Row(Modifier.weight(1f), horizontalArrangement = Arrangement.spacedBy(8.dp), verticalAlignment = Alignment.CenterVertically) {
                speakers.firstOrNull()?.let { leadSpeaker ->
                    Box(
                        Modifier.size(26.dp).clip(CircleShape).background(brand.peach).border(1.dp, brand.line, CircleShape),
                        contentAlignment = Alignment.Center,
                    ) {
                        RemoteImage(leadSpeaker.avatarUrl, Modifier.size(26.dp).clip(CircleShape), contentScale = androidx.compose.ui.layout.ContentScale.Crop) {
                            Text(leadSpeaker.name.take(1).uppercase(), style = MaterialTheme.typography.labelSmall.copy(color = brand.primaryText, fontWeight = FontWeight.Bold))
                        }
                    }
                }
                Column(Modifier.weight(1f), verticalArrangement = Arrangement.spacedBy(2.dp)) {
                    if (speakers.isNotEmpty()) {
                        Text(
                            speakers.joinToString(", ") { it.name },
                            style = MaterialTheme.typography.bodySmall.copy(color = brand.muted, fontWeight = FontWeight.Medium),
                            maxLines = 1,
                            overflow = TextOverflow.Ellipsis,
                        )
                    }
                    if (!type.isNullOrBlank()) {
                        Text(type.uppercase(), style = MaterialTheme.typography.labelSmall.copy(color = brand.muted, fontSize = 10.sp))
                    }
                }
            }
            Box(
                Modifier.size(40.dp).clip(CircleShape).clickable(role = Role.Button, onClick = onStar)
                    .semantics { contentDescription = if (saved) "Remove from My Day" else "Save to My Day"; role = Role.Button },
                contentAlignment = Alignment.Center,
            ) { LineIcon("star", if (saved) brand.accent else brand.muted, size = 22.dp, filled = saved) }
        }

        if (started) {
            Row(Modifier.padding(top = 2.dp), horizontalArrangement = Arrangement.spacedBy(8.dp)) {
                StatusButton("✓ Attended", status == "attended", false) { onStatus("attended") }
                StatusButton("✗ Missed", status == "missed", true) { onStatus("missed") }
            }
        }
    }
}

/** M08 Session Detail & Reminder Sheet. */
@Composable
private fun SessionDetailSheet(
    session: Session,
    zone: kotlinx.datetime.TimeZone,
    speakers: List<club.campbuddy.shared.model.Speaker>,
    saved: Boolean,
    alertsEnabled: Boolean,
    onToggleSave: () -> Unit,
    onToggleReminder: () -> Unit,
    onOpenUrl: (String) -> Unit,
    onClose: () -> Unit,
) {
    val brand = LocalBrand.current
    val timeLabel = EventClock.startOf(session)?.let { EventClock.time(it, zone) } ?: "Time TBA"
    val room = session.trackNames.firstOrNull() ?: "Main Hall"

    Sheet(title = "Session Details", onClose = onClose) {
        if (speakers.isNotEmpty()) {
            val leadSpeaker = speakers.first()
            Row(
                Modifier.fillMaxWidth().clip(RoundedCornerShape(14.dp)).background(Color.White).border(1.dp, brand.line, RoundedCornerShape(14.dp)).padding(12.dp),
                horizontalArrangement = Arrangement.spacedBy(12.dp),
                verticalAlignment = Alignment.CenterVertically,
            ) {
                Box(Modifier.size(46.dp).clip(CircleShape).background(brand.peach).border(1.dp, brand.line, CircleShape), contentAlignment = Alignment.Center) {
                    RemoteImage(leadSpeaker.avatarUrl, Modifier.size(46.dp).clip(CircleShape), contentScale = androidx.compose.ui.layout.ContentScale.Crop) {
                        LineIcon("mic", brand.primary, size = 20.dp)
                    }
                }
                Column(Modifier.weight(1f)) {
                    Text(speakers.joinToString(", ") { it.name }, style = MaterialTheme.typography.titleMedium.copy(fontWeight = FontWeight.Bold))
                    Text("Featured Speaker", style = MaterialTheme.typography.bodySmall.copy(color = brand.primaryText))
                }
            }
        }

        Row(Modifier.fillMaxWidth(), horizontalArrangement = Arrangement.SpaceBetween, verticalAlignment = Alignment.CenterVertically) {
            Box(Modifier.clip(RoundedCornerShape(50)).background(brand.peach).padding(horizontal = 12.dp, vertical = 5.dp)) {
                Text(timeLabel, style = MaterialTheme.typography.labelMedium.copy(color = brand.primaryText, fontWeight = FontWeight.Bold))
            }
            Text(room, style = MaterialTheme.typography.labelMedium.copy(color = brand.success, fontWeight = FontWeight.Bold))
        }

        Text(session.title, style = MaterialTheme.typography.titleLarge.copy(fontWeight = FontWeight.ExtraBold, fontSize = 20.sp, lineHeight = 25.sp))

        session.description?.takeIf { it.isNotBlank() }?.let {
            Text(it, style = MaterialTheme.typography.bodyMedium.copy(color = brand.muted))
        }

        Row(Modifier.fillMaxWidth(), horizontalArrangement = Arrangement.spacedBy(10.dp)) {
            Box(
                Modifier.weight(1f).heightIn(min = 46.dp).clip(RoundedCornerShape(50))
                    .background(if (saved) brand.accent else brand.primary)
                    .clickable(role = Role.Button, onClick = onToggleSave)
                    .padding(horizontal = 14.dp, vertical = 10.dp),
                contentAlignment = Alignment.Center,
            ) {
                Row(horizontalArrangement = Arrangement.spacedBy(6.dp), verticalAlignment = Alignment.CenterVertically) {
                    LineIcon("star", Color.White, size = 16.dp, filled = saved)
                    Text(if (saved) "Saved to My Day" else "Save to My Day", style = MaterialTheme.typography.labelLarge.copy(color = Color.White, fontSize = 13.sp))
                }
            }
        }

        Row(
            Modifier.fillMaxWidth().clip(RoundedCornerShape(14.dp)).background(Color.White).border(1.dp, brand.line, RoundedCornerShape(14.dp))
                .clickable(role = Role.Switch, onClick = onToggleReminder).padding(14.dp),
            horizontalArrangement = Arrangement.SpaceBetween,
            verticalAlignment = Alignment.CenterVertically,
        ) {
            Column(Modifier.weight(1f)) {
                Text("Remind me 10 mins before", style = MaterialTheme.typography.titleMedium.copy(fontSize = 14.sp))
                Text("Works offline at the venue", style = MaterialTheme.typography.bodySmall.copy(color = brand.success))
            }
            Box(
                Modifier.size(width = 48.dp, height = 28.dp).background(if (alertsEnabled) brand.success else Color(0xFFCBD5E1), RoundedCornerShape(50)).padding(3.dp),
                contentAlignment = if (alertsEnabled) Alignment.CenterEnd else Alignment.CenterStart,
            ) {
                Box(Modifier.size(22.dp).background(Color.White, CircleShape))
            }
        }

        Row(
            Modifier.fillMaxWidth().clip(RoundedCornerShape(14.dp)).background(Color.White).border(1.dp, brand.line, RoundedCornerShape(14.dp)).padding(14.dp),
            horizontalArrangement = Arrangement.spacedBy(10.dp),
            verticalAlignment = Alignment.CenterVertically,
        ) {
            LineIcon("map-pin", brand.primary, size = 20.dp)
            Column {
                Text(room, style = MaterialTheme.typography.titleMedium.copy(fontSize = 14.sp))
                Text("Venue hall & session track", style = MaterialTheme.typography.bodySmall.copy(color = brand.muted))
            }
        }
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

@Composable
private fun ScheduleHealthBanner(
    savedCount: Int,
    hoursText: String,
    conflictCount: Int,
    alertsEnabled: Boolean,
    onToggleAlerts: () -> Unit,
) {
    val brand = LocalBrand.current
    val shape = RoundedCornerShape(20.dp)

    Column(
        Modifier
            .fillMaxWidth()
            .shadow(2.dp, shape, clip = false)
            .clip(shape)
            .background(brand.surface, shape)
            .border(1.dp, brand.line, shape)
            .padding(16.dp),
        verticalArrangement = Arrangement.spacedBy(12.dp),
    ) {
        Row(
            Modifier.fillMaxWidth(),
            horizontalArrangement = Arrangement.SpaceBetween,
            verticalAlignment = Alignment.CenterVertically,
        ) {
            Row(horizontalArrangement = Arrangement.spacedBy(10.dp), verticalAlignment = Alignment.CenterVertically) {
                Box(
                    Modifier.size(38.dp).background(brand.peach, CircleShape),
                    contentAlignment = Alignment.Center,
                ) {
                    LineIcon("calendar", brand.primary, size = 20.dp)
                }
                Column {
                    Text(
                        "Schedule Health",
                        style = MaterialTheme.typography.titleMedium.copy(fontWeight = FontWeight.Bold),
                    )
                    Text(
                        "$savedCount bookmarked · $hoursText planned",
                        style = MaterialTheme.typography.bodySmall.copy(color = brand.muted),
                    )
                }
            }

            Row(
                Modifier
                    .clip(RoundedCornerShape(50))
                    .background(if (conflictCount == 0) brand.success.copy(alpha = 0.12f) else brand.danger.copy(alpha = 0.12f))
                    .border(1.dp, if (conflictCount == 0) brand.success.copy(alpha = 0.35f) else brand.danger.copy(alpha = 0.35f), RoundedCornerShape(50))
                    .padding(horizontal = 10.dp, vertical = 4.dp),
                horizontalArrangement = Arrangement.spacedBy(4.dp),
                verticalAlignment = Alignment.CenterVertically,
            ) {
                LineIcon(if (conflictCount == 0) "check" else "shield-alert", if (conflictCount == 0) brand.success else brand.danger, size = 12.dp)
                Text(
                    if (conflictCount == 0) "0 Conflicts" else "$conflictCount Conflicts",
                    style = MaterialTheme.typography.labelSmall.copy(
                        color = if (conflictCount == 0) brand.success else brand.danger,
                        fontWeight = FontWeight.Bold,
                    ),
                )
            }
        }

        Row(
            Modifier
                .fillMaxWidth()
                .clip(RoundedCornerShape(10.dp))
                .background(brand.peach.copy(alpha = 0.45f))
                .clickable(role = Role.Button, onClick = onToggleAlerts)
                .padding(horizontal = 12.dp, vertical = 8.dp),
            horizontalArrangement = Arrangement.SpaceBetween,
            verticalAlignment = Alignment.CenterVertically,
        ) {
            Row(horizontalArrangement = Arrangement.spacedBy(8.dp), verticalAlignment = Alignment.CenterVertically) {
                LineIcon("bell", brand.primary, size = 16.dp)
                Text(
                    if (alertsEnabled) "10-minute prior alerts active" else "Alerts off · Tap to enable reminders",
                    style = MaterialTheme.typography.labelMedium.copy(
                        color = brand.ink,
                        fontWeight = FontWeight.SemiBold,
                    ),
                )
            }
            Text(
                if (alertsEnabled) "Change" else "Turn on",
                style = MaterialTheme.typography.labelSmall.copy(color = brand.primary, fontWeight = FontWeight.Bold),
            )
        }
    }
}

@Composable
private fun ConflictWarningCard(
    conflictPairs: List<Pair<Long, Long>>,
    sessions: List<Session>,
    onRemove: (Session) -> Unit,
) {
    val brand = LocalBrand.current
    val shape = RoundedCornerShape(14.dp)
    val sessionMap = remember(sessions) { sessions.associateBy { it.id } }

    Column(
        Modifier
            .fillMaxWidth()
            .background(brand.danger.copy(alpha = 0.08f), shape)
            .border(1.dp, brand.danger.copy(alpha = 0.3f), shape)
            .padding(14.dp),
        verticalArrangement = Arrangement.spacedBy(8.dp),
    ) {
        Row(horizontalArrangement = Arrangement.spacedBy(8.dp), verticalAlignment = Alignment.CenterVertically) {
            LineIcon("shield-alert", brand.danger, size = 20.dp)
            Text(
                "Overlapping Talk Conflicts Detected",
                style = MaterialTheme.typography.titleMedium.copy(color = brand.danger, fontWeight = FontWeight.Bold),
            )
        }
        Text(
            "You have bookmarked simultaneous sessions in different rooms. Remove one or choose your primary talk:",
            style = MaterialTheme.typography.bodySmall.copy(color = brand.ink),
        )

        conflictPairs.take(3).forEach { (idA, idB) ->
            val sA = sessionMap[idA]
            val sB = sessionMap[idB]
            if (sA != null && sB != null) {
                Row(
                    Modifier
                        .fillMaxWidth()
                        .clip(RoundedCornerShape(8.dp))
                        .background(Color.White)
                        .padding(horizontal = 10.dp, vertical = 6.dp),
                    horizontalArrangement = Arrangement.SpaceBetween,
                    verticalAlignment = Alignment.CenterVertically,
                ) {
                    Column(Modifier.weight(1f).padding(end = 6.dp)) {
                        Text(
                            "${sA.title} vs ${sB.title}",
                            style = MaterialTheme.typography.bodySmall.copy(fontWeight = FontWeight.Bold),
                            maxLines = 1,
                            overflow = TextOverflow.Ellipsis,
                        )
                        Text(
                            "${sA.trackNames.firstOrNull() ?: "Room A"} / ${sB.trackNames.firstOrNull() ?: "Room B"}",
                            style = MaterialTheme.typography.labelSmall.copy(color = brand.muted),
                            maxLines = 1,
                            overflow = TextOverflow.Ellipsis,
                        )
                    }
                    Text(
                        "Remove one",
                        style = MaterialTheme.typography.labelSmall.copy(color = brand.primary, fontWeight = FontWeight.Bold),
                        modifier = Modifier.clickable { onRemove(sB) }.padding(4.dp),
                    )
                }
            }
        }
    }
}
