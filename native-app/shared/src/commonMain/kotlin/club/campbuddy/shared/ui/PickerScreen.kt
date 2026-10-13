package club.campbuddy.shared.ui

import androidx.compose.foundation.layout.Arrangement
import androidx.compose.foundation.layout.Column
import androidx.compose.foundation.layout.Row
import androidx.compose.foundation.layout.fillMaxSize
import androidx.compose.foundation.layout.fillMaxWidth
import androidx.compose.foundation.layout.padding
import androidx.compose.foundation.layout.statusBarsPadding
import androidx.compose.foundation.lazy.LazyColumn
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

/** The WordCamp picker: what the app is for, the events, and (if switched on) organizer sign-in. */
@Composable
fun PickerScreen(
    repository: Repository,
    banner: BannerConfig?,
    language: String,
    organizerEnabled: Boolean,
    onBanner: (BannerConfig) -> Unit,
    onOpen: (EventCard) -> Unit,
    onOrganizer: () -> Unit,
) {
    val words = LocalWords.current
    val brand = LocalBrand.current
    val env = LocalEnv.current
    var showOnboarding by remember { mutableStateOf(!env.user.onboardingDone && words.config.onboarding.enabled) }
    val loader = rememberLoader("events") { repository.events() }
    val state by loader.state

    LazyColumn(
        Modifier.fillMaxSize().background(brand.background).statusBarsPadding(),
        contentPadding = androidx.compose.foundation.layout.PaddingValues(16.dp),
        verticalArrangement = Arrangement.spacedBy(12.dp),
    ) {
        if (banner != null) item { BannerCard(banner, language) { onBanner(banner) } }

        item {
            Column(verticalArrangement = Arrangement.spacedBy(8.dp)) {
                Text(words("picker.title"), style = MaterialTheme.typography.headlineSmall)
                Text(words("picker.lead"), style = MaterialTheme.typography.bodyLarge)
                Row(horizontalArrangement = Arrangement.spacedBy(8.dp)) {
                    listOf("picker.free", "picker.no_signup", "picker.works_offline").forEach { key ->
                        Text("✓ " + words(key), style = MaterialTheme.typography.labelMedium)
                    }
                }
            }
        }

        item { SectionTitle(words("picker.choose")) }

        when (val s = state) {
            LoadState.Loading -> item { Loading() }
            is LoadState.Failed -> item { ErrorState(s.message, loader.reload) }
            is LoadState.Ready -> {
                if (s.fromCache) item { OfflineNote() }
                if (s.value.isEmpty()) item { Text(words("picker.empty"), style = MaterialTheme.typography.bodyMedium) }
                items(s.value, key = { it.slug }) { event -> EventRow(event) { onOpen(event) } }
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

@Composable
private fun EventRow(event: EventCard, onClick: () -> Unit) {
    Card(onClick = onClick) {
        Column(verticalArrangement = Arrangement.spacedBy(4.dp)) {
            Text(event.name, style = MaterialTheme.typography.titleMedium)
            val dates = datesText(event.startsOn, event.endsOn)
            if (dates != null) Text(dates, style = MaterialTheme.typography.bodyMedium)
            if (event.completed) Text("Completed", style = MaterialTheme.typography.labelMedium)
        }
    }
}

internal fun datesText(starts: String?, ends: String?): String? {
    val a = starts?.let { runCatching { LocalDate.parse(it) }.getOrNull() } ?: return null
    val b = ends?.let { runCatching { LocalDate.parse(it) }.getOrNull() }

    return if (b == null || b == a) EventClock.dayLabel(a) + " " + a.year else EventClock.dayLabel(a) + " – " + EventClock.dayLabel(b) + " " + b.year
}
