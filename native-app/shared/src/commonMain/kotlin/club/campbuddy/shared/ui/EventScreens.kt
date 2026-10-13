package club.campbuddy.shared.ui

import androidx.compose.foundation.background
import androidx.compose.foundation.clickable
import androidx.compose.foundation.layout.Arrangement
import androidx.compose.foundation.layout.Box
import androidx.compose.foundation.layout.Column
import androidx.compose.foundation.layout.PaddingValues
import androidx.compose.foundation.layout.Row
import androidx.compose.foundation.layout.fillMaxSize
import androidx.compose.foundation.layout.fillMaxWidth
import androidx.compose.foundation.layout.navigationBarsPadding
import androidx.compose.foundation.layout.padding
import androidx.compose.foundation.layout.statusBarsPadding
import androidx.compose.foundation.lazy.LazyColumn
import androidx.compose.foundation.lazy.items
import androidx.compose.foundation.shape.RoundedCornerShape
import androidx.compose.material3.MaterialTheme
import androidx.compose.material3.NavigationBar
import androidx.compose.material3.NavigationBarItem
import androidx.compose.material3.NavigationBarItemDefaults
import androidx.compose.material3.OutlinedTextField
import androidx.compose.material3.Text
import androidx.compose.runtime.Composable
import androidx.compose.runtime.LaunchedEffect
import androidx.compose.runtime.getValue
import androidx.compose.runtime.mutableStateOf
import androidx.compose.runtime.produceState
import androidx.compose.runtime.remember
import androidx.compose.runtime.saveable.rememberSaveable
import androidx.compose.runtime.setValue
import androidx.compose.ui.Alignment
import androidx.compose.ui.Modifier
import androidx.compose.ui.unit.dp
import club.campbuddy.shared.PlatformBackHandler
import club.campbuddy.shared.data.Repository
import club.campbuddy.shared.domain.EventClock
import club.campbuddy.shared.domain.Phase
import club.campbuddy.shared.model.EventDetail
import club.campbuddy.shared.model.SectionConfig
import club.campbuddy.shared.model.Session
import kotlinx.coroutines.delay
import kotlinx.datetime.Clock
import kotlinx.datetime.Instant
import kotlinx.datetime.TimeZone

/** One WordCamp: the bottom tabs come from the server config; screens not built yet say so. */
@Composable
fun EventScreen(repository: Repository, slug: String, onBack: () -> Unit) {
    val words = LocalWords.current
    val brand = LocalBrand.current
    val eventLoader = rememberLoader("event:$slug") { repository.event(slug) }
    val sessionsLoader = rememberLoader("sessions:$slug") { repository.sessions(slug) }
    val event by eventLoader.state
    val sessions by sessionsLoader.state
    val tabs = words.config.enabledTabs()
    var selected by rememberSaveable(slug) { mutableStateOf(tabs.firstOrNull()?.key ?: "home") }

    PlatformBackHandler(enabled = true, onBack = onBack)

    Column(Modifier.fillMaxSize().background(brand.background)) {
        Box(Modifier.weight(1f).statusBarsPadding()) {
            when (val e = event) {
                LoadState.Loading -> Loading()
                is LoadState.Failed -> ErrorState(e.message) { eventLoader.reload(); sessionsLoader.reload() }
                is LoadState.Ready -> {
                    val list = (sessions as? LoadState.Ready)?.value.orEmpty()
                    val offline = e.fromCache || (sessions as? LoadState.Ready)?.fromCache == true
                    when (selected) {
                        "home" -> HomeTab(e.value, list, offline, onBack)
                        "my-day" -> ScheduleTab(e.value, list, offline)
                        else -> SoonTab(words(tabs.firstOrNull { it.key == selected }?.title ?: "tabs.home"))
                    }
                }
            }
        }

        NavigationBar(containerColor = brand.surface, modifier = Modifier.navigationBarsPadding()) {
            tabs.forEach { tab ->
                NavigationBarItem(
                    selected = tab.key == selected,
                    onClick = { selected = tab.key },
                    icon = { LineIcon(tab.icon, if (tab.key == selected) brand.primary else brand.muted) },
                    label = { Text(words(tab.title), style = MaterialTheme.typography.labelMedium, maxLines = 1) },
                    colors = NavigationBarItemDefaults.colors(indicatorColor = brand.background),
                )
            }
        }
    }
}

