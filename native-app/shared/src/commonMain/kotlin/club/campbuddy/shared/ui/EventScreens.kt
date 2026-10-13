package club.campbuddy.shared.ui

import androidx.compose.foundation.background
import androidx.compose.foundation.layout.Arrangement
import androidx.compose.foundation.layout.Box
import androidx.compose.foundation.layout.Column
import androidx.compose.foundation.layout.PaddingValues
import androidx.compose.foundation.layout.fillMaxSize
import androidx.compose.foundation.layout.fillMaxWidth
import androidx.compose.foundation.layout.navigationBarsPadding
import androidx.compose.foundation.layout.padding
import androidx.compose.foundation.layout.statusBarsPadding
import androidx.compose.foundation.lazy.LazyColumn
import androidx.compose.material3.LinearProgressIndicator
import androidx.compose.material3.MaterialTheme
import androidx.compose.foundation.Image
import androidx.compose.foundation.layout.size
import campbuddy_native.shared.generated.resources.Res
import campbuddy_native.shared.generated.resources.nav_camp_card
import campbuddy_native.shared.generated.resources.nav_contribute
import campbuddy_native.shared.generated.resources.nav_explore
import campbuddy_native.shared.generated.resources.nav_home
import campbuddy_native.shared.generated.resources.nav_my_day
import campbuddy_native.shared.generated.resources.nav_quest
import org.jetbrains.compose.resources.painterResource
import androidx.compose.foundation.border
import androidx.compose.foundation.clickable
import androidx.compose.foundation.layout.Row
import androidx.compose.foundation.layout.height
import androidx.compose.foundation.shape.CircleShape
import androidx.compose.foundation.shape.RoundedCornerShape
import androidx.compose.ui.graphics.Color
import androidx.compose.ui.semantics.Role
import androidx.compose.ui.text.font.FontWeight
import androidx.compose.ui.unit.sp
import campbuddy_native.shared.generated.resources.app_icon
import campbuddy_native.shared.generated.resources.wordmark
import kotlinx.datetime.LocalDate
import kotlinx.datetime.daysUntil
import androidx.compose.material3.NavigationBar
import androidx.compose.material3.NavigationBarItem
import androidx.compose.material3.NavigationBarItemDefaults
import androidx.compose.material3.Text
import androidx.compose.runtime.Composable
import androidx.compose.runtime.getValue
import androidx.compose.runtime.mutableStateOf
import androidx.compose.runtime.produceState
import androidx.compose.runtime.saveable.rememberSaveable
import androidx.compose.runtime.setValue
import androidx.compose.ui.Alignment
import androidx.compose.ui.Modifier
import androidx.compose.ui.unit.dp
import club.campbuddy.shared.PlatformBackHandler
import club.campbuddy.shared.domain.EventClock
import club.campbuddy.shared.domain.Phase
import club.campbuddy.shared.model.EventDetail
import club.campbuddy.shared.model.SectionConfig
import club.campbuddy.shared.model.Session
import kotlinx.coroutines.delay
import kotlinx.datetime.Clock
import kotlinx.datetime.Instant
import kotlinx.datetime.TimeZone

