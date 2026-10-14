package club.campbuddy.shared.ui

import androidx.compose.foundation.layout.Arrangement
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
import androidx.compose.ui.Modifier
import androidx.compose.ui.unit.dp
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
    val discoverable = env.user.discovery(slug) != null

    Card {
        Column(verticalArrangement = Arrangement.spacedBy(8.dp)) {
            Text("Your data", style = MaterialTheme.typography.titleLarge)
            Text(
                "Your saved sessions, quests, Camp Card and answers live only on this phone. Clearing them cannot be undone.",
                style = MaterialTheme.typography.bodyLarge.copy(color = brand.muted),
            )
            QuietButton(if (saving) "Making your PDF…" else "Save my day as PDF", { if (!saving) scope.launch { saving = true; note = saveMyDay(env, slug); saving = false } }, Modifier.fillMaxWidth())
            QuietButton("Clear my data on this phone", { asking = true }, Modifier.fillMaxWidth())
            note?.let { Text(it, style = MaterialTheme.typography.bodyMedium.copy(color = brand.primary)) }
        }
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
