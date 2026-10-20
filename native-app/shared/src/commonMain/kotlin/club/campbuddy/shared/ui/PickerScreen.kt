package club.campbuddy.shared.ui

import androidx.compose.foundation.layout.Arrangement
import androidx.compose.foundation.layout.Column
import androidx.compose.foundation.layout.Row
import androidx.compose.foundation.layout.fillMaxSize
import androidx.compose.foundation.layout.fillMaxWidth
import androidx.compose.foundation.layout.navigationBarsPadding
import androidx.compose.foundation.layout.padding
import androidx.compose.foundation.layout.statusBarsPadding
import androidx.compose.foundation.lazy.LazyColumn
import kotlinx.datetime.TimeZone
import androidx.compose.runtime.saveable.rememberSaveable
import org.jetbrains.compose.resources.painterResource
import kotlinx.coroutines.launch
import androidx.compose.animation.core.Animatable
import androidx.compose.animation.core.FastOutSlowInEasing
import androidx.compose.animation.core.LinearEasing
import androidx.compose.animation.core.LinearOutSlowInEasing
import androidx.compose.animation.core.RepeatMode
import androidx.compose.animation.core.Spring
import androidx.compose.animation.core.StartOffset
import androidx.compose.animation.core.StartOffsetType
import androidx.compose.animation.core.animateFloat
import androidx.compose.animation.core.infiniteRepeatable
import androidx.compose.animation.core.rememberInfiniteTransition
import androidx.compose.animation.core.spring
import androidx.compose.animation.core.tween
import androidx.compose.foundation.Canvas
import androidx.compose.foundation.layout.offset
import androidx.compose.runtime.LaunchedEffect
import androidx.compose.ui.graphics.PathEffect
import androidx.compose.ui.graphics.drawscope.Stroke
import androidx.compose.ui.graphics.drawscope.rotate
import androidx.compose.ui.graphics.graphicsLayer
import kotlinx.coroutines.delay
import kotlin.math.PI
import kotlin.math.cos
import kotlin.math.sin
import campbuddy_native.shared.generated.resources.wordmark
import campbuddy_native.shared.generated.resources.app_icon
import campbuddy_native.shared.generated.resources.Res
import androidx.compose.ui.unit.sp
import androidx.compose.ui.text.font.FontWeight
import androidx.compose.ui.semantics.Role
import androidx.compose.ui.graphics.Color
import androidx.compose.ui.draw.clip
import androidx.compose.ui.draw.shadow
import androidx.compose.ui.Alignment
import androidx.compose.runtime.rememberCoroutineScope
import androidx.compose.foundation.shape.RoundedCornerShape
import androidx.compose.foundation.shape.CircleShape
import androidx.compose.foundation.lazy.rememberLazyListState
import androidx.compose.foundation.layout.size
import androidx.compose.foundation.layout.heightIn
import androidx.compose.foundation.layout.height
import androidx.compose.foundation.layout.PaddingValues
import androidx.compose.foundation.layout.FlowRow
import androidx.compose.foundation.layout.ExperimentalLayoutApi
import androidx.compose.foundation.layout.width
import androidx.compose.foundation.layout.Box
import androidx.compose.foundation.clickable
import androidx.compose.foundation.border
import androidx.compose.foundation.Image
import androidx.compose.foundation.lazy.items
import androidx.compose.foundation.background
import androidx.compose.material3.MaterialTheme
import androidx.compose.material3.Text
import androidx.compose.runtime.Composable
import androidx.compose.runtime.getValue
import androidx.compose.runtime.mutableStateOf
import androidx.compose.runtime.remember
import androidx.compose.runtime.setValue
import androidx.compose.ui.Modifier
import androidx.compose.ui.unit.dp
import club.campbuddy.shared.data.Repository
import club.campbuddy.shared.domain.EventClock
import club.campbuddy.shared.model.BannerConfig
import club.campbuddy.shared.model.EventCard
import kotlinx.datetime.LocalDate