/** One WordCamp: the bottom tabs and the Home blocks come from the server config. */
@Composable
fun EventScreen(slug: String, onBack: () -> Unit) {
    val env = LocalEnv.current
    val words = LocalWords.current
    val brand = LocalBrand.current
    val eventLoader = rememberLoader("event:$slug") { env.repository.event(slug) }
    val sessionsLoader = rememberLoader("sessions:$slug") { env.repository.sessions(slug) }
    val event by eventLoader.state
    val sessions by sessionsLoader.state
    val tabs = words.config.enabledTabs()
    var selected by rememberSaveable(slug) { mutableStateOf(tabs.firstOrNull()?.key ?: "home") }
    var guide by rememberSaveable(slug) { mutableStateOf(false) }

    if (guide) {
        GuideScreen(slug) { guide = false }
        return
    }
    PlatformBackHandler(enabled = true, onBack = onBack)

    Column(Modifier.fillMaxSize().background(brand.background).statusBarsPadding()) {
        TopBar(onGuide = { guide = true }, onRefresh = { eventLoader.reload(); sessionsLoader.reload() })
        Box(Modifier.weight(1f)) {
            when (val e = event) {
                LoadState.Loading -> Loading()
                is LoadState.Failed -> ErrorState(e.message) { eventLoader.reload(); sessionsLoader.reload() }
                is LoadState.Ready -> {
                    val list = (sessions as? LoadState.Ready)?.value.orEmpty()
                    val offline = e.fromCache || (sessions as? LoadState.Ready)?.fromCache == true
                    when (selected) {
                        "home" -> HomeTab(e.value, list, offline, onBack, onTab = { selected = it }, onGuide = { guide = true })
                        "my-day" -> MyDayTab(e.value, list, offline)
                        "quest" -> QuestTab(slug) { selected = it }
                        "contribute" -> ContributeTab(slug)
                        "explore" -> ExploreTab(slug)
                        "camp-card" -> CampCardTab()
                        "memories" -> MemoriesTab(slug)
                        else -> Unit
                    }
                }
            }
        }

        NavigationBar(containerColor = brand.surface, modifier = Modifier.navigationBarsPadding()) {
            tabs.forEach { tab ->
                NavigationBarItem(
                    selected = tab.key == selected,
                    onClick = { selected = tab.key },
                    icon = { TabIcon(tab.key, tab.icon, tab.key == selected) },
                    label = { Text(words(tab.title), style = MaterialTheme.typography.labelMedium, maxLines = 1) },
                    colors = NavigationBarItemDefaults.colors(indicatorColor = brand.peach, selectedTextColor = brand.primary, unselectedTextColor = brand.muted),
                )
            }
        }
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
private fun HomeTab(event: EventDetail, sessions: List<Session>, offline: Boolean, onBack: () -> Unit, onTab: (String) -> Unit, onGuide: () -> Unit) {
    val env = LocalEnv.current
    val words = LocalWords.current
    val zone = EventClock.zone(event.timezone)
    val now = rememberNow()
    val phase = EventClock.phase(sessions, event.firstDay, event.lastDay, zone, now)
    val profile = env.user.profile
    val saved = env.user.bookmarks(event.slug)

    LazyColumn(Modifier.fillMaxSize(), contentPadding = PaddingValues(16.dp), verticalArrangement = Arrangement.spacedBy(12.dp)) {
        if (offline) item { OfflineNote() }

        val sections = words.config.enabledSections().filter { visible(it, phase, profile.firstWordCamp, saved.isNotEmpty()) }
        items(sections.size) { index ->
            val section = sections[index]
            when (section.type) {
                "hero" -> HeroCard(event, phase, zone, now, sessions, onBack)
                "starting_soon" -> StartingSoon(sessions, saved.map { it.sessionId }.toSet(), now, zone)
                "happening_now" -> HappeningNow(section, phase, sessions, now, zone, event)
                "up_next" -> UpNext(section, phase, sessions, now, zone) { onTab("my-day") }
                "start_here" -> {
                    SectionTitle(section.title?.let(words::invoke) ?: "New to WordCamp? Start here")
                    Card(onClick = onGuide) {
                        Column(verticalArrangement = Arrangement.spacedBy(4.dp)) {
                            Text("How the day runs, the words you will hear, and how to meet people. A 5-minute read.", style = MaterialTheme.typography.bodyMedium)
                            Text("Read the guide →", style = MaterialTheme.typography.labelMedium.copy(color = LocalBrand.current.primary))
                        }
                    }
                }
                "suggested_action" -> {
                    SectionTitle(section.title?.let(words::invoke) ?: "One thing to try")
                    Card(onClick = { onTab(if (saved.size < 3) "my-day" else "quest") }) {
                        Text(if (saved.size < 3) "Save 3 sessions you would enjoy." else "Tick off a quest.", style = MaterialTheme.typography.bodyLarge)
                    }
                }
                "people_cta" -> {
                    SectionTitle(section.title?.let(words::invoke) ?: "Meet people")
                    Card(onClick = { onTab("explore") }) { Text("See who is attending and find people to meet.", style = MaterialTheme.typography.bodyLarge) }
                }
                "progress" -> Progress(section, event.slug)
                else -> Unit // blocks the app does not draw yet are skipped, never an error
            }
        }
    }
}

private fun visible(section: SectionConfig, phase: Phase, firstWordCamp: String?, hasSaved: Boolean): Boolean = when (section.visibleIf) {
    "phase:before" -> phase == Phase.Before
    "phase:during" -> phase == Phase.During
    "phase:after" -> phase == Phase.After
    "first_timer" -> firstWordCamp == "yes"
    "returning" -> firstWordCamp == "no"
    "not_returning" -> firstWordCamp != "no"
    "has_saved" -> hasSaved
    else -> true
}

@Composable
private fun HeroCard(event: EventDetail, phase: Phase, zone: TimeZone, now: Instant, sessions: List<Session>, onBack: () -> Unit) {
    val brand = LocalBrand.current
    val shape = RoundedCornerShape(brand.radius.dp)
    Card {
        Column(verticalArrangement = Arrangement.spacedBy(8.dp)) {
            Row(horizontalArrangement = Arrangement.spacedBy(16.dp), verticalAlignment = Alignment.CenterVertically) {
                Box(
                    Modifier.size(88.dp).background(brand.background, shape).border(1.dp, brand.line, shape),
                    contentAlignment = Alignment.Center,
                ) { Image(painterResource(Res.drawable.app_icon), contentDescription = null, modifier = Modifier.size(64.dp)) }
                Column(Modifier.weight(1f), verticalArrangement = Arrangement.spacedBy(2.dp)) {
                    Text((event.shortName ?: "WordCamp").uppercase(), style = MaterialTheme.typography.labelMedium.copy(color = brand.primary, fontWeight = FontWeight.Bold, letterSpacing = 1.sp))
                    Text(event.name, style = MaterialTheme.typography.headlineSmall)
                    datesText(event.startsOn, event.endsOn)?.let { Text(it, style = MaterialTheme.typography.bodyMedium.copy(color = brand.muted)) }
                }
            }
            StatusPill(statusPillText(event, phase, zone, now, sessions), live = phase == Phase.During)
            LinkButton("All WordCamps", onBack)
        }
    }
}

@Composable
private fun StatusPill(text: String, live: Boolean) {
    val brand = LocalBrand.current
    val shape = RoundedCornerShape(50)
    Row(
        Modifier.background(brand.background, shape).border(1.dp, brand.line, shape).padding(horizontal = 12.dp, vertical = 6.dp),
        horizontalArrangement = Arrangement.spacedBy(8.dp),
        verticalAlignment = Alignment.CenterVertically,
    ) {
        Box(Modifier.size(10.dp).background(if (live) brand.success else brand.muted, CircleShape))
        Text(text, style = MaterialTheme.typography.labelLarge.copy(color = brand.ink))
    }
}

/** "Day 1 of 2 · happening now" while it runs, else the plain status. */
private fun statusPillText(event: EventDetail, phase: Phase, zone: TimeZone, now: Instant, sessions: List<Session>): String {
    if (phase != Phase.During) return statusText(event, phase, zone, now, sessions)
    val first = runCatching { LocalDate.parse(event.firstDay.orEmpty()) }.getOrNull() ?: return "Happening now"
    val last = runCatching { LocalDate.parse(event.lastDay.orEmpty()) }.getOrNull() ?: return "Happening now"
    val total = first.daysUntil(last) + 1
    val day = (first.daysUntil(EventClock.day(now, zone)) + 1).coerceIn(1, total)

    return "Day $day of $total · happening now"
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

/** A saved session that starts in the next 15 minutes gets a banner right on Home. */
@Composable
private fun StartingSoon(sessions: List<Session>, savedIds: Set<Long>, now: Instant, zone: TimeZone) {
    val soon = sessions.firstOrNull { s ->
        val start = EventClock.startOf(s) ?: return@firstOrNull false
        s.id in savedIds && start > now && (start.epochSeconds - now.epochSeconds) <= 15 * 60
    } ?: return

    Card {
        Column(verticalArrangement = Arrangement.spacedBy(2.dp)) {
            Text("Starting soon", style = MaterialTheme.typography.labelMedium)
            Text(soon.title, style = MaterialTheme.typography.titleMedium)
            EventClock.startOf(soon)?.let { Text(EventClock.when_(it, now, zone) + (soon.trackNames.firstOrNull()?.let { t -> " · $t" } ?: ""), style = MaterialTheme.typography.bodyMedium) }
        }
    }
}

@Composable
private fun HappeningNow(section: SectionConfig, phase: Phase, sessions: List<Session>, now: Instant, zone: TimeZone, event: EventDetail) {
    val words = LocalWords.current
    val brand = LocalBrand.current
    val env = LocalEnv.current
    val current = EventClock.current(sessions, now)
    val guide = rememberLoader("guide:${event.slug}") { env.repository.guide(event.slug) }.state.value as? LoadState.Ready
    val shape = RoundedCornerShape(brand.radius.dp)

    SectionTitle(section.title?.let(words::invoke) ?: "Happening now")
    if (phase != Phase.During || current.isEmpty()) {
        Card {
            when {
                phase == Phase.After -> Text("That is a wrap. Thanks for coming!", style = MaterialTheme.typography.bodyLarge)
                phase == Phase.Before -> Text(statusText(event, phase, zone, now, sessions), style = MaterialTheme.typography.bodyLarge)
                else -> Text("Nothing on stage right now. Talk to someone nearby, or go see a sponsor booth.", style = MaterialTheme.typography.bodyMedium)
            }
        }
        return
    }

    val moment = current.firstNotNullOfOrNull { s -> guide?.value?.moments?.firstOrNull { m -> m.match.any { s.title.contains(it, ignoreCase = true) } } }
    Column(
        Modifier.fillMaxWidth().background(brand.peach, shape).border(1.5.dp, brand.primary, shape).padding(16.dp),
        verticalArrangement = Arrangement.spacedBy(12.dp),
    ) {
        Text(
            "NOW",
            style = MaterialTheme.typography.labelMedium.copy(color = Color.White, fontWeight = FontWeight.Bold, letterSpacing = 1.sp),
            modifier = Modifier.background(brand.primary, RoundedCornerShape(50)).padding(horizontal = 12.dp, vertical = 4.dp),
        )
        current.forEachIndexed { i, session ->
            if (i > 0) Box(Modifier.fillMaxWidth().height(1.dp).background(brand.primary.copy(alpha = 0.25f)))
            Column {
                Text(session.title, style = MaterialTheme.typography.titleMedium)
                val end = EventClock.endOf(session)
                val meta = listOfNotNull(session.trackNames.firstOrNull(), end?.let { "until ${EventClock.time(it, zone)}" }).joinToString(" · ")
                if (meta.isNotEmpty()) Text(meta, style = MaterialTheme.typography.bodyMedium.copy(color = brand.muted))
            }
        }
        moment?.takeIf { it.now.isNotBlank() }?.let {
            Row(horizontalArrangement = Arrangement.spacedBy(8.dp)) {
                Text("→", style = MaterialTheme.typography.bodyLarge.copy(color = brand.primary))
                Text(it.now, style = MaterialTheme.typography.bodyLarge)
            }
        }
    }
}

@Composable
private fun UpNext(section: SectionConfig, phase: Phase, sessions: List<Session>, now: Instant, zone: TimeZone, onSchedule: () -> Unit) {
    val words = LocalWords.current
    if (phase == Phase.After) return
    val next = EventClock.upNext(sessions, now) ?: return
    val start = EventClock.startOf(next) ?: return
    val alternatives = EventClock.alternatives(sessions, next).size

    Row(Modifier.fillMaxWidth(), horizontalArrangement = Arrangement.SpaceBetween, verticalAlignment = Alignment.Bottom) {
        SectionTitle(section.title?.let(words::invoke) ?: "Up next")
        LinkButton("Full schedule →", onSchedule)
    }
    Card {
        Column(verticalArrangement = Arrangement.spacedBy(4.dp)) {
            Text(next.title, style = MaterialTheme.typography.titleMedium)
            Text(EventClock.when_(start, now, zone) + (next.trackNames.firstOrNull()?.let { " · $it" } ?: ""), style = MaterialTheme.typography.bodyMedium)
            if (alternatives > 0) {
                Text((if (alternatives == 1) "Another session runs" else "$alternatives other sessions run") + " at the same time.", style = MaterialTheme.typography.bodySmall)
            }
        }
    }
}

@Composable
private fun SessionLine(session: Session, zone: TimeZone) {
    Column {
        Text(session.title, style = MaterialTheme.typography.titleMedium)
        val start = EventClock.startOf(session)
        val meta = listOfNotNull(start?.let { EventClock.time(it, zone) }, session.trackNames.firstOrNull()).joinToString(" · ")
        if (meta.isNotEmpty()) Text(meta, style = MaterialTheme.typography.bodySmall)
    }
}

/** Quests done out of all of this event's quests (the count the website shows). */
@Composable
private fun Progress(section: SectionConfig, slug: String) {
    val env = LocalEnv.current
    val words = LocalWords.current
    val brand = LocalBrand.current
    val loader = rememberLoader("quests:$slug") { env.repository.quests(slug) }
    val ready = loader.state.value as? LoadState.Ready ?: return
    val total = ready.value.size
    if (total == 0) return
    val done = env.user.completedQuests(slug).count { id -> ready.value.any { it.id == id } }

    SectionTitle(section.title?.let(words::invoke) ?: "Your progress")
    Card {
        Column(verticalArrangement = Arrangement.spacedBy(6.dp)) {
            Text("$done of $total quests done", style = MaterialTheme.typography.bodyMedium)
            LinearProgressIndicator(progress = { done.toFloat() / total }, color = brand.accent, trackColor = brand.line, modifier = Modifier.fillMaxWidth())
        }
    }
}

/** The website's own coloured tab drawings where it has one for the tab, else the plain line icon. */
@Composable
private fun TabIcon(key: String, icon: String, selected: Boolean) {
    val brand = LocalBrand.current
    val art = when (key) {
        "home" -> Res.drawable.nav_home
        "my-day" -> Res.drawable.nav_my_day
        "quest" -> Res.drawable.nav_quest
        "contribute" -> Res.drawable.nav_contribute
        "explore" -> Res.drawable.nav_explore
        "camp-card" -> Res.drawable.nav_camp_card
        else -> null
    }
    if (art != null) {
        Image(painterResource(art), contentDescription = null, modifier = Modifier.size(28.dp))
    } else {
        LineIcon(icon, if (selected) brand.primary else brand.muted)
    }
}

/** The website's header: the CampBuddy wordmark, the guide and a refresh. */
@Composable
private fun TopBar(onGuide: () -> Unit, onRefresh: () -> Unit) {
    val brand = LocalBrand.current
    Row(
        Modifier.fillMaxWidth().padding(horizontal = 16.dp, vertical = 8.dp),
        horizontalArrangement = Arrangement.SpaceBetween,
        verticalAlignment = Alignment.CenterVertically,
    ) {
        Image(painterResource(Res.drawable.wordmark), contentDescription = "CampBuddy", modifier = Modifier.height(40.dp))
        Row(horizontalArrangement = Arrangement.spacedBy(8.dp)) {
            RoundTool("?", "Guide", onGuide)
            RoundTool("↻", "Refresh", onRefresh)
        }
    }
    Box(Modifier.fillMaxWidth().height(1.dp).background(brand.line))
}

@Composable
private fun RoundTool(glyph: String, label: String, onClick: () -> Unit) {
    val brand = LocalBrand.current
    Box(
        Modifier.size(44.dp).background(brand.peach, CircleShape).clickable(role = Role.Button, onClickLabel = label, onClick = onClick),
        contentAlignment = Alignment.Center,
    ) { Text(glyph, style = MaterialTheme.typography.titleMedium.copy(color = brand.ink)) }
}
