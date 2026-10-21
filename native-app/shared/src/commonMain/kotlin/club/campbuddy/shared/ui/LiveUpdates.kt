package club.campbuddy.shared.ui

import androidx.compose.foundation.background
import androidx.compose.foundation.border
import androidx.compose.foundation.clickable
import androidx.compose.foundation.layout.Arrangement
import androidx.compose.foundation.layout.Box
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
import androidx.compose.runtime.rememberCoroutineScope
import androidx.compose.runtime.setValue
import androidx.compose.ui.Alignment
import androidx.compose.ui.Modifier
import androidx.compose.ui.draw.clip
import androidx.compose.ui.graphics.Color
import androidx.compose.ui.semantics.Role
import androidx.compose.ui.semantics.contentDescription
import androidx.compose.ui.semantics.semantics
import androidx.compose.ui.unit.dp
import club.campbuddy.shared.data.NotificationState
import club.campbuddy.shared.model.LiveNotice
import kotlinx.coroutines.delay
import kotlinx.coroutines.launch
import kotlinx.datetime.Clock
import kotlinx.datetime.DatePeriod
import kotlinx.datetime.LocalDate
import kotlinx.datetime.TimeZone
import kotlinx.datetime.minus
import kotlinx.datetime.todayIn

// Live announcements from the people running the WordCamp (App\Support\EventBroadcasts on the server):
// this phone says it has the event open (so an announcement reaches it), the announcement sent in the last
// half hour shows as a banner, and from three days before the event a card asks to turn notifications on.

private const val SEEN_EVERY_MS = 6 * 60 * 60 * 1000L
private const val CHECK_EVERY_MS = 4 * 60 * 1000L

/** Tells the server this phone has the event open, at most every six hours. */
@Composable
internal fun MarkSeen(slug: String) {
    val env = LocalEnv.current
    LaunchedEffect(slug) {
        val key = "live:seen:$slug"
        val now = Clock.System.now().toEpochMilliseconds()
        val last = env.deps.store.get(key)?.toLongOrNull() ?: 0L
        if (now - last < SEEN_EVERY_MS) return@LaunchedEffect
        runCatching { env.api.markSeen(slug, env.user.deviceId(), env.deps.platform) }
            .onSuccess { env.deps.store.put(key, now.toString()) }
    }
}

/** The organizers' latest announcement, while it is fresh; closing it hides that one for good. */
@Composable
internal fun LiveNoticeBanner(slug: String, onTab: (String) -> Unit) {
    val env = LocalEnv.current
    val brand = LocalBrand.current
    var notice by remember(slug) { mutableStateOf<LiveNotice?>(null) }
    var closed by remember(slug) { mutableStateOf(env.deps.store.get("live:closed:$slug")) }
    LaunchedEffect(slug) {
        while (true) {
            runCatching { env.api.liveNotice(slug) }.onSuccess { notice = it }
            delay(CHECK_EVERY_MS)
        }
    }
    val n = notice ?: return
    if (closed == n.id.toString()) return
    val shape = RoundedCornerShape(brand.radius.dp)

    Row(
        Modifier.fillMaxWidth().padding(start = 16.dp, end = 16.dp, top = 12.dp, bottom = 8.dp)
            .background(brand.accent.copy(alpha = 0.16f), shape).border(1.dp, brand.accent.copy(alpha = 0.6f), shape)
            .clip(shape).clickable(enabled = n.tab != null, role = Role.Button) { n.tab?.let(onTab) }
            .padding(start = 14.dp, top = 12.dp, bottom = 12.dp, end = 4.dp),
        horizontalArrangement = Arrangement.spacedBy(12.dp),
        verticalAlignment = Alignment.Top,
    ) {
        Box(Modifier.size(36.dp).background(brand.primary, CircleShape), contentAlignment = Alignment.Center) { LineIcon("megaphone", Color.White, size = 18.dp) }
        Column(Modifier.weight(1f), verticalArrangement = Arrangement.spacedBy(2.dp)) {
            Text("FROM THE ORGANIZERS", style = MaterialTheme.typography.labelMedium.copy(color = brand.primary))
            Text(n.title, style = MaterialTheme.typography.titleMedium)
            Text(n.body, style = MaterialTheme.typography.bodyMedium)
        }
        Box(Modifier.size(40.dp).clip(CircleShape).clickable(role = Role.Button, onClickLabel = "Close this announcement") {
            env.deps.store.put("live:closed:$slug", n.id.toString())
            closed = n.id.toString()
        }.semantics { contentDescription = "Close this announcement" }, contentAlignment = Alignment.Center) { LineIcon("x", brand.muted, size = 18.dp) }
    }
}

/** Whether today (at the venue) is from three days before the first day to the last day. */
internal fun inAskWindow(firstDay: String?, lastDay: String?, zone: TimeZone): Boolean {
    val start = firstDay?.let { runCatching { LocalDate.parse(it) }.getOrNull() } ?: return false
    val end = lastDay?.let { runCatching { LocalDate.parse(it) }.getOrNull() } ?: start
    val today = Clock.System.todayIn(zone)
    return today >= start.minus(DatePeriod(days = 3)) && today <= end
}

/** "Get live updates from the organizers": once, from three days before the event, while notifications are off. */
@Composable
internal fun LiveAskCard(slug: String, firstDay: String?, lastDay: String?, zone: TimeZone) {
    val env = LocalEnv.current
    val brand = LocalBrand.current
    val scope = rememberCoroutineScope()
    val notes = env.user.notifications
    var dismissed by remember(slug) { mutableStateOf(env.deps.store.get("live:ask:$slug") != null) }
    if (dismissed || notes.enabled || notes.denied || !inAskWindow(firstDay, lastDay, zone)) return

    Card(Modifier.padding(bottom = 16.dp)) {
        Column(verticalArrangement = Arrangement.spacedBy(8.dp)) {
            Row(horizontalArrangement = Arrangement.spacedBy(10.dp), verticalAlignment = Alignment.CenterVertically) {
                LineIcon("bell", brand.primary, size = 22.dp)
                Text("Get live updates from the organizers", style = MaterialTheme.typography.titleMedium)
            }
            Text("“Opening remarks are starting”, “Lunch is served”, a session about to begin: a notification on this phone, even with CampBuddy closed.", style = MaterialTheme.typography.bodyMedium)
            Row(Modifier.fillMaxWidth(), horizontalArrangement = Arrangement.spacedBy(8.dp)) {
                OrgButton("Turn on", {
                    env.deps.store.put("live:ask:$slug", "asked")
                    dismissed = true
                    scope.launch {
                        val granted = env.deps.reminders.requestPermission()
                        env.user.saveNotifications(if (granted) NotificationState(enabled = true) else NotificationState(denied = true))
                    }
                }, Modifier.weight(1f), kind = BtnKind.Primary, icon = "bell")
                OrgButton("Not now", { env.deps.store.put("live:ask:$slug", "no"); dismissed = true }, Modifier.weight(1f))
            }
        }
    }
}
