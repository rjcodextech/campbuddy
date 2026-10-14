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
import androidx.compose.foundation.layout.fillMaxHeight
import androidx.compose.foundation.layout.heightIn
import androidx.compose.foundation.layout.width
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
import androidx.compose.ui.text.style.TextDecoration
import androidx.compose.ui.draw.clip
import androidx.compose.material3.NavigationBar
import androidx.compose.material3.NavigationBarItem
import androidx.compose.material3.NavigationBarItemDefaults
import androidx.compose.material3.Text
import androidx.compose.runtime.Composable
import androidx.compose.runtime.LaunchedEffect
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
fun EventScreen(slug: String, onBack: () -> Unit, startInGuide: Boolean = false, startTab: String? = null) {
    val env = LocalEnv.current
    val words = LocalWords.current
    val brand = LocalBrand.current
    val eventLoader = rememberLoader("event:$slug") { env.repository.event(slug) }
    val sessionsLoader = rememberLoader("sessions:$slug") { env.repository.sessions(slug) }
    val event by eventLoader.state
    val sessions by sessionsLoader.state

    // Once notifications are on, tell the server this phone's push token for this event (so organizers can reach it).
    LaunchedEffect(slug, env.user.notifications.enabled) {
        if (!env.user.notifications.enabled) return@LaunchedEffect
        val token = runCatching { env.deps.push.token() }.getOrNull() ?: return@LaunchedEffect
        runCatching { env.api.registerPush(slug, token, env.user.deviceId()) }
    }
    val tabs = words.config.enabledTabs()
    var selected by rememberSaveable(slug) { mutableStateOf(startTab?.takeIf { t -> tabs.any { it.key == t } } ?: tabs.firstOrNull()?.key ?: "home") }
    var guide by rememberSaveable(slug) { mutableStateOf(startInGuide) }

    if (guide) {
        GuideScreen(slug) { guide = false }
        return
    }
    PlatformBackHandler(enabled = true, onBack = onBack)

    Column(Modifier.fillMaxSize().background(brand.background).statusBarsPadding()) {
        TopBar(onGuide = { guide = true }, onRefresh = { eventLoader.reload(); sessionsLoader.reload() }, onBack = if (env.deps.platform == "ios") onBack else null)
        if (selected == "home" || selected == "my-day") RunningLate(slug)
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
                        "camp-card" -> CampCardTab(e.value.name, e.value.logo)
                        "memories" -> MemoriesTab(slug)
                        else -> Unit
                    }
                }
            }
        }

        BottomBar(tabs, selected) { selected = it }
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

    LazyColumn(Modifier.fillMaxSize(), contentPadding = PaddingValues(16.dp)) {
        if (offline) item { OfflineNote() }

        val sections = words.config.enabledSections().filter { visible(it, phase, profile.firstWordCamp, saved.isNotEmpty()) }
        items(sections.size) { index ->
            val section = sections[index]
            when (section.type) {
                "hero" -> HeroCard(event, phase, zone, now, sessions)
                "starting_soon" -> StartingSoon(sessions, saved.map { it.sessionId }.toSet(), now, zone)
                "happening_now" -> HappeningNow(section, phase, sessions, now, zone, event, onTab)
                "up_next" -> UpNext(section, phase, sessions, now, zone, onSchedule = { onTab("my-day") })
                "start_here" -> StartHere(section.title?.let(words::invoke) ?: "New to WordCamp? Start here", onGuide)
                "suggested_action" -> SuggestedAction(section.title?.let(words::invoke) ?: "One thing to try", event.slug, saved.size, onTab)
                "people_cta" -> PeoplePrompt { onTab("explore") }
                "progress" -> Progress(section, event.slug, saved.size)
                else -> Unit // blocks the app does not draw yet are skipped, never an error
            }
        }
    }
}

/** A section's heading line: the title, and a small link on the right. */
@Composable
private fun SectionHead(title: String, link: String? = null, onLink: () -> Unit = {}, desc: String? = null) {
    val brand = LocalBrand.current
    Column(Modifier.fillMaxWidth().padding(top = 20.dp, bottom = 10.dp)) {
        Row(Modifier.fillMaxWidth(), horizontalArrangement = Arrangement.SpaceBetween, verticalAlignment = Alignment.CenterVertically) {
            Text(title, style = MaterialTheme.typography.headlineSmall)
            if (link != null) Text(link, style = MaterialTheme.typography.bodySmall.copy(color = brand.primary, fontWeight = FontWeight.Bold), modifier = Modifier.clickable(role = Role.Button, onClick = onLink).padding(vertical = 8.dp))
        }
        desc?.let { Text(it, style = MaterialTheme.typography.bodySmall) }
    }
}

