package club.campbuddy.shared.ui

import androidx.compose.foundation.clickable
import androidx.compose.foundation.layout.Arrangement
import androidx.compose.foundation.layout.ExperimentalLayoutApi
import androidx.compose.foundation.layout.heightIn
import androidx.compose.foundation.layout.wrapContentHeight
import androidx.compose.foundation.layout.padding
import androidx.compose.foundation.layout.Column
import androidx.compose.foundation.layout.fillMaxWidth
import androidx.compose.material3.AlertDialog
import androidx.compose.material3.MaterialTheme
import androidx.compose.material3.Text
import androidx.compose.runtime.Composable
import androidx.compose.runtime.getValue
import androidx.compose.runtime.mutableStateOf
import androidx.compose.runtime.remember
import androidx.compose.runtime.rememberCoroutineScope
import androidx.compose.runtime.setValue
import androidx.compose.ui.Alignment
import androidx.compose.ui.Modifier
import androidx.compose.ui.text.style.TextAlign
import androidx.compose.ui.unit.dp
import club.campbuddy.shared.domain.EventClock
import kotlinx.coroutines.launch

/**
 * "Your data": everything CampBuddy keeps is on this phone, and this clears it. An active discovery
 * profile lives on the server, so it is left first (the website asks the same, as a separate step).
 */
@Composable
fun DataControls(slug: String) {
    val env = LocalEnv.current
    val brand = LocalBrand.current
    val scope = rememberCoroutineScope()
    var asking by remember { mutableStateOf(false) }
    var note by remember { mutableStateOf<String?>(null) }
    var saving by remember { mutableStateOf(false) }
    var changing by remember { mutableStateOf(false) }
    val event = (rememberLoader("event:$slug") { env.repository.event(slug) }.state.value as? LoadState.Ready)?.value
    val lastDay = event?.let { runCatching { kotlinx.datetime.LocalDate.parse(it.lastDay.orEmpty()) }.getOrNull() }
    val discoverable = env.user.discovery(slug) != null

    Card {
        Column(verticalArrangement = Arrangement.spacedBy(8.dp)) {
            Text("Your data", style = MaterialTheme.typography.titleLarge)
            Text(
                "Your saved sessions, quests, Camp Card and answers live only on this phone. Clearing them cannot be undone.",
                style = MaterialTheme.typography.bodyLarge.copy(color = brand.muted),
            )
            if (event != null && lastDay != null) {
                val choice = env.user.dataChoice(slug)
                Text(
                    when {
                        choice == null -> "When this WordCamp is over we will ask whether to keep your data on this phone or delete it after $EVENT_DATA_DAYS days."
                        choice.keep -> "After this WordCamp: your data stays on this phone until you clear it."
                        else -> "After this WordCamp: your data is deleted from this phone on ${EventClock.dayLabel(deleteDay(lastDay))} ${deleteDay(lastDay).year}."
                    },
                    style = MaterialTheme.typography.bodyMedium.copy(color = brand.muted),
                )
                QuietButton("Keep or delete after the event", { changing = true }, Modifier.fillMaxWidth())
            }
            QuietButton(if (saving) "Making your PDF…" else "Save my day as PDF", { if (!saving) scope.launch { saving = true; note = saveMyDay(env, slug); saving = false } }, Modifier.fillMaxWidth())
            QuietButton("Download my data (JSON)", { env.deps.shareFile("campbuddy-my-data.json", "application/json", env.user.exportJson().encodeToByteArray()); note = "Your data is ready. Choose where to save it." }, Modifier.fillMaxWidth())
            QuietButton("Clear my data on this phone", { asking = true }, Modifier.fillMaxWidth())
            PrivacyLinks()
            note?.let { Text(it, style = MaterialTheme.typography.bodyMedium.copy(color = brand.primary)) }
        }
    }

    if (changing && event != null && lastDay != null) {
        AfterEventChoice(slug, event.name, lastDay, club.campbuddy.shared.domain.EventClock.zone(event.timezone)) { changing = false }
    }

    if (asking) {
        Sheet(
            "Clear everything on this phone?", onClose = { asking = false },
            actions = {
                SheetActions(
                    "Clear",
                    {
                        asking = false
                        scope.launch {
                            env.user.discovery(slug)?.let { mine -> runCatching { env.api.leaveDiscovery(slug, mine.discoveryId, mine.ownerToken) } }
                            runCatching { env.deps.reminders.replaceAll(emptyList()) }
                            env.user.eraseAll()
                            note = "Cleared. Nothing of yours is left on this phone."
                        }
                    },
                    "Keep it", { asking = false },
                )
            },
        ) {
            Text(
                if (discoverable) "You are still in attendee discovery, so you will leave it first. Then your saved sessions, quests, Camp Card and answers are removed from this phone."
                else "Your saved sessions, quests, Camp Card and answers are removed from this phone.",
                style = MaterialTheme.typography.bodyLarge,
            )
        }
    }
}