@OptIn(ExperimentalLayoutApi::class)
/** The WordCamp picker: what the app is for, the events, and (if switched on) organizer sign-in. */
@Composable
fun PickerScreen(
    repository: Repository,
    banner: BannerConfig?,
    language: String,
    organizerEnabled: Boolean,
    onBanner: (BannerConfig) -> Unit,
    onOpen: (EventCard) -> Unit,
    onFirstTimer: (EventCard) -> Unit,
    onOrganizer: () -> Unit,
) {
    val words = LocalWords.current
    val brand = LocalBrand.current
    val env = LocalEnv.current
    var showOnboarding by remember { mutableStateOf(!env.user.onboardingDone && words.config.onboarding.enabled) }
    val loader = rememberLoader("events") { repository.events() }
    val state by loader.state
    val events = (state as? LoadState.Ready)?.value.orEmpty()
    var picked by rememberSaveable { mutableStateOf<String?>(null) }
    var shownCount by rememberSaveable { mutableStateOf(5) }
    val listState = rememberLazyListState()
    val scope = rememberCoroutineScope()

    val staffToken = repository.organizerToken()
    val staffRole = repository.organizerRole() ?: "manager"
    val staffName = repository.organizerName()
    val isStaff = staffToken != null
    val isAdmin = staffRole == "admin"

    Column(Modifier.fillMaxSize().background(brand.background).statusBarsPadding().navigationBarsPadding()) {
        Row(
            Modifier.fillMaxWidth().padding(horizontal = 16.dp, vertical = 8.dp),
            horizontalArrangement = Arrangement.SpaceBetween,
            verticalAlignment = Alignment.CenterVertically,
        ) {
            Image(painterResource(Res.drawable.wordmark), contentDescription = "CampBuddy", modifier = Modifier.height(40.dp))
            if (isStaff) {
                Box(
                    modifier = Modifier
                        .clip(RoundedCornerShape(50))
                        .background(if (isAdmin) brand.primary.copy(alpha = 0.12f) else brand.accent.copy(alpha = 0.22f))
                        .border(1.dp, if (isAdmin) brand.primary.copy(alpha = 0.40f) else brand.accent.copy(alpha = 0.50f), RoundedCornerShape(50))
                        .clickable(role = Role.Button, onClickLabel = "Staff Area", onClick = onOrganizer)
                        .padding(horizontal = 12.dp, vertical = 6.dp),
                    contentAlignment = Alignment.Center,
                ) {
                    Text(
                        if (isAdmin) "👑 Super Admin" else "👔 Event Manager",
                        style = MaterialTheme.typography.labelMedium.copy(
                            fontWeight = FontWeight.Bold,
                            color = if (isAdmin) brand.primary else brand.ink,
                        ),
                    )
                }
            } else if (organizerEnabled) {
                Box(
                    modifier = Modifier
                        .clip(RoundedCornerShape(50))
                        .background(brand.peach)
                        .clickable(role = Role.Button, onClickLabel = "Staff Login", onClick = onOrganizer)
                        .padding(horizontal = 12.dp, vertical = 6.dp),
                    contentAlignment = Alignment.Center,
                ) {
                    Text(
                        "Staff Sign In",
                        style = MaterialTheme.typography.labelSmall.copy(fontWeight = FontWeight.Bold, color = brand.ink),
                    )
                }
            }
        }
        Box(Modifier.fillMaxWidth().height(1.dp).background(brand.line))

        LazyColumn(
            Modifier.fillMaxSize(), state = listState,
            contentPadding = PaddingValues(16.dp),
            verticalArrangement = Arrangement.spacedBy(14.dp),
        ) {
            if (banner != null && !env.user.isBannerDismissed(banner.dismissKey)) {
                item {
                    BannerCard(
                        banner = banner,
                        language = language,
                        onAction = { onBanner(banner) },
                        onDismiss = { env.user.dismissBanner(banner.dismissKey) },
                    )
                }
            }

            if (isStaff) {
                item {
                    val name = staffName?.takeIf { it.isNotBlank() } ?: (if (isAdmin) "Administrator" else "Event Manager")
                    Card {
                        Column(verticalArrangement = Arrangement.spacedBy(8.dp)) {
                            Row(
                                Modifier.fillMaxWidth(),
                                horizontalArrangement = Arrangement.SpaceBetween,
                                verticalAlignment = Alignment.CenterVertically,
                            ) {
                                Row(horizontalArrangement = Arrangement.spacedBy(8.dp), verticalAlignment = Alignment.CenterVertically) {
                                    Text(if (isAdmin) "👑" else "👔", style = MaterialTheme.typography.titleLarge)
                                    Column {
                                        Text(
                                            if (isAdmin) "Super Admin Console" else "Event Manager Hub",
                                            style = MaterialTheme.typography.titleMedium.copy(fontWeight = FontWeight.Bold),
                                        )
                                        Text("Signed in as $name", style = MaterialTheme.typography.bodySmall.copy(color = brand.muted))
                                    }
                                }
                            }
                            Text(
                                if (isAdmin) "Full platform access: manage schedules, announcements, reports, and volunteer roles across all WordCamps."
                                else "Manager access: push real-time schedule delays, moderate attendee reports, and configure venue details for your assigned WordCamps.",
                                style = MaterialTheme.typography.bodyMedium,
                            )
                            PrimaryButton(
                                if (isAdmin) "Open Admin Console →" else "Open Event Manager Hub →",
                                onClick = onOrganizer,
                                modifier = Modifier.fillMaxWidth().padding(top = 4.dp),
                            )
                        }
                    }
                }
            }

            item { HeroOrbit(words("picker.pill_talks"), words("picker.pill_people"), words("picker.pill_reminders"), words("picker.pill_camp_card")) }

            item {
                Column(verticalArrangement = Arrangement.spacedBy(10.dp)) {
                    Text(words("picker.kicker").uppercase(), style = MaterialTheme.typography.labelLarge.copy(color = brand.primary, letterSpacing = 1.5.sp, fontWeight = FontWeight.Bold))
                    Text(words("picker.title"), style = MaterialTheme.typography.headlineSmall.copy(fontSize = 28.sp, lineHeight = 32.2.sp, fontWeight = FontWeight.ExtraBold, letterSpacing = (-0.28).sp))
                    Text(words("picker.lead"), style = MaterialTheme.typography.bodyLarge.copy(color = brand.muted))
                    FlowRow(horizontalArrangement = Arrangement.spacedBy(8.dp), verticalArrangement = Arrangement.spacedBy(8.dp)) {
                        listOf("picker.free", "picker.no_signup", "picker.works_offline").forEach { key ->
                            Row(
                                Modifier.background(brand.peach, RoundedCornerShape(50)).padding(horizontal = 14.dp, vertical = 8.dp),
                                horizontalArrangement = Arrangement.spacedBy(6.dp),
                            ) {
                                Text("✓", style = MaterialTheme.typography.labelLarge.copy(color = brand.primary))
                                Text(words(key), style = MaterialTheme.typography.labelLarge.copy(color = brand.ink))
                            }
                        }
                    }
                }
            }

            item {
                Column(verticalArrangement = Arrangement.spacedBy(8.dp)) {
                    PrimaryButton(words("picker.choose"), { scope.launch { listState.animateScrollToItem(if (banner != null) 4 else 3) } }, Modifier.fillMaxWidth().heightIn(min = 56.dp))
                    if (events.isNotEmpty()) QuietButton(words("picker.first_timer"), { onFirstTimer(events.first()) }, Modifier.fillMaxWidth().heightIn(min = 56.dp))
                }
            }

            item {
                Column(Modifier.padding(top = 12.dp), verticalArrangement = Arrangement.spacedBy(4.dp)) {
                    Text(words("picker.choose"), style = MaterialTheme.typography.headlineSmall)
                    Text(words("picker.subtitle"), style = MaterialTheme.typography.bodyLarge.copy(color = brand.muted))
                }
            }

            when (val s = state) {
                LoadState.Loading -> item { Loading() }
                is LoadState.Failed -> item { ErrorState(s.message, loader.reload) }
                is LoadState.Ready -> {
                    if (s.fromCache) item { OfflineNote() }
                    if (s.value.isEmpty()) item { Text(words("picker.empty"), style = MaterialTheme.typography.bodyMedium) }
                    val countries = s.value.mapNotNull { it.country }.distinct().sortedBy { countryName(it) }
                    // Start on the phone's own country when a WordCamp is there (found from the time zone), else on everything.
                    val mine = s.value.firstOrNull { it.country != null && it.timezone == TimeZone.currentSystemDefault().id }?.country
                    val chosen = picked ?: mine ?: "all"
                    val inCountry = if (chosen == "all") s.value else s.value.filter { it.country == chosen }
                    if (countries.size > 1) {
                        item { CountryFilter(countries, s.value, chosen, onPick = { picked = it; shownCount = 5 }) }
                        item {
                            Text(
                                if (chosen == "all") "Showing ${minOf(shownCount, inCountry.size)} of ${inCountry.size} WordCamps"
                                else "Showing ${minOf(shownCount, inCountry.size)} of ${inCountry.size} WordCamps in ${countryName(chosen)}",
                                style = MaterialTheme.typography.bodySmall,
                            )
                        }
                    }
                    items(inCountry.take(shownCount), key = { it.slug }) { event -> EventRow(event, words("picker.open_event")) { onOpen(event) } }
                    if (inCountry.size > shownCount) {
                        item { QuietButton("Load ${minOf(5, inCountry.size - shownCount)} more", { shownCount += 5 }, Modifier.fillMaxWidth()) }
                    }
                }
            }

            if (showOnboarding) {
                item { OnboardingCard(onDone = { showOnboarding = false }) }
            }

            if (organizerEnabled) {
                item {
                    if (isStaff) {
                        val name = staffName?.takeIf { it.isNotBlank() } ?: (if (isAdmin) "Admin" else "Manager")
                        val roleTitle = if (isAdmin) "Super Admin" else "Event Manager"
                        QuietButton("Signed in: $name ($roleTitle) · Open Staff Hub", onOrganizer, Modifier.fillMaxWidth().padding(top = 8.dp))
                    } else {
                        QuietButton("Event staff: sign in", onOrganizer, Modifier.fillMaxWidth().padding(top = 8.dp))
                    }
                }
            }
            item { PrivacyLinks(Modifier.padding(top = 4.dp), centered = true) }
        }
    }
}

