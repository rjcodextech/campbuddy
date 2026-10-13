package club.campbuddy.shared.ui

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
    var message by remember { mutableStateOf<String?>(null) }
    var askFor by remember { mutableStateOf<Boolean>(false) }

    // A "running late" change moves times: keep what is saved in step.
    LaunchedEffect(sessions) { user.refreshBookmarks(event.slug, sessions) }

    val savedIds = user.bookmarks(event.slug).map { it.sessionId }.toSet()
    val pool = if (mine) sessions.filter { it.id in savedIds } else sessions
    val shown = pool.filter { query.isBlank() || it.title.contains(query, ignoreCase = true) || it.trackNames.any { t -> t.contains(query, ignoreCase = true) } }
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
                TextButton(onClick = {
                    askFor = false
                    scope.launch {
                        val granted = env.deps.reminders.requestPermission()
                        user.saveNotifications(if (granted) NotificationState(enabled = true) else NotificationState(denied = true))
                    }
                }) { Text("Remind me") }
            },
            dismissButton = {
                TextButton(onClick = { askFor = false; user.saveNotifications(NotificationState(declined = true)) }) { Text("No thanks") }
            },
        )
    }

    LazyColumn(Modifier.fillMaxSize(), contentPadding = PaddingValues(16.dp), verticalArrangement = Arrangement.spacedBy(8.dp)) {
        item { Text(words("tabs.my_day"), style = MaterialTheme.typography.headlineSmall) }
        if (offline) item { OfflineNote() }
        item {
            Row(horizontalArrangement = Arrangement.spacedBy(8.dp)) {
                FilterChip(selected = !mine, onClick = { mine = false }, label = { Text("Full schedule") })
                FilterChip(selected = mine, onClick = { mine = true }, label = { Text("My schedule (${savedIds.size})") })
            }
        }
        item {
            OutlinedTextField(
                value = query,
                onValueChange = { query = it },
                modifier = Modifier.fillMaxWidth(),
                placeholder = { Text("Search sessions or speakers") },
                singleLine = true,
                shape = RoundedCornerShape(brand.radius.dp),
            )
        }
        message?.let { text -> item { Text(text, style = MaterialTheme.typography.bodyMedium.copy(color = brand.primary)) } }
        if (shown.isEmpty()) {
            item {
                Text(if (mine) "Nothing saved yet. Tap the star on a session to add it to your day." else "No sessions to show yet.", style = MaterialTheme.typography.bodyMedium)
            }
        }

        byDay.forEach { (day, list) ->
            item(key = "day-$day") { SectionTitle(day?.let { EventClock.dayLabel(it) } ?: "Time to be announced") }
            items(list.sortedBy { EventClock.startOf(it) }, key = { it.id }) { session ->
                SessionCard(session, zone, saved = session.id in savedIds, onStar = { star(session) })
            }
        }
    }
}

@Composable
private fun SessionCard(session: Session, zone: kotlinx.datetime.TimeZone, saved: Boolean, onStar: () -> Unit) {
    val brand = LocalBrand.current
    var open by remember { mutableStateOf(false) }

    Card(onClick = { open = !open }) {
        Column(verticalArrangement = Arrangement.spacedBy(4.dp)) {
            Row(horizontalArrangement = Arrangement.spacedBy(12.dp), verticalAlignment = Alignment.Top) {
                Text(
                    EventClock.startOf(session)?.let { EventClock.time(it, zone) } ?: "TBA",
                    style = MaterialTheme.typography.labelMedium,
                    modifier = Modifier.padding(top = 4.dp).width(64.dp),
                )
                Column(Modifier.weight(1f)) {
                    Text(session.title, style = MaterialTheme.typography.titleMedium)
                    session.trackNames.firstOrNull()?.let { Text(it, style = MaterialTheme.typography.bodySmall) }
                }
                Box(
                    Modifier.size(48.dp).clickable(role = Role.Button, onClick = onStar)
                        .semantics { contentDescription = if (saved) "Remove from My Day" else "Save to My Day"; role = Role.Button },
                    contentAlignment = Alignment.Center,
                ) { LineIcon("star", if (saved) brand.accent else brand.muted, filled = saved) }
            }
            if (open && !session.description.isNullOrBlank()) {
                Text(session.description, style = MaterialTheme.typography.bodyMedium, modifier = Modifier.padding(top = 4.dp))
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
