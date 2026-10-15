package club.campbuddy.shared.ui

import androidx.compose.foundation.background
import androidx.compose.foundation.border
import androidx.compose.foundation.clickable
import androidx.compose.foundation.layout.Arrangement
import androidx.compose.foundation.layout.Column
import androidx.compose.foundation.layout.Row
import androidx.compose.foundation.layout.fillMaxWidth
import androidx.compose.foundation.layout.padding
import androidx.compose.foundation.layout.size
import androidx.compose.foundation.shape.CircleShape
import androidx.compose.foundation.shape.RoundedCornerShape
import androidx.compose.material3.MaterialTheme
import androidx.compose.material3.Text
import androidx.compose.runtime.Composable
import androidx.compose.runtime.LaunchedEffect
import androidx.compose.runtime.getValue
import androidx.compose.runtime.mutableStateOf
import androidx.compose.runtime.remember
import androidx.compose.runtime.setValue
import androidx.compose.ui.Alignment
import androidx.compose.ui.Modifier
import androidx.compose.ui.semantics.Role
import androidx.compose.ui.semantics.selected
import androidx.compose.ui.semantics.semantics
import androidx.compose.ui.text.font.FontWeight
import androidx.compose.ui.unit.dp
import club.campbuddy.shared.data.DataChoice
import club.campbuddy.shared.domain.EventClock
import kotlinx.datetime.Clock
import kotlinx.datetime.DatePeriod
import kotlinx.datetime.LocalDate
import kotlinx.datetime.TimeZone
import kotlinx.datetime.atStartOfDayIn
import kotlinx.datetime.plus

/** Days after a WordCamp's last day until what attendees shared is deleted on our servers (30 kept + 60): the same time limit is offered on the phone. */
const val EVENT_DATA_DAYS = 90

/** The day the data of a finished WordCamp is deleted if the person asks for that: [EVENT_DATA_DAYS] after its last day. */
fun deleteDay(lastDay: LocalDate): LocalDate = lastDay.plus(DatePeriod(days = EVENT_DATA_DAYS))

/** The same moment in epoch milliseconds: the start of the following day at the venue. */
fun deleteAtMillis(lastDay: LocalDate, zone: TimeZone): Long = deleteDay(lastDay).plus(DatePeriod(days = 1)).atStartOfDayIn(zone).toEpochMilliseconds()

private fun prettyDay(date: LocalDate): String = EventClock.dayLabel(date) + " " + date.year

/**
 * Once a WordCamp is over: "keep your data on this phone, or have it deleted after the usual time?". One answer per
 * event, changeable any time in Your data. Closing it asks again the next time the event is opened.
 */
@Composable
fun AfterEventChoice(slug: String, eventName: String, lastDay: LocalDate, zone: TimeZone, onClose: () -> Unit) {
    val env = LocalEnv.current
    var pick by remember { mutableStateOf(env.user.dataChoice(slug)?.keep) }
    val day = prettyDay(deleteDay(lastDay))

    Sheet(
        "$eventName is over", onClose = onClose,
        actions = {
            SheetActions(
                "Confirm",
                { pick?.let { keep -> env.user.setDataChoice(slug, DataChoice(keep, if (keep) 0 else deleteAtMillis(lastDay, zone))) }; onClose() },
                "Decide later", onClose,
                enabled = pick != null,
            )
        },
    ) {
        Text(
            "Your saved sessions, notes on people to meet, quests and discovery details for this WordCamp are only on this phone. What should happen to them?",
            style = MaterialTheme.typography.bodyLarge,
        )
        OptionCard("Keep my data", "It stays on this phone until you clear it yourself.", pick == true) { pick = true }
        OptionCard("Delete it after $EVENT_DATA_DAYS days", "On $day it is removed from this phone automatically. What you shared on our servers is deleted then too.", pick == false) { pick = false }
        Text(
            "Your Camp Card and answers are not tied to one WordCamp and stay until you clear them. Whatever you shared on our servers is deleted about $EVENT_DATA_DAYS days after the event either way.",
            style = MaterialTheme.typography.bodySmall.copy(color = LocalBrand.current.muted),
        )
    }
}

@Composable
private fun OptionCard(title: String, text: String, on: Boolean, onClick: () -> Unit) {
    val brand = LocalBrand.current
    val shape = RoundedCornerShape(brand.radius.dp)
    Row(
        Modifier.fillMaxWidth()
            .background(if (on) brand.peach else brand.surface, shape)
            .border(if (on) 2.dp else 1.dp, if (on) brand.primary else brand.line, shape)
            .clickable(role = Role.RadioButton, onClick = onClick)
            .semantics { this.selected = on }
            .padding(14.dp),
        horizontalArrangement = Arrangement.spacedBy(12.dp), verticalAlignment = Alignment.Top,
    ) {
        Row(Modifier.padding(top = 2.dp).size(20.dp).border(2.dp, if (on) brand.primary else brand.line, CircleShape).padding(4.dp)) {
            if (on) Row(Modifier.fillMaxWidth().size(8.dp).background(brand.primary, CircleShape)) {}
        }
        Column(verticalArrangement = Arrangement.spacedBy(2.dp)) {
            Text(title, style = MaterialTheme.typography.titleMedium.copy(fontWeight = FontWeight.Bold))
            Text(text, style = MaterialTheme.typography.bodyMedium.copy(color = brand.muted))
        }
    }
}

/**
 * Runs once when the app opens: any WordCamp whose data the person asked to have deleted, and whose day has come, is
 * wiped from this phone (a discovery profile is left on the server first, best effort; the server deletes the rest itself).
 */
@Composable
fun DataSweep() {
    val env = LocalEnv.current
    LaunchedEffect(Unit) {
        env.user.dueForDeletion(Clock.System.now().toEpochMilliseconds()).forEach { slug ->
            env.user.discovery(slug)?.let { mine -> runCatching { env.api.leaveDiscovery(slug, mine.discoveryId, mine.ownerToken) } }
            env.user.eraseEvent(slug)
        }
    }
}
