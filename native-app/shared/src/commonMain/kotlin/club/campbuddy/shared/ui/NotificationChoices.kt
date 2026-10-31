package club.campbuddy.shared.ui

import androidx.compose.foundation.layout.Arrangement
import androidx.compose.foundation.layout.Column
import androidx.compose.foundation.layout.Row
import androidx.compose.foundation.layout.fillMaxWidth
import androidx.compose.foundation.layout.padding
import androidx.compose.material3.MaterialTheme
import androidx.compose.material3.Text
import androidx.compose.runtime.Composable
import androidx.compose.runtime.LaunchedEffect
import androidx.compose.runtime.getValue
import androidx.compose.runtime.mutableLongStateOf
import androidx.compose.runtime.mutableStateOf
import androidx.compose.runtime.produceState
import androidx.compose.runtime.remember
import androidx.compose.runtime.rememberCoroutineScope
import androidx.compose.runtime.setValue
import androidx.compose.ui.Alignment
import androidx.compose.ui.Modifier
import androidx.compose.ui.text.font.FontWeight
import androidx.compose.ui.unit.dp
import club.campbuddy.shared.data.NotificationState
import club.campbuddy.shared.domain.NotificationChoices
import kotlinx.coroutines.delay
import kotlinx.coroutines.launch
import kotlinx.datetime.Clock
import kotlinx.datetime.LocalDate
import kotlinx.datetime.TimeZone
import kotlinx.datetime.todayIn

// "Notifications are off" (Home and My Day) and "Your choices" (next to "Your data"): what a no to notifications
// turns off and the way back on (domain/NotificationChoices; the website's notification-choices.js).

/** The moment of a no, this session (My Day's reminder sheet, the live-updates card): the notice shows at once, even if hidden today. */
internal object TurnedDown {
    var at by mutableLongStateOf(0L)
    fun now() { at = Clock.System.now().toEpochMilliseconds() }
}

/** Whether the phone allows notifications; checked every second while [waiting] for the person to come back from the settings. */
@Composable
private fun rememberPermitted(waiting: Boolean): Boolean? {
    val env = LocalEnv.current
    val permitted by produceState<Boolean?>(null, waiting, env.user.notifications) {
        value = env.deps.reminders.hasPermission()
        if (waiting) {
            repeat(180) {
                delay(1000)
                value = env.deps.reminders.hasPermission()
                if (value == true) return@produceState
            }
        }
    }
    return permitted
}

private enum class TurnOn { On, Blocked, Settings }

/** "Turn on": allowed already → on; said no to the phone before → its settings; else the phone's question. */
private suspend fun turnOn(env: Env, slug: String): TurnOn {
    val reminders = env.deps.reminders
    if (!reminders.hasPermission()) {
        if (env.user.notifications.denied) {
            reminders.openSettings()
            return TurnOn.Settings
        }
        if (!reminders.requestPermission()) {
            env.user.saveNotifications(NotificationState(denied = true))
            return TurnOn.Blocked
        }
    }
    switchedOn(env, slug)
    return TurnOn.On
}

private fun switchedOn(env: Env, slug: String) {
    env.user.saveNotifications(NotificationState(enabled = true))
    env.deps.store.put("live:ask:$slug", "asked")
}

@Composable
private fun Bullet(text: String) {
    val brand = LocalBrand.current
    Row(Modifier.fillMaxWidth().padding(start = 2.dp), horizontalArrangement = Arrangement.spacedBy(8.dp), verticalAlignment = Alignment.Top) {
        Text("•", style = MaterialTheme.typography.bodyMedium.copy(color = brand.primaryText, fontWeight = FontWeight.Bold))
        Text(text, style = MaterialTheme.typography.bodyMedium)
    }
}

/**
 * "Notifications are off": after a no, on Home and My Day, until the event is over; "Not now" hides it for a day.
 * On Home ([home]) it waits while the live-updates card is still asking, so only one of them asks.
 */
