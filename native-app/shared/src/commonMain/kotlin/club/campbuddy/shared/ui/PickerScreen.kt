package club.campbuddy.shared.ui

import androidx.compose.foundation.layout.Arrangement
import androidx.compose.foundation.layout.Column
import androidx.compose.foundation.layout.Row
import androidx.compose.foundation.layout.fillMaxSize
import androidx.compose.foundation.layout.fillMaxWidth
import androidx.compose.foundation.layout.padding
import androidx.compose.foundation.layout.statusBarsPadding
import androidx.compose.foundation.lazy.LazyColumn
import kotlinx.datetime.TimeZone
import androidx.compose.runtime.saveable.rememberSaveable
import org.jetbrains.compose.resources.painterResource
import kotlinx.coroutines.launch
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

    Column(Modifier.fillMaxSize().background(brand.background).statusBarsPadding()) {
        Row(Modifier.fillMaxWidth().padding(horizontal = 16.dp, vertical = 8.dp), verticalAlignment = Alignment.CenterVertically) {
            Image(painterResource(Res.drawable.wordmark), contentDescription = "CampBuddy", modifier = Modifier.height(40.dp))
        }
        Box(Modifier.fillMaxWidth().height(1.dp).background(brand.line))

        LazyColumn(
            Modifier.fillMaxSize(), state = listState,
            contentPadding = PaddingValues(16.dp),
            verticalArrangement = Arrangement.spacedBy(14.dp),
        ) {
            if (banner != null) item { BannerCard(banner, language) { onBanner(banner) } }

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
                    QuietButton("Event staff: sign in", onOrganizer, Modifier.fillMaxWidth().padding(top = 8.dp))
                }
            }
        }
    }
}

/** The website's hero: the app mark in the middle with four feature pills around it. */
@Composable
private fun HeroOrbit(talks: String, people: String, reminders: String, campCard: String) {
    val brand = LocalBrand.current
    Box(Modifier.fillMaxWidth().height(250.dp), contentAlignment = Alignment.Center) {
        Box(
            Modifier.size(100.dp).background(Color.White, RoundedCornerShape(24.dp)).border(2.dp, brand.peach, RoundedCornerShape(24.dp)),
            contentAlignment = Alignment.Center,
        ) { Image(painterResource(Res.drawable.app_icon), contentDescription = null, modifier = Modifier.size(76.dp)) }
        FeaturePill(talks, "calendar", Modifier.align(Alignment.TopStart).padding(top = 8.dp))
        FeaturePill(people, "users", Modifier.align(Alignment.TopEnd).padding(top = 40.dp))
        FeaturePill(reminders, "bell", Modifier.align(Alignment.BottomStart).padding(bottom = 40.dp))
        FeaturePill(campCard, "id-card", Modifier.align(Alignment.BottomEnd).padding(bottom = 4.dp))
    }
}

@Composable
private fun FeaturePill(label: String, icon: String, modifier: Modifier) {
    val brand = LocalBrand.current
    val shape = RoundedCornerShape(50)
    Row(
        modifier.shadow(3.dp, shape, clip = false).background(Color.White, shape).padding(start = 6.dp, end = 16.dp, top = 6.dp, bottom = 6.dp),
        horizontalArrangement = Arrangement.spacedBy(8.dp), verticalAlignment = Alignment.CenterVertically,
    ) {
        Box(Modifier.size(34.dp).background(brand.peach, CircleShape), contentAlignment = Alignment.Center) { LineIcon(icon, brand.primary, size = 18.dp) }
        Text(label, style = MaterialTheme.typography.labelLarge.copy(color = brand.ink, fontWeight = FontWeight.Bold))
    }
}

@Composable
private fun EventRow(event: EventCard, openLabel: String, onClick: () -> Unit) {
    val brand = LocalBrand.current
    val shape = RoundedCornerShape(brand.radius.dp)
    // A finished WordCamp gets the website's cool teal wash and a "Completed" pill, so it reads as done.
    val done = event.completed
    val teal = Color(0xFF049395)
    val fill = if (done) androidx.compose.ui.graphics.lerp(brand.surface, teal, 0.07f) else brand.surface
    val edge = if (done) androidx.compose.ui.graphics.lerp(brand.line, teal, 0.30f) else brand.line
    val divider = if (done) androidx.compose.ui.graphics.lerp(brand.line, teal, 0.22f) else brand.line
    val cta = if (done) androidx.compose.ui.graphics.lerp(brand.ink, teal, 0.70f) else brand.primary
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
        if (done) {
            // The website's joined "Save your WordCamp Data" bar: the PDF of what this phone kept for the event.
            val env = LocalEnv.current
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