@Composable
private fun SoonTab(title: String) {
    Column(Modifier.fillMaxSize().padding(24.dp), verticalArrangement = Arrangement.Center, horizontalAlignment = Alignment.CenterHorizontally) {
        Text(title, style = MaterialTheme.typography.titleLarge)
        Text("This screen is being built in the native app. The website has it today.", style = MaterialTheme.typography.bodyMedium, modifier = Modifier.padding(top = 8.dp))
    }
}

/** A clock that moves every 30 seconds, so "starts in 12 min" stays true while the screen is open. */
@Composable
private fun rememberNow(): Instant {
    val now by produceState(Clock.System.now()) {
        while (true) {
            delay(30_000)
            value = Clock.System.now()
        }
    }

    return now
}

@Composable
private fun HomeTab(event: EventDetail, sessions: List<Session>, offline: Boolean, onBack: () -> Unit) {
    val words = LocalWords.current
    val zone = EventClock.zone(event.timezone)
    val now = rememberNow()
    val phase = EventClock.phase(sessions, event.firstDay, event.lastDay, zone, now)

    LazyColumn(Modifier.fillMaxSize(), contentPadding = PaddingValues(16.dp), verticalArrangement = Arrangement.spacedBy(12.dp)) {
        if (offline) item { OfflineNote() }

        val sections = words.config.enabledSections().filter { visible(it, phase) }
        items(sections.size) { index ->
            val section = sections[index]
            when (section.type) {
                "hero" -> HeroCard(event, phase, zone, now, sessions, onBack)
                "happening_now" -> HappeningNow(section, phase, sessions, now, zone, event)
                "up_next" -> UpNext(section, phase, sessions, now, zone)
                else -> Unit // blocks the app does not draw yet are skipped, never an error
            }
        }
    }
}

private fun visible(section: SectionConfig, phase: Phase): Boolean = when (section.visibleIf) {
    "phase:before" -> phase == Phase.Before
    "phase:during" -> phase == Phase.During
    "phase:after" -> phase == Phase.After
    else -> true
}

@Composable
private fun HeroCard(event: EventDetail, phase: Phase, zone: TimeZone, now: Instant, sessions: List<Session>, onBack: () -> Unit) {
    Card {
        Column(verticalArrangement = Arrangement.spacedBy(4.dp)) {
            Text(event.shortName ?: "WordCamp", style = MaterialTheme.typography.labelMedium)
            Text(event.name, style = MaterialTheme.typography.headlineSmall)
            datesText(event.startsOn, event.endsOn)?.let { Text(it, style = MaterialTheme.typography.bodyMedium) }
            Text(statusText(event, phase, zone, now, sessions), style = MaterialTheme.typography.titleMedium)
            QuietButton("All WordCamps", onBack, Modifier.padding(top = 8.dp))
        }
    }
}

private fun statusText(event: EventDetail, phase: Phase, zone: TimeZone, now: Instant, sessions: List<Session>): String = when (phase) {
    Phase.Before -> when (val days = EventClock.daysUntil(event.firstDay, zone, now)) {
        null -> "Coming soon"
        0 -> sessions.firstNotNullOfOrNull { EventClock.startOf(it) }?.let { "WordCamp starts today at ${EventClock.time(it, zone)}" } ?: "WordCamp starts today"
        1 -> "WordCamp starts tomorrow"
        else -> "WordCamp starts in $days days"
    }
    Phase.During -> "Happening now"
    Phase.After -> "This WordCamp has finished"
}