/**
 * The website's hero, with its motion: a dashed ring turns slowly round the app mark, the mark breathes and sends out two
 * signal ripples, a coffee cup and a map pin travel round the inner ring, and the four pills pop in one after another and
 * then float. (The website's hero-orbit animation, same timings.) Phones set to no animations show it still.
 */
@Composable
private fun HeroOrbit(talks: String, people: String, reminders: String, campCard: String) {
    val brand = LocalBrand.current
    val motion = rememberInfiniteTransition(label = "orbit")
    val turn by motion.animateFloat(0f, 360f, infiniteRepeatable(tween(60_000, easing = LinearEasing)), label = "turn")
    val travel by motion.animateFloat(0f, 360f, infiniteRepeatable(tween(16_000, easing = LinearEasing)), label = "travel")
    val breathe by motion.animateFloat(1f, 1.045f, infiniteRepeatable(tween(2_000, easing = FastOutSlowInEasing), RepeatMode.Reverse), label = "breathe")
    val rippleA by motion.animateFloat(0f, 1f, infiniteRepeatable(tween(3_200, easing = LinearOutSlowInEasing)), label = "rippleA")
    val rippleB by motion.animateFloat(0f, 1f, infiniteRepeatable(tween(3_200, easing = LinearOutSlowInEasing), initialStartOffset = StartOffset(1_600, StartOffsetType.FastForward)), label = "rippleB")
    val outer = Color(0xFFC33A19).copy(alpha = 0.28f)
    val inner = Color(0xFFF4A629).copy(alpha = 0.35f)
    val ringRadius = 72.dp

    Box(Modifier.fillMaxWidth().height(250.dp), contentAlignment = Alignment.Center) {
        Canvas(Modifier.size(230.dp)) {
            val stroke = 1.5.dp.toPx()
            rotate(turn) {
                drawCircle(outer, radius = size.minDimension / 2 - stroke, style = Stroke(stroke, pathEffect = PathEffect.dashPathEffect(floatArrayOf(7.dp.toPx(), 6.dp.toPx()))))
            }
        }
        Canvas(Modifier.size(145.dp)) { drawCircle(inner, radius = size.minDimension / 2 - 1.5.dp.toPx(), style = Stroke(1.5.dp.toPx())) }
        listOf(rippleA, rippleB).forEach { p ->
            Box(
                Modifier.size(100.dp)
                    .graphicsLayer { scaleX = 1f + 0.7f * p; scaleY = 1f + 0.7f * p; alpha = 0.9f * (1f - p) }
                    .border(2.dp, Color(0xFFC33A19).copy(alpha = 0.35f), RoundedCornerShape(24.dp)),
            )
        }
        Box(
            Modifier.size(100.dp).graphicsLayer { scaleX = breathe; scaleY = breathe }
                .shadow(8.dp, RoundedCornerShape(24.dp), clip = false).background(Color.White, RoundedCornerShape(24.dp)).border(2.dp, brand.peach, RoundedCornerShape(24.dp)),
            contentAlignment = Alignment.Center,
        ) { Image(painterResource(Res.drawable.app_icon), contentDescription = null, modifier = Modifier.size(76.dp)) }
        listOf(Triple("coffee", Color(0xFFE39D1C), 0f), Triple("map-pin", Color(0xFF049395), 180f)).forEach { (icon, tint, offset) ->
            val angle = (travel + offset) * (PI.toFloat() / 180f)
            Box(
                Modifier.offset(ringRadius * cos(angle), ringRadius * sin(angle)).size(30.dp).shadow(3.dp, CircleShape, clip = false).background(Color.White, CircleShape),
                contentAlignment = Alignment.Center,
            ) { LineIcon(icon, tint, size = 18.dp) }
        }
        FeaturePill(talks, "calendar", Modifier.align(Alignment.TopStart).padding(top = 8.dp), popAfter = 100, phase = 650)
        FeaturePill(people, "users", Modifier.align(Alignment.TopEnd).padding(top = 40.dp), popAfter = 250, phase = 2_100)
        FeaturePill(reminders, "bell", Modifier.align(Alignment.BottomStart).padding(bottom = 40.dp), popAfter = 400, phase = 900)
        FeaturePill(campCard, "id-card", Modifier.align(Alignment.BottomEnd).padding(bottom = 4.dp), popAfter = 550, phase = 1_700)
    }
}

