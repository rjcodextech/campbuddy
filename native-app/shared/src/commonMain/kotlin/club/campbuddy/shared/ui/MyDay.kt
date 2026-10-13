package club.campbuddy.shared.ui

import androidx.compose.foundation.border
import androidx.compose.foundation.background
import androidx.compose.foundation.layout.ExperimentalLayoutApi
import androidx.compose.foundation.layout.FlowRow
import androidx.compose.foundation.layout.heightIn
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
    var message by remember { mutableStateOf<String?>(null) }
    var askFor by remember { mutableStateOf<Boolean>(false) }

    // A "running late" change moves times: keep what is saved in step.
    LaunchedEffect(sessions) { user.refreshBookmarks(event.slug, sessions) }

    val savedIds = user.bookmarks(event.slug).map { it.sessionId }.toSet()
    val pool = if (mine) sessions.filter { it.id in savedIds } else sessions
    val names = (rememberLoader("speakers:${event.slug}") { env.repository.speakers(event.slug) }.state.value as? LoadState.Ready)
        ?.value.orEmpty().associate { it.id to it.name }
    val shown = pool.filter { s ->
        (query.isBlank() || s.title.contains(query, ignoreCase = true) || s.trackNames.any { t -> t.contains(query, ignoreCase = true) } ||
            s.speakerIds.any { names[it]?.contains(query, ignoreCase = true) == true }) &&
            (dayFilter == null || EventClock.startOf(s)?.let { EventClock.day(it, zone).toString() } == dayFilter)
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
                Segment("My schedule (${savedIds.size})", selected = mine) { mine = true }
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
                        days.forEach { d -> DayPill(EventClock.dayLabel(d), selected = dayFilter == d.toString()) { dayFilter = d.toString() } }
                    }
                }
            }
        }
        if (shown.isEmpty()) {
            item {
                Text(if (mine) "Nothing saved yet. Tap the star on a session to add it to your day." else "No sessions to show yet.", style = MaterialTheme.typography.bodyMedium)
            }
        }

        byDay.forEach { (day, list) ->
            item(key = "day-$day") {
                Card {
                    Column(verticalArrangement = Arrangement.spacedBy(8.dp)) {
                        Text(
                            (day?.let { fullDayLabel(it) } ?: "Time to be announced").uppercase(),
                            style = MaterialTheme.typography.labelLarge.copy(color = brand.muted, letterSpacing = 1.sp),
                        )
                        list.sortedBy { EventClock.startOf(it) }.forEach { session ->
                            SessionRow(
                                session, zone, now,
                                speakers = session.speakerIds.mapNotNull { speakerNames[it] },
                                saved = session.id in savedIds,
                                onStar = { star(session) },
                            )
                        }
                    }
                }
            }
        }
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
    val shape = RoundedCornerShape(brand.radius.dp)
    Box(
        Modifier.heightIn(min = 48.dp)
            .background(if (selected) brand.peach else Color.Transparent, shape)
            .border(1.dp, if (selected) brand.peach else brand.line, shape)
            .clickable(role = Role.Tab, onClick = onClick)
            .padding(horizontal = 20.dp),
        contentAlignment = Alignment.Center,
    ) { Text(label, style = MaterialTheme.typography.labelLarge.copy(color = brand.ink)) }
}

@Composable
private fun SearchField(value: String, onChange: (String) -> Unit) {
    val brand = LocalBrand.current
    val shape = RoundedCornerShape(brand.radius.dp)
    Row(
        Modifier.fillMaxWidth().heightIn(min = 52.dp).background(Color.White, shape).border(1.dp, brand.line, shape).padding(horizontal = 14.dp),
        horizontalArrangement = Arrangement.spacedBy(12.dp),
        verticalAlignment = Alignment.CenterVertically,
    ) {
        LineIcon("search", brand.ink, size = 22.dp)
        Box(Modifier.weight(1f)) {
            if (value.isEmpty()) Text("Search sessions or speakers…", style = MaterialTheme.typography.bodyLarge.copy(color = brand.muted))
            BasicTextField(
                value = value,
                onValueChange = onChange,
                singleLine = true,
                textStyle = MaterialTheme.typography.bodyLarge,
                cursorBrush = SolidColor(brand.primary),
                modifier = Modifier.fillMaxWidth().semantics { contentDescription = "Search sessions or speakers" },
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
private fun SessionRow(session: Session, zone: kotlinx.datetime.TimeZone, now: kotlinx.datetime.Instant, speakers: List<String>, saved: Boolean, onStar: () -> Unit) {
    val brand = LocalBrand.current
    var open by remember { mutableStateOf(false) }
    val start = EventClock.startOf(session)
    val end = EventClock.endOf(session)
    val live = start != null && end != null && now >= start && now < end
    val shape = RoundedCornerShape(brand.radius.dp)
    val hasMore = !session.description.isNullOrBlank()

    Column(
        Modifier.fillMaxWidth()
            .background(if (live) brand.peach else Color.Transparent, shape)
            .clickable(enabled = hasMore, role = Role.Button) { open = !open }
            .padding(12.dp),
        verticalArrangement = Arrangement.spacedBy(4.dp),
    ) {
        Row(horizontalArrangement = Arrangement.spacedBy(12.dp), verticalAlignment = Alignment.Top) {
            Text(
                start?.let { EventClock.time(it, zone) } ?: "TBA",
                style = MaterialTheme.typography.labelMedium.copy(color = brand.muted),
                modifier = Modifier.padding(top = 4.dp).width(64.dp),
            )
            Column(Modifier.weight(1f), verticalArrangement = Arrangement.spacedBy(2.dp)) {
                Row(verticalAlignment = Alignment.Top, horizontalArrangement = Arrangement.spacedBy(6.dp)) {
                    Text(session.title, style = MaterialTheme.typography.titleMedium.copy(fontWeight = FontWeight.Normal), modifier = Modifier.weight(1f, fill = false))
                    if (hasMore) Box(Modifier.padding(top = 6.dp)) { LineIcon("chevron", brand.muted, size = 16.dp) }
                }
                if (speakers.isNotEmpty()) Text(speakers.joinToString(", "), style = MaterialTheme.typography.bodyMedium.copy(color = brand.muted))
                val tags = listOfNotNull(session.trackNames.firstOrNull(), session.sessionType).joinToString(" · ").uppercase()
                if (live || tags.isNotEmpty()) {
                    Row(horizontalArrangement = Arrangement.spacedBy(12.dp), verticalAlignment = Alignment.CenterVertically) {
                        if (live) Text("● LIVE NOW", style = MaterialTheme.typography.labelMedium.copy(color = brand.primary, fontWeight = FontWeight.Bold, letterSpacing = 1.sp))
                        if (tags.isNotEmpty()) Text(tags, style = MaterialTheme.typography.labelMedium.copy(letterSpacing = 1.sp))
                    }
                }
            }
            Box(
                Modifier.size(48.dp).clickable(role = Role.Button, onClick = onStar)
                    .semantics { contentDescription = if (saved) "Remove from My Day" else "Save to My Day"; role = Role.Button },
                contentAlignment = Alignment.Center,
            ) { LineIcon("star", if (saved) brand.accent else brand.muted, size = 28.dp, filled = saved) }
        }
        if (open && hasMore) {
            Text(session.description.orEmpty(), style = MaterialTheme.typography.bodyMedium, modifier = Modifier.padding(start = 76.dp, top = 4.dp))
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