/** The website's three footer links: the privacy notice, the terms, and the address for data requests and complaints. */
@OptIn(ExperimentalLayoutApi::class)
@Composable
fun PrivacyLinks(modifier: Modifier = Modifier, centered: Boolean = false) {
    val env = LocalEnv.current
    val brand = LocalBrand.current
    var sheetDoc by remember { mutableStateOf<String?>(null) }
    val style = MaterialTheme.typography.bodyMedium.copy(
        color = brand.primary,
        textDecoration = androidx.compose.ui.text.style.TextDecoration.Underline,
    )

    Column(
        modifier = modifier.fillMaxWidth(),
        horizontalAlignment = if (centered) Alignment.CenterHorizontally else Alignment.Start,
        verticalArrangement = Arrangement.spacedBy(8.dp),
    ) {
        androidx.compose.foundation.layout.FlowRow(
            modifier = Modifier.fillMaxWidth(),
            horizontalArrangement = if (centered) Arrangement.spacedBy(16.dp, Alignment.CenterHorizontally) else Arrangement.spacedBy(16.dp),
        ) {
            listOf(
                "Privacy notice" to { sheetDoc = "privacy" },
                "Terms of use" to { sheetDoc = "terms" },
                "Data request or complaint" to { env.deps.openUrl("mailto:$GRIEVANCE_EMAIL?subject=CampBuddy%20data%20request") },
            ).forEach { (label, go) ->
                Text(
                    label,
                    style = style,
                    modifier = Modifier
                        .heightIn(min = 44.dp)
                        .clickable(role = androidx.compose.ui.semantics.Role.Button, onClick = go)
                        .wrapContentHeight(Alignment.CenterVertically),
                )
            }
        }

        Text(
            "© 2026 CampBuddy. Made for the WordPress community.",
            style = MaterialTheme.typography.bodySmall.copy(color = brand.muted),
            textAlign = if (centered) TextAlign.Center else TextAlign.Start,
            modifier = Modifier.padding(top = 4.dp, bottom = 4.dp),
        )
    }

    sheetDoc?.let { slug ->
        LegalSheet(slug = slug, onClose = { sheetDoc = null })
    }
}

/** Where anyone can ask to see, correct or erase their data, or complain (the privacy notice says the same). */
const val GRIEVANCE_EMAIL = "hello@wpsimplified.in"

/**
 * "Save my day as PDF": the saved sessions, people to meet, quests and Camp Card for one WordCamp, built on this phone and
 * handed to the share sheet. Returns what to tell the person.
 */
suspend fun saveMyDay(env: Env, slug: String): String {
    val event = (runCatching { env.repository.event(slug) }.getOrNull())?.value ?: return "Couldn't open this WordCamp to make the PDF. Check your connection and try again."
    val quests = runCatching { env.repository.quests(slug).value }.getOrDefault(emptyList())
    val pdf = club.campbuddy.shared.domain.DayExport.build(
        event.name, club.campbuddy.shared.domain.EventClock.zone(event.timezone), env.user.bookmarks(slug), env.user.meetings(slug),
        quests.associate { it.id to it.title }, env.user.completedQuests(slug), env.user.card(),
    )
    if (pdf.sections.isEmpty()) return "Nothing saved for this WordCamp on this phone yet, so there is nothing to put in a PDF."
    val bytes = env.deps.makePdf(pdf)
    if (bytes != null) env.deps.shareFile(club.campbuddy.shared.domain.DayExport.fileName(event.name), "application/pdf", bytes)
    else env.deps.shareFile(club.campbuddy.shared.domain.DayExport.fileName(event.name).removeSuffix(".pdf") + ".txt", "text/plain", club.campbuddy.shared.domain.DayExport.asText(pdf).encodeToByteArray())
    return "Your day is ready. Choose where to save it."
}