@Composable
private fun HappeningNow(section: SectionConfig, phase: Phase, sessions: List<Session>, now: Instant, zone: TimeZone, event: EventDetail) {
    val words = LocalWords.current
    SectionTitle(section.title?.let(words::invoke) ?: "Happening now")
    Card {
        val current = EventClock.current(sessions, now)
        Column(verticalArrangement = Arrangement.spacedBy(6.dp)) {
            when {
                phase == Phase.After -> Text("That is a wrap. Thanks for coming!", style = MaterialTheme.typography.bodyLarge)
                phase == Phase.Before -> Text(statusText(event, phase, zone, now, sessions), style = MaterialTheme.typography.bodyLarge)
                current.isEmpty() -> Text("Nothing on stage right now. Talk to someone nearby, or go see a sponsor booth.", style = MaterialTheme.typography.bodyMedium)
                else -> current.forEach { SessionLine(it, zone, showTime = true) }
            }
        }
    }
}

@Composable
private fun UpNext(section: SectionConfig, phase: Phase, sessions: List<Session>, now: Instant, zone: TimeZone) {
    val words = LocalWords.current
    if (phase == Phase.After) return
    val next = EventClock.upNext(sessions, now) ?: return
    val start = EventClock.startOf(next) ?: return
    val alternatives = EventClock.alternatives(sessions, next).size

    SectionTitle(section.title?.let(words::invoke) ?: "Up next")
    Card {
        Column(verticalArrangement = Arrangement.spacedBy(4.dp)) {
            Text(next.title, style = MaterialTheme.typography.titleMedium)
            Text(EventClock.when_(start, now, zone) + (next.trackNames.firstOrNull()?.let { " · $it" } ?: ""), style = MaterialTheme.typography.bodyMedium)
            if (alternatives > 0) {
                Text(
                    (if (alternatives == 1) "Another session runs" else "$alternatives other sessions run") + " at the same time.",
                    style = MaterialTheme.typography.bodySmall,
                )
            }
        }
    }
}

@Composable
private fun SessionLine(session: Session, zone: TimeZone, showTime: Boolean) {
    Column {
        Text(session.title, style = MaterialTheme.typography.titleMedium)
        val start = EventClock.startOf(session)
        val meta = listOfNotNull(start?.takeIf { showTime }?.let { EventClock.time(it, zone) }, session.trackNames.firstOrNull()).joinToString(" · ")
        if (meta.isNotEmpty()) Text(meta, style = MaterialTheme.typography.bodySmall)
    }
}

/** The full schedule, grouped by day, with search. Times are the event's own. */
@Composable
private fun ScheduleTab(event: EventDetail, sessions: List<Session>, offline: Boolean) {
    val words = LocalWords.current
    val brand = LocalBrand.current
    val zone = EventClock.zone(event.timezone)
    var query by rememberSaveable { mutableStateOf("") }
    val shown = sessions.filter { query.isBlank() || it.title.contains(query, ignoreCase = true) || it.trackNames.any { t -> t.contains(query, ignoreCase = true) } }
    val byDay = shown.groupBy { s -> EventClock.startOf(s)?.let { EventClock.day(it, zone) } }
        .toList().sortedBy { it.first?.toEpochDays() ?: Int.MAX_VALUE }

    LazyColumn(Modifier.fillMaxSize(), contentPadding = PaddingValues(16.dp), verticalArrangement = Arrangement.spacedBy(8.dp)) {
        item { Text(words("tabs.my_day"), style = MaterialTheme.typography.headlineSmall) }
        if (offline) item { OfflineNote() }
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
        if (shown.isEmpty()) item { Text("No sessions to show yet.", style = MaterialTheme.typography.bodyMedium) }

        byDay.forEach { (day, list) ->
            item(key = "day-$day") { SectionTitle(day?.let { EventClock.dayLabel(it) } ?: "Time to be announced") }
            items(list.sortedBy { EventClock.startOf(it) }, key = { it.id }) { session ->
                Card {
                    Row(horizontalArrangement = Arrangement.spacedBy(12.dp)) {
                        Text(EventClock.startOf(session)?.let { EventClock.time(it, zone) } ?: "TBA", style = MaterialTheme.typography.labelMedium, modifier = Modifier.padding(top = 2.dp))
                        Column(Modifier.weight(1f)) {
                            Text(session.title, style = MaterialTheme.typography.titleMedium)
                            session.trackNames.firstOrNull()?.let { Text(it, style = MaterialTheme.typography.bodySmall) }
                        }
                    }
                }
            }
        }
    }
}