/** The "now" look: peach with a brand-coloured border. */
@Composable
private fun NowCard(content: @Composable () -> Unit) {
    val brand = LocalBrand.current
    val shape = RoundedCornerShape(brand.radius.dp)
    Column(Modifier.fillMaxWidth().background(brand.peach, shape).border(1.dp, brand.primary, shape).padding(16.dp), verticalArrangement = Arrangement.spacedBy(8.dp)) { content() }
}

/** "→ a line of guidance" the way the website writes it. */
@Composable
private fun Guidance(text: String) {
    val brand = LocalBrand.current
    Row(horizontalArrangement = Arrangement.spacedBy(8.dp)) {
        Text("→", style = MaterialTheme.typography.bodyMedium.copy(color = brand.primary, fontWeight = FontWeight.Bold))
        Text(text, style = MaterialTheme.typography.bodyMedium)
    }
}

@Composable
private fun LinkBullet(text: String, onClick: () -> Unit) {
    val brand = LocalBrand.current
    Row(Modifier.padding(start = 4.dp, top = 6.dp), horizontalArrangement = Arrangement.spacedBy(10.dp)) {
        Text("•", style = MaterialTheme.typography.bodyMedium)
        Text(text, style = MaterialTheme.typography.bodyMedium.copy(color = brand.primary, fontWeight = FontWeight.Bold, textDecoration = TextDecoration.Underline), modifier = Modifier.clickable(role = Role.Button, onClick = onClick))
    }
}

@Composable
private fun StartHere(title: String, onGuide: () -> Unit) {
    val brand = LocalBrand.current
    val shape = RoundedCornerShape(brand.radius.dp)
    Row(
        Modifier.padding(top = 20.dp).fillMaxWidth().border(1.dp, brand.accent, shape).clickable(role = Role.Button, onClick = onGuide).padding(start = 16.dp, top = 16.dp, bottom = 16.dp, end = 16.dp),
        horizontalArrangement = Arrangement.spacedBy(16.dp), verticalAlignment = Alignment.CenterVertically,
    ) {
        Box(Modifier.size(64.dp)) {
            Box(Modifier.size(64.dp).clip(RoundedCornerShape(16.dp)).background(Color(0xFFFBEFD9)), contentAlignment = Alignment.Center) {
                Image(painterResource(Res.drawable.app_icon), contentDescription = null, modifier = Modifier.size(52.dp))
            }
            Text(
                "101", style = MaterialTheme.typography.labelMedium.copy(color = Color.White, fontSize = 11.sp),
                modifier = Modifier.align(Alignment.BottomEnd).background(brand.primary, RoundedCornerShape(50)).padding(horizontal = 6.dp, vertical = 1.dp),
            )
        }
        Column(Modifier.weight(1f), verticalArrangement = Arrangement.spacedBy(4.dp)) {
            Text(title, style = MaterialTheme.typography.titleMedium.copy(fontWeight = FontWeight.ExtraBold))
            Text("How the day runs, the words you'll hear, and how to meet people. A 5-minute read.", style = MaterialTheme.typography.bodyMedium.copy(color = brand.muted))
            Text("Read the guide →", style = MaterialTheme.typography.bodyMedium.copy(color = brand.primary, fontWeight = FontWeight.Bold), modifier = Modifier.padding(top = 4.dp))
        }
    }
}

@Composable
private fun SuggestedAction(title: String, slug: String, savedCount: Int, onTab: (String) -> Unit) {
    val env = LocalEnv.current
    val brand = LocalBrand.current
    val loader = rememberLoader("quests:$slug") { env.repository.quests(slug) }
    val quests = (loader.state.value as? LoadState.Ready)?.value.orEmpty()
    val done = env.user.completedQuests(slug)
    val next = quests.firstOrNull { it.id !in done }

    Column {
        SectionHead(title, "All quests →", { onTab("quest") })
        Card(onClick = { onTab(if (next != null) "quest" else "my-day") }) {
            Row(horizontalArrangement = Arrangement.spacedBy(12.dp), verticalAlignment = Alignment.CenterVertically) {
                Box(Modifier.size(22.dp).border(2.dp, brand.primary, CircleShape))
                Text(
                    next?.title ?: if (savedCount < 3) "Save 3 sessions you'd enjoy" else "You've done them all. Nice.",
                    style = MaterialTheme.typography.titleMedium, modifier = Modifier.weight(1f),
                )
            }
        }
    }
}