/** One pill: pops in (springy) after [popAfter] ms, then floats up and down on its own beat ([phase] ms into the cycle). */
@Composable
private fun FeaturePill(label: String, icon: String, modifier: Modifier, popAfter: Int = 0, phase: Int = 0) {
    val brand = LocalBrand.current
    val shape = RoundedCornerShape(50)
    val pop = remember { Animatable(0f) }
    LaunchedEffect(Unit) {
        delay(popAfter.toLong())
        pop.animateTo(1f, spring(dampingRatio = 0.55f, stiffness = Spring.StiffnessMediumLow))
    }
    val float by rememberInfiniteTransition(label = "float").animateFloat(
        0f, 1f, infiniteRepeatable(tween(2_500, easing = FastOutSlowInEasing), RepeatMode.Reverse, StartOffset(phase, StartOffsetType.FastForward)), label = "float",
    )
    Row(
        modifier
            .graphicsLayer {
                val grow = 0.6f + 0.4f * pop.value
                scaleX = grow; scaleY = grow
                alpha = pop.value.coerceIn(0f, 1f)
                translationX = 2.dp.toPx() * float
                translationY = -6.dp.toPx() * float + (1f - pop.value) * 8.dp.toPx()
                rotationZ = -1.5f * float
            }
            .shadow(3.dp, shape, clip = false).background(Color.White, shape).padding(start = 6.dp, end = 16.dp, top = 6.dp, bottom = 6.dp),
        horizontalArrangement = Arrangement.spacedBy(8.dp), verticalAlignment = Alignment.CenterVertically,
    ) {
        Box(Modifier.size(34.dp).background(brand.peach, CircleShape), contentAlignment = Alignment.Center) { LineIcon(icon, brand.primary, size = 18.dp) }
        Text(label, style = MaterialTheme.typography.labelLarge.copy(color = brand.ink, fontWeight = FontWeight.Bold))
    }
}