@Composable
internal fun NotificationsOffNotice(slug: String, firstDay: String?, lastDay: String?, zone: TimeZone, home: Boolean = false) {
    val env = LocalEnv.current
    val brand = LocalBrand.current
    val scope = rememberCoroutineScope()
    var waiting by remember(slug) { mutableStateOf(false) }
    var blocked by remember(slug) { mutableStateOf(false) }
    var turnedOn by remember(slug) { mutableStateOf(false) }
    var hiddenAt by remember(slug) { mutableStateOf(env.deps.store.get("notif:off:hidden:$slug")?.toLongOrNull()) }
    val permitted = rememberPermitted(waiting) ?: return

    // Back from the phone's settings with notifications allowed: on, without another tap.
    LaunchedEffect(waiting, permitted) {
        if (waiting && permitted) {
            switchedOn(env, slug)
            waiting = false
            turnedOn = true
        }
    }
    if (turnedOn) {
        LaunchedEffect(Unit) { delay(5000); turnedOn = false }
        Card(Modifier.padding(bottom = 16.dp)) { Text(NotificationChoices.TURNED_ON, style = MaterialTheme.typography.bodyLarge) }
        return
    }

    val status = NotificationChoices.status(env.user.notifications, permitted, env.deps.store.get("live:ask:$slug"))
    val now = Clock.System.now().toEpochMilliseconds()
    val last = lastDay?.let { runCatching { LocalDate.parse(it) }.getOrNull() }
    val justNow = TurnedDown.at > 0 && TurnedDown.at > (hiddenAt ?: 0L)
    val notes = env.user.notifications
    val askCardShowing = home && env.deps.store.get("live:ask:$slug") == null && !notes.enabled && !notes.denied && inAskWindow(firstDay, lastDay, zone)
    if (askCardShowing || !NotificationChoices.showOffNotice(status, Clock.System.todayIn(zone), last, hiddenAt, now, justNow)) return
    val toSettings = blocked || status.blocked

    Card(Modifier.padding(bottom = 16.dp)) {
        Column(verticalArrangement = Arrangement.spacedBy(8.dp)) {
            Row(horizontalArrangement = Arrangement.spacedBy(10.dp), verticalAlignment = Alignment.CenterVertically) {
                LineIcon("bell", brand.primary, size = 22.dp)
                Text("Notifications are off", style = MaterialTheme.typography.titleMedium)
            }
            if (toSettings) {
                Text(NotificationChoices.BLOCKED, style = MaterialTheme.typography.bodyMedium)
            } else {
                Text("So you won't get:", style = MaterialTheme.typography.bodyMedium.copy(color = brand.muted))
                NotificationChoices.OFF_ITEMS.forEach { Bullet(it) }
                Text(NotificationChoices.STILL_WORKS, style = MaterialTheme.typography.bodyMedium.copy(color = brand.muted))
            }
            Row(Modifier.fillMaxWidth(), horizontalArrangement = Arrangement.spacedBy(8.dp)) {
                OrgButton(if (toSettings) "Open phone settings" else "Turn on notifications", {
                    scope.launch {
                        if (toSettings) {
                            env.deps.reminders.openSettings()
                            waiting = true
                            return@launch
                        }
                        when (turnOn(env, slug)) {
                            TurnOn.On -> turnedOn = true
                            TurnOn.Blocked -> blocked = true
                            TurnOn.Settings -> waiting = true
                        }
                    }
                }, Modifier.weight(1f), kind = BtnKind.Primary, icon = "bell")
                OrgButton("Not now", {
                    env.deps.store.put("notif:off:hidden:$slug", now.toString())
                    hiddenAt = now
                }, Modifier.weight(1f))
            }
        }
    }
}

/** "Your choices": where talk reminders and the organizers' updates stand, and "Turn on all" while they are off. Next to "Your data". */
@Composable
fun NotificationChoicesCard(slug: String) {
    val env = LocalEnv.current
    val brand = LocalBrand.current
    val scope = rememberCoroutineScope()
    var waiting by remember(slug) { mutableStateOf(false) }
    var blocked by remember(slug) { mutableStateOf(false) }
    val permitted = rememberPermitted(waiting) ?: return
    LaunchedEffect(waiting, permitted) {
        if (waiting && permitted) {
            switchedOn(env, slug)
            waiting = false
        }
    }
    val status = NotificationChoices.status(env.user.notifications, permitted, env.deps.store.get("live:ask:$slug")).let { if (blocked && !it.on) it.copy(blocked = true) else it }
    val label = NotificationChoices.label(status)

    Card {
        Column(verticalArrangement = Arrangement.spacedBy(8.dp)) {
            Text("Your choices", style = MaterialTheme.typography.titleLarge)
            listOf("Talk reminders", "Live updates from the organizers").forEach { row ->
                Row(Modifier.fillMaxWidth(), horizontalArrangement = Arrangement.SpaceBetween, verticalAlignment = Alignment.CenterVertically) {
                    Text(row, style = MaterialTheme.typography.bodyLarge, modifier = Modifier.weight(1f))
                    Text(label, style = MaterialTheme.typography.bodyLarge.copy(fontWeight = FontWeight.Bold))
                }
            }
            if (status.blocked) Text(NotificationChoices.BLOCKED, style = MaterialTheme.typography.bodyMedium.copy(color = brand.muted))
            if (!status.on) {
                OrgButton(if (status.blocked) "Open phone settings" else "Turn on all", {
                    scope.launch {
                        if (status.blocked) {
                            env.deps.reminders.openSettings()
                            waiting = true
                            return@launch
                        }
                        when (turnOn(env, slug)) {
                            TurnOn.On -> Unit
                            TurnOn.Blocked -> blocked = true
                            TurnOn.Settings -> waiting = true
                        }
                    }
                }, Modifier.fillMaxWidth(), kind = BtnKind.Primary, icon = "bell")
            }
            Text("This app has no analytics, so there is nothing else to allow.", style = MaterialTheme.typography.bodyMedium.copy(color = brand.muted))
        }
    }
}