@Composable
private fun PeoplePrompt(onJoin: () -> Unit) {
    val brand = LocalBrand.current
    Card(Modifier.padding(top = 20.dp)) {
        Column(verticalArrangement = Arrangement.spacedBy(4.dp)) {
            Box(Modifier.size(width = 64.dp, height = 48.dp), contentAlignment = Alignment.CenterStart) { LineIcon("users", brand.primary, size = 40.dp) }
            Text("Find people who match your interests", style = MaterialTheme.typography.titleMedium, modifier = Modifier.padding(top = 4.dp))
            Text(
                "Pick a few interests and see who here shares them. Names and photos help you find each other in the crowd. Leave any time.",
                style = MaterialTheme.typography.bodyMedium.copy(color = brand.muted), modifier = Modifier.padding(bottom = 8.dp),
            )
            PrimaryButton("Join attendee discovery", onJoin, Modifier.fillMaxWidth())
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
private fun HeroCard(event: EventDetail, phase: Phase, zone: TimeZone, now: Instant, sessions: List<Session>) {
    val brand = LocalBrand.current
    val shape = RoundedCornerShape(brand.radius.dp)
    Card {
        Row(horizontalArrangement = Arrangement.spacedBy(12.dp), verticalAlignment = Alignment.CenterVertically) {
            Box(
                Modifier.size(104.dp).background(brand.background, shape).border(1.dp, brand.line, shape),
                contentAlignment = Alignment.Center,
            ) { RemoteImage(event.logo, Modifier.size(96.dp)) { Image(painterResource(Res.drawable.app_icon), contentDescription = null, modifier = Modifier.size(72.dp)) } }
            Column(Modifier.weight(1f)) {
                Text((event.shortName ?: "WordCamp").uppercase(), style = MaterialTheme.typography.labelMedium.copy(color = brand.primary, letterSpacing = 0.72.sp), modifier = Modifier.padding(bottom = 4.dp))
                Text(event.name, style = MaterialTheme.typography.titleLarge.copy(fontSize = 20.sp, lineHeight = 23.sp, fontWeight = FontWeight.ExtraBold, letterSpacing = (-0.4).sp))
                datesText(event.startsOn, event.endsOn)?.let { Text(it, style = MaterialTheme.typography.bodyMedium.copy(color = brand.muted), modifier = Modifier.padding(top = 4.dp)) }
                Box(Modifier.padding(top = 8.dp)) { StatusPill(statusPillText(event, phase, zone, now, sessions), live = phase == Phase.During) }
            }
        }
    }
}

@Composable
private fun StatusPill(text: String, live: Boolean) {
    val brand = LocalBrand.current
    val shape = RoundedCornerShape(50)
    Row(
        Modifier.background(brand.background, shape).border(1.dp, brand.line, shape).padding(horizontal = 8.dp, vertical = 2.dp),
        horizontalArrangement = Arrangement.spacedBy(6.dp),
        verticalAlignment = Alignment.CenterVertically,
    ) {
        if (live) Box(Modifier.size(8.dp).background(brand.success, CircleShape))
        Text(text, style = MaterialTheme.typography.labelMedium.copy(color = brand.ink))
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

    Card(Modifier.padding(top = 20.dp)) {
        Column(verticalArrangement = Arrangement.spacedBy(2.dp)) {
            Text("Starting soon", style = MaterialTheme.typography.labelMedium)
            Text(soon.title, style = MaterialTheme.typography.titleMedium)
            EventClock.startOf(soon)?.let { Text(EventClock.when_(it, now, zone) + (soon.trackNames.firstOrNull()?.let { t -> " · $t" } ?: ""), style = MaterialTheme.typography.bodyMedium) }
        }
    }
}

@Composable
private fun HappeningNow(section: SectionConfig, phase: Phase, sessions: List<Session>, now: Instant, zone: TimeZone, event: EventDetail, onTab: (String) -> Unit) {
    val words = LocalWords.current
    val brand = LocalBrand.current
    val env = LocalEnv.current
    val current = EventClock.current(sessions, now)
    val guide = rememberLoader("guide:${event.slug}") { env.repository.guide(event.slug) }.state.value as? LoadState.Ready

    SectionHead(section.title?.let(words::invoke) ?: "Happening now")
    if (phase != Phase.During || current.isEmpty()) {
        when {
            phase == Phase.After -> Card { Text("That is a wrap. Thanks for coming!", style = MaterialTheme.typography.titleMedium) }
            phase == Phase.Before -> NowCard {
                Text(statusText(event, phase, zone, now, sessions), style = MaterialTheme.typography.titleMedium)
                Guidance("A few minutes now makes the day much easier:")
                LinkBullet("Save 3 sessions you'd enjoy") { onTab("my-day") }
                LinkBullet("Tick off the \"get ready\" checklist") { onTab("quest") }
            }
            else -> Card { Text("Nothing on stage right now. Talk to someone nearby, or go see a sponsor booth.", style = MaterialTheme.typography.bodyMedium) }
        }
        return
    }

    val moment = current.firstNotNullOfOrNull { s -> guide?.value?.moments?.firstOrNull { m -> m.match.any { s.title.contains(it, ignoreCase = true) } } }
    NowCard {
        Text(
            "NOW",
            style = MaterialTheme.typography.labelMedium.copy(color = Color.White, letterSpacing = 0.72.sp),
            modifier = Modifier.background(brand.primary, RoundedCornerShape(50)).padding(horizontal = 12.dp, vertical = 3.dp),
        )
        current.forEachIndexed { i, session ->
            if (i > 0) Box(Modifier.fillMaxWidth().height(1.dp).background(brand.primary.copy(alpha = 0.25f)))
            Column {
                Text(session.title, style = MaterialTheme.typography.titleMedium)
                val end = EventClock.endOf(session)
                val meta = listOfNotNull(session.trackNames.firstOrNull(), end?.let { "until ${EventClock.time(it, zone)}" }).joinToString(" · ")
                if (meta.isNotEmpty()) Text(meta, style = MaterialTheme.typography.bodySmall)
            }
        }
        moment?.takeIf { it.now.isNotBlank() }?.let { Guidance(it.now) }
    }
}

@Composable
private fun UpNext(section: SectionConfig, phase: Phase, sessions: List<Session>, now: Instant, zone: TimeZone, onSchedule: () -> Unit) {
    val words = LocalWords.current
    val brand = LocalBrand.current
    if (phase == Phase.After) return
    val next = EventClock.upNext(sessions, now)
    val start = next?.let { EventClock.startOf(it) }

    if (next == null || start == null) {
        // Sessions announced but no times yet: say so, and point at the list (the website does the same).
        if (sessions.isEmpty() || sessions.any { EventClock.startOf(it) != null }) return
        SectionHead(section.title?.let(words::invoke) ?: "Up next", "Full schedule →", onSchedule)
        Card {
            Column(verticalArrangement = Arrangement.spacedBy(8.dp)) {
                Text("${sessions.size} sessions announced, times still to come", style = MaterialTheme.typography.titleMedium)
                Row(horizontalArrangement = Arrangement.spacedBy(8.dp)) {
                    Text("→", style = MaterialTheme.typography.bodyMedium.copy(color = brand.primary, fontWeight = FontWeight.Bold))
                    Text("Browse them now and star the ones you'd enjoy: ", style = MaterialTheme.typography.bodyMedium, modifier = Modifier.weight(1f, fill = false))
                }
                Text("see the sessions.", style = MaterialTheme.typography.bodyMedium.copy(color = brand.primary, fontWeight = FontWeight.Bold), modifier = Modifier.clickable(role = Role.Button, onClick = onSchedule))
            }
        }
        return
    }
    val alternatives = EventClock.alternatives(sessions, next).size

    SectionHead(section.title?.let(words::invoke) ?: "Up next", "Full schedule →", onSchedule)
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

/** Quests done out of all of this event's quests, and saved sessions (the line the website shows). */
@Composable
private fun Progress(section: SectionConfig, slug: String, savedCount: Int) {
    val env = LocalEnv.current
    val words = LocalWords.current
    val brand = LocalBrand.current
    val loader = rememberLoader("quests:$slug") { env.repository.quests(slug) }
    val ready = loader.state.value as? LoadState.Ready ?: return
    val total = ready.value.size
    if (total == 0) return
    val done = env.user.completedQuests(slug).count { id -> ready.value.any { it.id == id } }

    Column {
        SectionHead(section.title?.let(words::invoke) ?: "Your progress", desc = "$done/$total quests · $savedCount saved sessions")
        Box(Modifier.fillMaxWidth().height(8.dp).background(brand.line, RoundedCornerShape(50))) {
            Box(Modifier.fillMaxWidth(done.toFloat() / total).height(8.dp).background(brand.accent, RoundedCornerShape(50)))
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
        Image(painterResource(art), contentDescription = null, modifier = Modifier.size(20.dp))
    } else {
        LineIcon(icon, if (selected) brand.primary else brand.muted)
    }
}

/** The website's header: the CampBuddy wordmark, the guide and a refresh (32dp peach circles). */
@Composable
private fun TopBar(onGuide: () -> Unit, onRefresh: () -> Unit, onBack: (() -> Unit)? = null) {
    val brand = LocalBrand.current
    Row(
        Modifier.fillMaxWidth().padding(horizontal = 16.dp, vertical = 8.dp),
        horizontalArrangement = Arrangement.SpaceBetween,
        verticalAlignment = Alignment.CenterVertically,
    ) {
        Row(verticalAlignment = Alignment.CenterVertically) {
            // iOS has no back button of its own, so the events list is one tap away.
            onBack?.let { RoundTool("chevron-left", "All WordCamps", it) }
            Image(painterResource(Res.drawable.wordmark), contentDescription = "CampBuddy", modifier = Modifier.height(32.dp))
        }
        Row(horizontalArrangement = Arrangement.spacedBy(8.dp)) {
            RoundTool("help-circle", "Guide", onGuide)
            RoundTool("refresh", "Refresh", onRefresh)
        }
    }
    Box(Modifier.fillMaxWidth().height(1.dp).background(brand.line))
}

@Composable
private fun RoundTool(icon: String, label: String, onClick: () -> Unit) {
    val brand = LocalBrand.current
    // 32dp to look like the website's, with the touch area kept to 44dp around it.
    Box(Modifier.size(44.dp).clickable(role = Role.Button, onClickLabel = label, onClick = onClick), contentAlignment = Alignment.Center) {
        Box(Modifier.size(32.dp).background(brand.peach, CircleShape), contentAlignment = Alignment.Center) { LineIcon(icon, brand.ink, size = 16.dp) }
    }
}

/** The website's "Running late" notice: shown while organizers have moved the schedule; the times already include it. */
@Composable
private fun RunningLate(slug: String) {
    val env = LocalEnv.current
    val brand = LocalBrand.current
    val state = rememberLoader("delay:$slug") { env.repository.delays(slug) }.state.value as? LoadState.Ready ?: return
    val active = state.value.filter { it.minutes != 0 && !it.text.isNullOrBlank() }
    if (active.isEmpty()) return
    val shape = RoundedCornerShape(brand.radius.dp)

    Row(
        Modifier.fillMaxWidth().padding(start = 16.dp, end = 16.dp, top = 12.dp)
            .background(brand.danger.copy(alpha = 0.10f), shape).border(1.dp, brand.danger.copy(alpha = 0.35f), shape)
            .padding(start = 0.dp),
        verticalAlignment = Alignment.Top,
    ) {
        Box(Modifier.width(5.dp).heightIn(min = 96.dp).background(brand.primary, RoundedCornerShape(topStart = brand.radius.dp, bottomStart = brand.radius.dp)))
        Row(Modifier.padding(14.dp), horizontalArrangement = Arrangement.spacedBy(14.dp)) {
            LineIcon("clock", brand.danger, size = 30.dp)
            Column(verticalArrangement = Arrangement.spacedBy(4.dp)) {
                Text("Running late", style = MaterialTheme.typography.titleLarge)
                active.forEach { Text(it.text.orEmpty(), style = MaterialTheme.typography.bodyLarge) }
                Text("The times in CampBuddy already include this.", style = MaterialTheme.typography.bodyMedium.copy(color = brand.muted))
            }
        }
    }
}

/** The website's bottom bar: 64 high, a thin line on top, each tab an icon in a 48x26 pill (peach when open) over a small label. */
@Composable
private fun BottomBar(tabs: List<club.campbuddy.shared.model.TabConfig>, selected: String, onSelect: (String) -> Unit) {
    val brand = LocalBrand.current
    val words = LocalWords.current
    Column(Modifier.fillMaxWidth().background(brand.surface).navigationBarsPadding()) {
        Box(Modifier.fillMaxWidth().height(1.dp).background(brand.line))
        Row(Modifier.fillMaxWidth().height(64.dp)) {
            tabs.forEach { tab ->
                val on = tab.key == selected
                Column(
                    Modifier.weight(1f).fillMaxHeight().clickable(role = Role.Tab, onClick = { onSelect(tab.key) }),
                    horizontalAlignment = Alignment.CenterHorizontally,
                    verticalArrangement = Arrangement.Center,
                ) {
                    Box(
                        Modifier.size(width = 48.dp, height = 26.dp).background(if (on) brand.peach else Color.Transparent, RoundedCornerShape(50)),
                        contentAlignment = Alignment.Center,
                    ) { TabIcon(tab.key, tab.icon, on) }
                    Text(
                        words(tab.title),
                        style = MaterialTheme.typography.labelMedium.copy(fontSize = 10.4.sp, lineHeight = 11.5.sp, color = if (on) brand.primary else brand.muted),
                        maxLines = 1, softWrap = false, modifier = Modifier.padding(top = 3.dp),
                    )
                }
            }
        }
    }
}