@Composable
private fun EventRow(event: EventCard, openLabel: String, onClick: () -> Unit) {
    val env = LocalEnv.current
    val brand = LocalBrand.current
    val shape = RoundedCornerShape(brand.radius.dp)
    // A finished WordCamp gets the website's cool teal wash and a "Completed" pill, so it reads as done.
    val done = event.completed
    val teal = Color(0xFF049395)
    val fill = if (done) androidx.compose.ui.graphics.lerp(brand.surface, teal, 0.07f) else brand.surface
    val edge = if (done) androidx.compose.ui.graphics.lerp(brand.line, teal, 0.30f) else brand.line
    val divider = if (done) androidx.compose.ui.graphics.lerp(brand.line, teal, 0.22f) else brand.line
    val cta = if (done) androidx.compose.ui.graphics.lerp(brand.ink, teal, 0.70f) else brand.primary
    val photos = event.memories?.photos?.takeIf { it.isNotBlank() }
    val videos = event.memories?.videos?.takeIf { it.isNotBlank() }
    Column(
        Modifier.fillMaxWidth().shadow(2.dp, shape, clip = false).clip(shape).background(fill, shape).border(1.dp, edge, shape)
            .clickable(role = Role.Button, onClick = onClick),
    ) {
        Row(Modifier.padding(20.dp), horizontalArrangement = Arrangement.spacedBy(18.dp), verticalAlignment = Alignment.CenterVertically) {
            Box(Modifier.size(64.dp).background(Color.White, CircleShape).border(1.dp, brand.line, CircleShape), contentAlignment = Alignment.Center) {
                RemoteImage(event.logo, Modifier.size(52.dp).clip(CircleShape)) {
                    Image(painterResource(Res.drawable.app_icon), contentDescription = null, modifier = Modifier.size(46.dp))
                }
            }
            Column(verticalArrangement = Arrangement.spacedBy(2.dp)) {
                datesText(event.startsOn, event.endsOn)?.let {
                    Text(it.uppercase(), style = MaterialTheme.typography.labelMedium.copy(color = brand.primary, letterSpacing = 0.67.sp, fontSize = 11.2.sp))
                }
                Text(event.name, style = MaterialTheme.typography.titleMedium)
                if (done) Row(
                    Modifier.padding(top = 4.dp).background(androidx.compose.ui.graphics.lerp(brand.surface, teal, 0.14f), RoundedCornerShape(50)).padding(horizontal = 8.dp, vertical = 2.dp),
                    horizontalArrangement = Arrangement.spacedBy(5.dp), verticalAlignment = Alignment.CenterVertically,
                ) {
                    LineIcon("party", cta, size = 13.dp)
                    Text("Completed", style = MaterialTheme.typography.labelMedium.copy(color = cta, fontWeight = FontWeight.Bold))
                }
            }
        }
        event.venue?.let { venue ->
            Row(Modifier.padding(start = 20.dp, end = 20.dp, bottom = 16.dp), horizontalArrangement = Arrangement.spacedBy(8.dp), verticalAlignment = Alignment.Top) {
                Box(Modifier.padding(top = 3.dp)) { LineIcon("map-pin", brand.primary, size = 14.dp) }
                Text(venue, style = MaterialTheme.typography.bodyMedium.copy(color = brand.muted))
            }
        }
        Box(Modifier.fillMaxWidth().height(1.dp).background(divider))
        Text(openLabel, style = MaterialTheme.typography.labelLarge.copy(color = cta), modifier = Modifier.padding(16.dp))

        // Photos and recorded talks on WordPress.tv (EventMemories)
        if (photos != null || videos != null) {
            Box(Modifier.fillMaxWidth().height(1.dp).background(divider))
            Row(
                Modifier.fillMaxWidth().background(androidx.compose.ui.graphics.lerp(brand.surface, teal, 0.08f)),
                verticalAlignment = Alignment.CenterVertically,
            ) {
                if (photos != null) {
                    Row(
                        Modifier.weight(1f).heightIn(min = 44.dp).clickable(role = Role.Button) { env.deps.openUrl(photos) }
                            .padding(horizontal = 12.dp, vertical = 10.dp),
                        horizontalArrangement = Arrangement.Center,
                        verticalAlignment = Alignment.CenterVertically,
                    ) {
                        LineIcon("camera", cta, size = 16.dp)
                        Text("  Photos", style = MaterialTheme.typography.labelLarge.copy(color = cta, fontWeight = FontWeight.Bold))
                    }
                }
                if (photos != null && videos != null) {
                    Box(Modifier.width(1.dp).height(24.dp).background(divider))
                }
                if (videos != null) {
                    Row(
                        Modifier.weight(1f).heightIn(min = 44.dp).clickable(role = Role.Button) { env.deps.openUrl(videos) }
                            .padding(horizontal = 12.dp, vertical = 10.dp),
                        horizontalArrangement = Arrangement.Center,
                        verticalAlignment = Alignment.CenterVertically,
                    ) {
                        LineIcon("play", cta, size = 16.dp)
                        Text("  Talks on WordPress.tv", style = MaterialTheme.typography.labelLarge.copy(color = cta, fontWeight = FontWeight.Bold))
                    }
                }
            }
        }

        if (done) {
            // The website's joined "Save your WordCamp Data" bar: the PDF of what this phone kept for the event.
            val scope = androidx.compose.runtime.rememberCoroutineScope()
            var busy by remember { mutableStateOf(false) }
            var said by remember { mutableStateOf<String?>(null) }
            Row(
                Modifier.fillMaxWidth().background(cta).clickable(role = Role.Button) {
                    if (!busy) scope.launch { busy = true; said = saveMyDay(env, event.slug); busy = false }
                }.padding(horizontal = 16.dp, vertical = 14.dp),
                horizontalArrangement = Arrangement.Center, verticalAlignment = Alignment.CenterVertically,
            ) {
                LineIcon("download", Color.White, size = 16.dp)
                Text(if (busy) "  Making your PDF…" else "  Save your WordCamp Data", style = MaterialTheme.typography.labelLarge.copy(color = Color.White, fontWeight = FontWeight.Bold))
            }
            said?.let { Text(it, style = MaterialTheme.typography.bodySmall.copy(color = cta), modifier = Modifier.padding(horizontal = 16.dp, vertical = 8.dp)) }
        }
    }
}

