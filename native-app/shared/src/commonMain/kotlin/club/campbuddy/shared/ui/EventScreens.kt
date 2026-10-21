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
import campbuddy_native.shared.generated.resources.pattern_event
import campbuddy_native.shared.generated.resources.wordmark
import kotlinx.datetime.LocalDate
import kotlinx.datetime.daysUntil
import androidx.compose.ui.text.style.TextDecoration
import androidx.compose.ui.draw.clip
import androidx.compose.ui.graphics.Brush
import androidx.compose.ui.text.SpanStyle
import androidx.compose.ui.text.buildAnnotatedString
import androidx.compose.ui.text.withStyle
import club.campbuddy.shared.domain.Moments
import campbuddy_native.shared.generated.resources.art_people
import campbuddy_native.shared.generated.resources.pattern_people
import androidx.compose.runtime.remember
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
fun EventScreen(
    slug: String,
    onBack: () -> Unit,
    startInGuide: Boolean = false,
    startTab: String? = null,
    startSub: String? = null,
    onStaff: (() -> Unit)? = null,
) {
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
        GuideScreen(slug, onBack = { guide = false }, onTab = { guide = false; selected = it })
        return
    }
    PlatformBackHandler(enabled = true, onBack = onBack)

    Column(Modifier.fillMaxSize().background(brand.background).statusBarsPadding()) {
        TopBar(onGuide = { guide = true }, onSwitch = onBack, onStaff = onStaff)
        val banner = words.config.banner
        if (banner != null && selected == "home" && !env.user.isBannerDismissed(banner.dismissKey)) {
            Box(Modifier.padding(horizontal = 16.dp, vertical = 4.dp)) {
                BannerCard(
                    banner = banner,
                    language = words.language,
                    onAction = {
                        val action = banner.action
                        when {
                            action?.type == "event" && action.slug != null && action.slug != slug -> onBack()
                            action?.type == "url" && action.url != null -> env.deps.openUrl(action.url)
                            action?.type == "tab" && action.tab != null -> selected = action.tab
                        }
                    },
                    onDismiss = { env.user.dismissBanner(banner.dismissKey) },
                )
            }
        }
        // The organizers' announcement of the last half hour, on every tab (it tells everyone, not just one screen).
        MarkSeen(slug)
        LiveNoticeBanner(slug) { tab -> if (tabs.any { it.key == tab }) selected = tab }
        if (selected == "home" || selected == "my-day") RunningLate(slug)
        Box(Modifier.weight(1f)) {
            when (val e = event) {
                LoadState.Loading -> Loading()
                is LoadState.Failed -> ErrorState(e.message) { eventLoader.reload(); sessionsLoader.reload() }
                is LoadState.Ready -> {
                    val list = (sessions as? LoadState.Ready)?.value.orEmpty()
                    val offline = e.fromCache || (sessions as? LoadState.Ready)?.fromCache == true
                    var askedNow by rememberSaveable(slug) { mutableStateOf(false) }
                    val lastDay = runCatching { kotlinx.datetime.LocalDate.parse(e.value.lastDay.orEmpty()) }.getOrNull()
                    val zone = EventClock.zone(e.value.timezone)
                    if (!askedNow && lastDay != null && env.user.dataChoice(slug) == null && env.user.hasEventData(slug) &&
                        EventClock.phase(list, e.value.firstDay, e.value.lastDay, zone, kotlinx.datetime.Clock.System.now()) == Phase.After
                    ) AfterEventChoice(slug, e.value.name, lastDay, zone) { askedNow = true }
                    when (selected) {
                        "home" -> HomeTab(e.value, list, offline, onBack, onTab = { selected = it }, onGuide = { guide = true })
                        "my-day" -> MyDayTab(e.value, list, offline)
                        "quest" -> QuestTab(slug) { selected = it }
                        "contribute" -> ContributeTab(slug)
                        "explore" -> ExploreTab(slug, startSub)
                        "camp-card" -> CampCardTab(slug, e.value.name, e.value.logo, e.value.mark)
                        "memories" -> MemoriesTab(slug) { selected = tabs.firstOrNull()?.key ?: "home" }
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
    val startHereShown = startHereVisible(env.deps.store, event.slug, profile.firstWordCamp)

    LazyColumn(Modifier.fillMaxSize(), contentPadding = PaddingValues(16.dp)) {
        if (offline) item { OfflineNote() }
        item { LiveAskCard(event.slug, event.firstDay, event.lastDay, zone) }

        val sections = words.config.enabledSections().filter { visible(it, phase, profile.firstWordCamp, saved.isNotEmpty()) }
        items(sections.size) { index ->
            val section = sections[index]
            when (section.type) {
                "hero" -> HeroCard(event, phase, zone, now, sessions)
                "memories" -> MemoriesCard(event.slug, withHeading = true)
                "starting_soon" -> StartingSoon(sessions, saved.map { it.sessionId }.toSet(), now, zone)
                "happening_now" -> HappeningNow(section, phase, sessions, now, zone, event, startHereShown, onTab, onGuide)
                "up_next" -> UpNext(section, sessions, now, zone, event.slug, saved.map { it.sessionId }.toSet(), onSchedule = { onTab("my-day") })
                "start_here" -> StartHere(section.title?.let(words::invoke) ?: "New to WordCamp? Start here", event.slug, onGuide)
                "suggested_action" -> SuggestedAction(section.title?.let(words::invoke) ?: "One thing to try", event.slug, saved.size, onTab)
                "people_cta" -> PeoplePrompt(event.slug) { onTab("explore") }
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
private fun StartHere(title: String, slug: String, onGuide: () -> Unit) {
    val env = LocalEnv.current
    val brand = LocalBrand.current
    val shape = RoundedCornerShape(brand.radius.dp)
    var hidden by remember(slug) { mutableStateOf(!startHereVisible(env.deps.store, slug, env.user.profile.firstWordCamp)) }
    if (hidden) return

    Box(Modifier.padding(top = 20.dp).fillMaxWidth()) {
        Row(
            Modifier.fillMaxWidth().background(Brush.linearGradient(listOf(brand.peach, brand.surface)), shape).border(1.dp, brand.accent, shape)
                .clickable(role = Role.Button, onClick = onGuide).padding(start = 16.dp, top = 16.dp, bottom = 16.dp, end = 40.dp),
            horizontalArrangement = Arrangement.spacedBy(16.dp), verticalAlignment = Alignment.CenterVertically,
        ) {
            Box(Modifier.size(64.dp)) {
                Box(Modifier.size(64.dp).clip(RoundedCornerShape(16.dp)).background(Color(0xFFFBEFD9)), contentAlignment = Alignment.Center) {
                    Image(painterResource(Res.drawable.app_icon), contentDescription = null, modifier = Modifier.size(52.dp))
                }
                Text(
                    "101", style = MaterialTheme.typography.labelMedium.copy(color = Color.White, fontSize = 11.sp),
                    modifier = Modifier.align(Alignment.BottomEnd).background(brand.primary, RoundedCornerShape(50)).border(2.dp, brand.surface, RoundedCornerShape(50)).padding(horizontal = 6.dp, vertical = 1.dp),
                )
            }
            Column(Modifier.weight(1f), verticalArrangement = Arrangement.spacedBy(4.dp)) {
                Text(title, style = MaterialTheme.typography.titleMedium.copy(fontWeight = FontWeight.ExtraBold))
                Text("How the day runs, the words you'll hear, and how to meet people. A 5-minute read.", style = MaterialTheme.typography.bodyMedium.copy(color = brand.muted))
                Text("Read the guide →", style = MaterialTheme.typography.bodyMedium.copy(color = brand.primary, fontWeight = FontWeight.Bold), modifier = Modifier.padding(top = 4.dp))
            }
        }
        Box(
            Modifier.align(Alignment.TopEnd).size(40.dp).clickable(role = Role.Button, onClickLabel = "Hide this guide card") { env.deps.store.put("ui:startHere:$slug", "dismissed"); hidden = true },
            contentAlignment = Alignment.Center,
        ) { LineIcon("x", brand.ink, size = 20.dp) }
    }
}

@Composable
private fun SuggestedAction(title: String, slug: String, savedCount: Int, onTab: (String) -> Unit) {
    val env = LocalEnv.current
    val brand = LocalBrand.current
    val loader = rememberLoader("quests:$slug") { env.repository.quests(slug) }
    val quests = (loader.state.value as? LoadState.Ready)?.value.orEmpty()
    val done = env.user.completedQuests(slug)
    val incomplete = quests.filter { it.id !in done }
    val pool = if (incomplete.isNotEmpty()) incomplete.map { it.title to it.description.orEmpty() } else listOf(
        "Visit the sponsor area" to "Sponsors pay for a big part of WordCamp. Say hello and ask what they make.",
        "Introduce yourself to someone new" to "Ask what brought them to this WordCamp.",
        "Add a link to your Camp Card" to "So people you meet have a way to stay in touch.",
    )
    // One suggestion at a time, the same for a whole hour so it does not flicker.
    val pick = pool[((Clock.System.now().epochSeconds / 3600) % pool.size).toInt()]

    Column {
        SectionHead(title, "All quests →", { onTab("quest") })
        Card(onClick = { onTab("quest") }) {
            Row(horizontalArrangement = Arrangement.spacedBy(12.dp), verticalAlignment = Alignment.Top) {
                Box(Modifier.size(44.dp).background(brand.peach, RoundedCornerShape(brand.radius.dp)), contentAlignment = Alignment.Center) { LineIcon("lightbulb", brand.primary, size = 22.dp) }
                Column(Modifier.weight(1f)) {
                    Text(pick.first, style = MaterialTheme.typography.titleMedium)
                    if (pick.second.isNotBlank()) Text(pick.second, style = MaterialTheme.typography.bodyMedium.copy(color = brand.muted), modifier = Modifier.padding(top = 4.dp))
                }
            }
        }
    }
}
@Composable
private fun PeoplePrompt(slug: String, onJoin: () -> Unit) {
    val env = LocalEnv.current
    val brand = LocalBrand.current
    val joined = env.user.discovery(slug) != null
    Column {
        SectionHead("Meet people")
        Card(Modifier.clip(RoundedCornerShape(brand.radius.dp)).patternBackground(Res.drawable.pattern_people, brand.surface)) {
            Column(verticalArrangement = Arrangement.spacedBy(4.dp)) {
                Image(painterResource(Res.drawable.art_people), contentDescription = null, modifier = Modifier.size(width = 64.dp, height = 48.dp))
                Text(if (joined) "You are in attendee discovery" else "Find people who match your interests", style = MaterialTheme.typography.titleMedium, modifier = Modifier.padding(top = 4.dp))
                Text(
                    if (joined) "See who shares your interests, and wave at the people you would like to meet."
                    else "Pick a few interests and see who here shares them. Names and photos help you find each other in the crowd. Leave any time.",
                    style = MaterialTheme.typography.bodyMedium.copy(color = brand.muted), modifier = Modifier.padding(bottom = 8.dp),
                )
                PrimaryButton(if (joined) "See my matches" else "Join attendee discovery", onJoin, Modifier.fillMaxWidth())
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
private fun HeroCard(event: EventDetail, phase: Phase, zone: TimeZone, now: Instant, sessions: List<Session>) {
    val brand = LocalBrand.current
    val shape = RoundedCornerShape(brand.radius.dp)
    Card(Modifier.clip(shape).patternBackground(Res.drawable.pattern_event, brand.surface)) {
        Row(horizontalArrangement = Arrangement.spacedBy(12.dp), verticalAlignment = Alignment.CenterVertically) {
            Box(
                Modifier.size(104.dp).background(brand.background, shape).border(1.dp, brand.line, shape),
                contentAlignment = Alignment.Center,
            ) { RemoteImage(event.logo, Modifier.size(96.dp)) { Image(painterResource(Res.drawable.app_icon), contentDescription = null, modifier = Modifier.size(72.dp)) } }
            Column(Modifier.weight(1f)) {
                Text((event.shortName ?: "WordCamp").uppercase(), style = MaterialTheme.typography.labelMedium.copy(color = brand.primary, letterSpacing = 0.72.sp), modifier = Modifier.padding(bottom = 4.dp))
                Text(event.name, style = MaterialTheme.typography.titleLarge.copy(fontSize = 20.sp, lineHeight = 23.sp, fontWeight = FontWeight.ExtraBold, letterSpacing = (-0.4).sp))
                heroDates(event.startsOn, event.endsOn)?.let { Text(it, style = MaterialTheme.typography.bodyMedium.copy(color = brand.muted), modifier = Modifier.padding(top = 4.dp)) }
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

@Composable
private fun StartingSoon(sessions: List<Session>, savedIds: Set<Long>, now: Instant, zone: TimeZone) {
    val brand = LocalBrand.current
    val soon = sessions.firstOrNull { s ->
        val start = EventClock.startOf(s) ?: return@firstOrNull false
        s.id in savedIds && start > now && (start.epochSeconds - now.epochSeconds) <= 15 * 60
    } ?: return
    val start = EventClock.startOf(soon) ?: return
    val minutes = maxOf(1, ((start.epochSeconds - now.epochSeconds) / 60).toInt())
    val shape = RoundedCornerShape(brand.radius.dp)

    Text(
        buildAnnotatedString {
            withStyle(SpanStyle(fontWeight = FontWeight.Bold)) { append(soon.title) }
            append(" starts in $minutes min")
            soon.trackNames.firstOrNull()?.let { append(" in $it") }
            append(". You saved this one.")
        },
        style = MaterialTheme.typography.bodyMedium,
        modifier = Modifier.padding(top = 20.dp).fillMaxWidth().background(brand.peach, shape).border(1.dp, brand.accent, shape).padding(12.dp),
    )
}
@Composable
private fun HappeningNow(section: SectionConfig, phase: Phase, sessions: List<Session>, now: Instant, zone: TimeZone, event: EventDetail, startHereShown: Boolean, onTab: (String) -> Unit, onGuide: () -> Unit) {
    val words = LocalWords.current
    val brand = LocalBrand.current
    val env = LocalEnv.current
    val timed = sessions.filter { EventClock.startOf(it) != null }
    // A session with no length counts as "now" for its first 30 minutes, rather than never.
    val current = timed.filter { s ->
        val start = EventClock.startOf(s) ?: return@filter false
        val end = EventClock.endOf(s)?.takeIf { it > start } ?: Instant.fromEpochSeconds(start.epochSeconds + 30 * 60)
        start <= now && now < end
    }
    val guide = (rememberLoader("guide:${event.slug}") { env.repository.guide(event.slug) }.state.value as? LoadState.Ready)?.value
    val moments = guide?.moments.orEmpty()

    SectionHead(section.title?.let(words::invoke) ?: "Happening now")
    when {
        phase == Phase.Before -> NowCard {
            Text(statusText(event, phase, zone, now, sessions), style = MaterialTheme.typography.titleMedium)
            Guidance("A few minutes now makes the day much easier:")
            if (!startHereShown) LinkBullet("Read the 5-minute first-timer guide", onGuide)
            LinkBullet("Save 3 sessions you'd enjoy") { onTab("my-day") }
            LinkBullet("Tick off the \"get ready\" checklist") { onTab("quest") }
        }
        current.isEmpty() && phase == Phase.After -> NowCard {
            Text("That's a wrap. Thanks for coming!", style = MaterialTheme.typography.titleMedium)
            Guidance("Connect with the people you met while it's fresh, look up your local WordPress meetup, and watch for talk recordings on WordPress.tv.")
        }
        current.isEmpty() -> {
            val next = timed.firstOrNull { (EventClock.startOf(it) ?: now) > now }
            NowCard {
                Text("Nothing's on stage right this minute.", style = MaterialTheme.typography.titleMedium)
                Guidance(
                    "Nothing on stage right now. Talk to someone nearby, or go see a sponsor booth." +
                        (next?.let { n -> " Next up: ${n.title} ${EventClock.when_(EventClock.startOf(n)!!, now, zone)}." } ?: ""),
                )
            }
        }
        else -> NowCard {
            Text(
                "NOW",
                style = MaterialTheme.typography.labelMedium.copy(color = Color.White, letterSpacing = 0.72.sp),
                modifier = Modifier.background(brand.primary, RoundedCornerShape(50)).padding(horizontal = 12.dp, vertical = 3.dp),
            )
            current.forEachIndexed { i, session ->
                if (i > 0) Box(Modifier.fillMaxWidth().height(1.dp).background(brand.primary.copy(alpha = 0.18f)))
                Column {
                    Text(session.title, style = MaterialTheme.typography.titleMedium)
                    val start = EventClock.startOf(session)!!
                    val end = EventClock.endOf(session)?.takeIf { it > start } ?: Instant.fromEpochSeconds(start.epochSeconds + 30 * 60)
                    val meta = listOfNotNull(session.trackNames.firstOrNull(), "until ${EventClock.time(end, zone)}").joinToString(" · ")
                    Text(meta, style = MaterialTheme.typography.bodySmall.copy(fontSize = 12.sp))
                }
            }
            val moment = current.firstNotNullOfOrNull { Moments.momentFor(it, moments) }
            Guidance(
                moment?.now?.takeIf { it.isNotBlank() }
                    ?: if (current.size > 1) "Pick the one that sounds most useful. If it isn't for you, it's fine to switch rooms."
                    else "If it has already started, slip in quietly. There's usually room at the back.",
            )
        }
    }
}
@Composable
private fun UpNext(section: SectionConfig, sessions: List<Session>, now: Instant, zone: TimeZone, slug: String, savedIds: Set<Long>, onSchedule: () -> Unit) {
    val words = LocalWords.current
    val brand = LocalBrand.current
    val env = LocalEnv.current
    val guide = (rememberLoader("guide:$slug") { env.repository.guide(slug) }.state.value as? LoadState.Ready)?.value
    val moments = guide?.moments.orEmpty()
    val profile = env.user.profile
    val timed = sessions.filter { EventClock.startOf(it) != null }.sortedBy { EventClock.startOf(it) }
    val upcoming = timed.filter { (EventClock.startOf(it) ?: now) > now }
    val untimed = sessions.count { EventClock.startOf(it) == null }

    SectionHead(section.title?.let(words::invoke) ?: "Up next", "Full schedule →", onSchedule)
    if (upcoming.isEmpty()) {
        Card {
            if (untimed > 0) {
                Column(verticalArrangement = Arrangement.spacedBy(4.dp)) {
                    Text("$untimed sessions announced, times still to come", style = MaterialTheme.typography.titleMedium)
                    Row(horizontalArrangement = Arrangement.spacedBy(8.dp)) {
                        Text("→", style = MaterialTheme.typography.bodyMedium.copy(color = brand.primary, fontWeight = FontWeight.Bold))
                        Text(
                            buildAnnotatedString {
                                append("Browse them now and star the ones you'd enjoy: ")
                                withStyle(SpanStyle(color = brand.primary, fontWeight = FontWeight.Bold)) { append("see the sessions") }
                                append(".")
                            },
                            style = MaterialTheme.typography.bodyMedium, modifier = Modifier.clickable(role = Role.Button, onClick = onSchedule),
                        )
                    }
                }
            } else {
                Text("That's everything on the schedule for now.", style = MaterialTheme.typography.bodyLarge)
            }
        }
        return
    }

    // Priority: your own saved sessions, then moments for everyone (the keynote, lunch), then what you said you are into;
    // each only among what starts soon, so lunch in an hour does not hide the talks starting in ten minutes.
    val soonest = EventClock.startOf(upcoming.first())!!.epochSeconds
    fun within(minutes: Int) = upcoming.filter { EventClock.startOf(it)!!.epochSeconds <= soonest + minutes * 60 }
    val interests = profile.interests.map { it.lowercase() }
    val firstTimer = profile.firstWordCamp == "yes"
    val savedPick = within(120).firstOrNull { it.id in savedIds }
    val keyPick = within(30).firstOrNull { Moments.momentFor(it, moments)?.key == true }
    val interesting = within(60)
        .map { it to (Moments.interestScore(it, interests) + if (firstTimer && Moments.isBeginnerFriendly(it)) 1 else 0) }
        .filter { it.second > 0 }
        .sortedWith(compareByDescending<Pair<Session, Int>> { it.second }.thenBy { EventClock.startOf(it.first)!!.epochSeconds })
        .firstOrNull()?.first
    val next = savedPick ?: keyPick ?: interesting ?: upcoming.first()
    val reason = when {
        savedPick != null -> "You've saved this one"
        keyPick == next -> "For everyone"
        interesting == next -> if (Moments.isBeginnerFriendly(next) && firstTimer) "Beginner-friendly" else "Matches what you said you're into"
        else -> "Next on the schedule"
    }
    val start = EventClock.startOf(next)!!
    val alternatives = upcoming.count { EventClock.startOf(it) == start && it.id != next.id }
    val guidance = Moments.momentFor(next, moments)?.now?.takeIf { it.isNotBlank() }
        ?: if (alternatives > 0) (if (alternatives == 1) "Another session runs" else "$alternatives other sessions run") + " at the same time. Compare them in My Day." else null

    Card {
        Column(verticalArrangement = Arrangement.spacedBy(2.dp)) {
            Text(next.title, style = MaterialTheme.typography.titleMedium)
            Text("$reason, starts ${EventClock.when_(start, now, zone)}" + (next.trackNames.firstOrNull()?.let { " · $it" } ?: "") + ".", style = MaterialTheme.typography.bodySmall)
            guidance?.let { Guidance(it) }
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
        Row(Modifier.fillMaxWidth().padding(top = 20.dp, bottom = 10.dp), horizontalArrangement = Arrangement.SpaceBetween, verticalAlignment = Alignment.Bottom) {
            Text(section.title?.let(words::invoke) ?: "Your progress", style = MaterialTheme.typography.headlineSmall)
            Text("$done/$total quests · $savedCount saved session${if (savedCount == 1) "" else "s"}", style = MaterialTheme.typography.bodySmall)
        }
        Box(Modifier.fillMaxWidth().height(8.dp).background(brand.line, RoundedCornerShape(50))) {
            if (done > 0) Box(Modifier.fillMaxWidth(done.toFloat() / total).height(8.dp).background(brand.accent, RoundedCornerShape(50)))
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

/** The website's header: the CampBuddy wordmark, staff hub button (if staff), the guide and a refresh. */
@Composable
private fun TopBar(onGuide: () -> Unit, onSwitch: () -> Unit, onStaff: (() -> Unit)? = null) {
    val brand = LocalBrand.current
    val env = LocalEnv.current
    val isStaff = onStaff != null
    val role = env.repository.organizerRole() ?: "manager"
    val isAdmin = role == "admin"

    Row(
        Modifier.fillMaxWidth().padding(horizontal = 16.dp, vertical = 8.dp),
        horizontalArrangement = Arrangement.SpaceBetween,
        verticalAlignment = Alignment.CenterVertically,
    ) {
        Row(verticalAlignment = Alignment.CenterVertically) {
            Image(painterResource(Res.drawable.wordmark), contentDescription = "CampBuddy", modifier = Modifier.height(32.dp))
        }
        Row(horizontalArrangement = Arrangement.spacedBy(8.dp), verticalAlignment = Alignment.CenterVertically) {
            if (isStaff) {
                Box(
                    modifier = Modifier
                        .clip(RoundedCornerShape(50))
                        .background(if (isAdmin) brand.primary.copy(alpha = 0.12f) else brand.accent.copy(alpha = 0.22f))
                        .border(1.dp, if (isAdmin) brand.primary.copy(alpha = 0.40f) else brand.accent.copy(alpha = 0.50f), RoundedCornerShape(50))
                        .clickable(role = Role.Button, onClickLabel = "Staff Hub", onClick = onStaff)
                        .padding(horizontal = 10.dp, vertical = 6.dp),
                    contentAlignment = Alignment.Center,
                ) {
                    Text(
                        text = if (isAdmin) "👑 Staff" else "👔 Staff",
                        style = MaterialTheme.typography.labelMedium.copy(
                            fontWeight = FontWeight.Bold,
                            color = if (isAdmin) brand.primary else brand.ink,
                        ),
                    )
                }
            }
            RoundTool("help-circle", "Guide", onGuide)
            RoundTool("refresh", "Switch WordCamp", onSwitch)
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
        // A gap below too: the page scrolls under the notice, and a card cut off right at its edge looked stuck to it.
        Modifier.fillMaxWidth().padding(start = 16.dp, end = 16.dp, top = 12.dp, bottom = 8.dp)
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

/** "21 Nov 2026 – 22 Nov 2026": the dates line on the website's event card and Memories page. */
internal fun heroDates(starts: String?, ends: String?): String? {
    val a = starts?.let { runCatching { LocalDate.parse(it) }.getOrNull() } ?: return null
    val b = ends?.let { runCatching { LocalDate.parse(it) }.getOrNull() }
    fun text(d: LocalDate) = "${d.dayOfMonth} ${d.month.name.take(3).lowercase().replaceFirstChar { it.uppercase() }} ${d.year}"

    return if (b == null || b == a) text(a) else text(a) + " – " + text(b)
}

/** The guide card shows until it is dismissed, and never to someone who said it is not their first WordCamp. */
private fun startHereVisible(store: club.campbuddy.shared.data.KeyValueStore, slug: String, firstWordCamp: String?): Boolean =
    store.get("ui:startHere:$slug") == null && firstWordCamp != "no"