internal fun datesText(starts: String?, ends: String?): String? {
    val a = starts?.let { runCatching { LocalDate.parse(it) }.getOrNull() } ?: return null
    val b = ends?.let { runCatching { LocalDate.parse(it) }.getOrNull() }

    return if (b == null || b == a) EventClock.dayLabel(a) + " " + a.year else EventClock.dayLabel(a) + " – " + EventClock.dayLabel(b) + " " + b.year
}

/** The website's country filter: a dropdown pill (with counts) and, when filtered, a "Show all" pill. */
@Composable
private fun CountryFilter(countries: List<String>, events: List<EventCard>, chosen: String, onPick: (String) -> Unit) {
    val brand = LocalBrand.current
    var open by remember { mutableStateOf(false) }
    val pill = RoundedCornerShape(50)
    val label = if (chosen == "all") "All countries (${events.size})" else "${countryName(chosen)} (${events.count { it.country == chosen }})"

    Row(horizontalArrangement = Arrangement.spacedBy(8.dp), verticalAlignment = Alignment.CenterVertically) {
        Box {
            Row(
                Modifier.heightIn(min = 44.dp).background(brand.surface, pill).border(1.dp, brand.primary, pill).clickable(role = Role.Button) { open = true }.padding(horizontal = 16.dp),
                horizontalArrangement = Arrangement.spacedBy(8.dp), verticalAlignment = Alignment.CenterVertically,
            ) {
                LineIcon("map-pin", brand.primary, size = 16.dp)
                Text(label, style = MaterialTheme.typography.labelLarge.copy(color = brand.ink))
                LineIcon("chevron-down", brand.ink, size = 16.dp)
            }
            androidx.compose.material3.DropdownMenu(expanded = open, onDismissRequest = { open = false }) {
                androidx.compose.material3.DropdownMenuItem(text = { Text("All countries (${events.size})") }, onClick = { open = false; onPick("all") })
                countries.forEach { c ->
                    androidx.compose.material3.DropdownMenuItem(text = { Text("${countryName(c)} (${events.count { it.country == c }})") }, onClick = { open = false; onPick(c) })
                }
            }
        }
        if (chosen != "all") {
            Row(
                Modifier.heightIn(min = 44.dp).border(1.dp, brand.line, pill).clickable(role = Role.Button) { onPick("all") }.padding(horizontal = 12.dp),
                horizontalArrangement = Arrangement.spacedBy(6.dp), verticalAlignment = Alignment.CenterVertically,
            ) {
                LineIcon("x", brand.primary, size = 14.dp)
                Text("Show all", style = MaterialTheme.typography.labelLarge.copy(color = brand.primary))
            }
        }
    }
}

private val COUNTRY_NAMES = mapOf(
    "IN" to "India", "US" to "United States", "GB" to "United Kingdom", "CA" to "Canada", "DE" to "Germany", "FR" to "France", "NL" to "Netherlands",
    "ZA" to "South Africa", "PK" to "Pakistan", "BG" to "Bulgaria", "AU" to "Australia", "SG" to "Singapore", "JP" to "Japan", "AE" to "United Arab Emirates",
    "ES" to "Spain", "IT" to "Italy", "BR" to "Brazil", "MX" to "Mexico", "NG" to "Nigeria", "KE" to "Kenya", "ID" to "Indonesia", "PH" to "Philippines",
    "MY" to "Malaysia", "TH" to "Thailand", "LK" to "Sri Lanka", "NP" to "Nepal", "BD" to "Bangladesh", "PT" to "Portugal", "PL" to "Poland", "CH" to "Switzerland",
    "SE" to "Sweden", "NO" to "Norway", "DK" to "Denmark", "FI" to "Finland", "IE" to "Ireland", "AT" to "Austria", "BE" to "Belgium", "GR" to "Greece",
    "TR" to "Turkey", "EG" to "Egypt", "NZ" to "New Zealand", "KR" to "South Korea", "CN" to "China", "HK" to "Hong Kong", "AR" to "Argentina", "CO" to "Colombia", "CL" to "Chile",
)

private fun countryName(code: String): String = COUNTRY_NAMES[code] ?: code
