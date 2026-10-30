package club.campbuddy.shared.ui

import androidx.compose.foundation.Image
import androidx.compose.foundation.background
import androidx.compose.foundation.border
import androidx.compose.foundation.clickable
import androidx.compose.foundation.layout.Arrangement
import androidx.compose.foundation.layout.Box
import androidx.compose.foundation.layout.Column
import androidx.compose.foundation.layout.ExperimentalLayoutApi
import androidx.compose.foundation.layout.FlowRow
import androidx.compose.foundation.layout.Row
import androidx.compose.foundation.layout.fillMaxWidth
import androidx.compose.foundation.layout.height
import androidx.compose.foundation.layout.heightIn
import androidx.compose.foundation.layout.padding
import androidx.compose.foundation.layout.size
import androidx.compose.foundation.rememberScrollState
import androidx.compose.foundation.shape.CircleShape
import androidx.compose.foundation.shape.RoundedCornerShape
import androidx.compose.foundation.verticalScroll
import androidx.compose.material3.AlertDialog
import androidx.compose.material3.MaterialTheme
import androidx.compose.material3.OutlinedTextField
import androidx.compose.material3.Text
import androidx.compose.runtime.Composable
import androidx.compose.runtime.getValue
import androidx.compose.runtime.mutableStateOf
import androidx.compose.runtime.remember
import androidx.compose.runtime.setValue
import androidx.compose.ui.Alignment
import androidx.compose.ui.Modifier
import androidx.compose.ui.draw.clip
import androidx.compose.ui.graphics.Color
import androidx.compose.ui.semantics.Role
import androidx.compose.ui.text.font.FontWeight
import androidx.compose.ui.text.input.KeyboardType
import androidx.compose.ui.unit.dp
import androidx.compose.ui.unit.sp
import campbuddy_native.shared.generated.resources.Res
import campbuddy_native.shared.generated.resources.art_avatar
import campbuddy_native.shared.generated.resources.art_people
import club.campbuddy.shared.data.Meeting
import club.campbuddy.shared.domain.Ics
import club.campbuddy.shared.domain.IcsItem
import club.campbuddy.shared.model.RosterEntry
import org.jetbrains.compose.resources.painterResource

/** The words the website uses for a person's place on the plan (people-state.js). */
private fun meetLabel(m: Meeting?): String = when (m?.status) {
    "met" -> "✓ Met"
    "missed" -> "Couldn't meet"
    "skipped", null -> "+ Meet"
    else -> "✓ To meet"
}

/** The "+ Meet" pill on an attendee row: opens the note sheet; says where the person is on the plan. */
@Composable
fun MeetButton(person: RosterEntry, slug: String) {
    val env = LocalEnv.current
    val brand = LocalBrand.current
    var open by remember { mutableStateOf(false) }
    val meeting = env.user.meeting(slug, person.id)?.takeIf { it.status != "skipped" }
    val saved = meeting != null
    val pill = RoundedCornerShape(50)

    Text(
        meetLabel(meeting), style = MaterialTheme.typography.labelLarge.copy(color = if (saved) Color.White else brand.primary),
        modifier = Modifier.heightIn(min = 32.dp).background(if (saved) brand.primary else Color.White, pill).border(1.dp, brand.primary, pill)
            .clickable(role = Role.Button, onClickLabel = if (saved) "Edit your note about meeting ${person.name}" else "Plan to meet ${person.name}") { open = true }
            .padding(horizontal = 14.dp, vertical = 5.dp),
    )
    if (open) MeetSheet(slug, person.id, person.name, person.avatarUrl, person.roles.firstOrNull()?.replaceFirstChar { it.uppercase() }.orEmpty(), meeting) { open = false }
}

/** "Add to people to meet": a note, when, and where it goes (this phone only). */
@OptIn(androidx.compose.material3.ExperimentalMaterial3Api::class, ExperimentalLayoutApi::class)
@Composable
fun MeetSheet(slug: String, rosterId: Long, name: String, avatarUrl: String?, sub: String, existing: Meeting?, onClose: () -> Unit) {
    val env = LocalEnv.current
    val brand = LocalBrand.current
    var note by remember { mutableStateOf(existing?.note.orEmpty()) }
    var timed by remember { mutableStateOf(existing?.at != null) }
    var date by remember { mutableStateOf(existing?.at?.substringBefore('T').orEmpty()) }
    var time by remember { mutableStateOf(existing?.at?.substringAfter('T', "")?.take(5).orEmpty()) }
    var error by remember { mutableStateOf<String?>(null) }
    var pickDate by remember { mutableStateOf(false) }
    var pickTime by remember { mutableStateOf(false) }

    fun at(): String? = if (timed) "$date" + "T" + time else null

    fun save() {
        if (timed && Ics.local(date, time) == null) { error = "Pick the day and the time."; return }
        env.user.saveMeeting(slug, Meeting(rosterId, name, avatarUrl, sub, note.trim(), at(), existing?.status?.takeIf { it != "skipped" }))
        onClose()
    }

    if (pickDate) {
        val state = androidx.compose.material3.rememberDatePickerState(initialSelectedDateMillis = date.takeIf { it.length == 10 }?.let { runCatching { kotlinx.datetime.LocalDate.parse(it).toEpochDays() * 86_400_000L }.getOrNull() })
        Sheet(
            "Pick a day", onClose = { pickDate = false },
            actions = {
                SheetActions("OK", { state.selectedDateMillis?.let { date = kotlinx.datetime.LocalDate.fromEpochDays((it / 86_400_000L).toInt()).toString() }; pickDate = false }, "Cancel", { pickDate = false })
            },
        ) {
            androidx.compose.material3.DatePicker(
                state, title = null, headline = null, showModeToggle = false,
                colors = androidx.compose.material3.DatePickerDefaults.colors(containerColor = Color.Transparent, selectedDayContainerColor = brand.primary, todayDateBorderColor = brand.primary, todayContentColor = brand.primary),
            )
        }
    }
    if (pickTime) {
        val state = androidx.compose.material3.rememberTimePickerState(initialHour = time.take(2).toIntOrNull() ?: 10, initialMinute = time.drop(3).toIntOrNull() ?: 0, is24Hour = false)
        Sheet(
            "Pick a time", onClose = { pickTime = false },
            actions = { SheetActions("OK", { time = state.hour.toString().padStart(2, '0') + ":" + state.minute.toString().padStart(2, '0'); pickTime = false }, "Cancel", { pickTime = false }) },
        ) {
            Box(Modifier.fillMaxWidth(), contentAlignment = Alignment.Center) {
                androidx.compose.material3.TimePicker(
                    state,
                    colors = androidx.compose.material3.TimePickerDefaults.colors(
                        clockDialSelectedContentColor = Color.White, clockDialColor = brand.peach, selectorColor = brand.primary,
                        timeSelectorSelectedContainerColor = brand.primary.copy(alpha = 0.14f), timeSelectorSelectedContentColor = brand.primary,
                        periodSelectorSelectedContainerColor = brand.primary.copy(alpha = 0.14f), periodSelectorSelectedContentColor = brand.primary,
                    ),
                )
            }
        }
    }

    Sheet(
        onClose,
        head = {
            Row(horizontalArrangement = Arrangement.spacedBy(12.dp), verticalAlignment = Alignment.CenterVertically) {
                Box(Modifier.size(48.dp).clip(CircleShape).background(brand.peach), contentAlignment = Alignment.Center) {
                    RemoteImage(avatarUrl, Modifier.size(48.dp)) { Image(painterResource(Res.drawable.art_avatar), null, Modifier.size(48.dp)) }
                }
                Column(Modifier.weight(1f)) {
                    Text("ADD TO PEOPLE TO MEET", style = MaterialTheme.typography.labelMedium.copy(color = brand.primaryText, letterSpacing = 0.72.sp))
                    Text(name, style = MaterialTheme.typography.titleLarge.copy(fontSize = 18.sp))
                    if (sub.isNotBlank()) Text(sub, style = MaterialTheme.typography.bodySmall.copy(fontSize = 12.sp, color = brand.muted))
                }
            }
        },
        actions = {
            SheetActions(
                "Save to My schedule", { save() },
                if (existing != null) "Remove" else null, { env.user.removeMeeting(slug, rosterId); onClose() },
            )
        },
    ) {
        run {
            run {
                OutlinedTextField(
                    note, { note = it.take(280) }, label = { Text("Your note") }, placeholder = { Text("Why meet them? e.g. Ask about their WooCommerce plugin") },
                    minLines = 3, modifier = Modifier.fillMaxWidth(),
                )
                Text("When", style = MaterialTheme.typography.titleMedium.copy(fontSize = 14.sp))
                FlowRow(horizontalArrangement = Arrangement.spacedBy(8.dp), verticalArrangement = Arrangement.spacedBy(8.dp)) {
                    ChoicePill("Any time at the event", !timed) { timed = false }
                    ChoicePill("At a set time", timed) { timed = true }
                }
                if (timed) {
                    Row(horizontalArrangement = Arrangement.spacedBy(8.dp), verticalAlignment = Alignment.CenterVertically) {
                        ActionButton(if (date.isBlank()) "Pick a day" else prettyDate(date), filled = false) { pickDate = true }
                        ActionButton(if (time.isBlank()) "Pick a time" else time, filled = false) { pickTime = true }
                    }
                }
                if (timed && Ics.local(date, time) != null) {
                    val start = Ics.local(date, time)!!
                    val end = Ics.local(date, time, 30)!!
                    FlowRow(horizontalArrangement = Arrangement.spacedBy(8.dp), verticalArrangement = Arrangement.spacedBy(8.dp)) {
                        ActionButton("Add to calendar", filled = false) {
                            env.deps.shareFile("meet-$name.ics".replace(' ', '-'), "text/calendar", Ics.build(listOf(IcsItem("meet-$slug-$rosterId", "Meet $name", start, end, note))).encodeToByteArray())
                        }
                        ActionButton("Google Calendar  ↗", filled = false) {
                            env.deps.openUrl("https://calendar.google.com/calendar/render?action=TEMPLATE&text=" + encodeUrl("Meet $name") + "&dates=$start/$end&details=" + encodeUrl(note))
                        }
                    }
                }
                Row(horizontalArrangement = Arrangement.spacedBy(8.dp), verticalAlignment = Alignment.CenterVertically) {
                    LineIcon("lock", brand.muted, size = 14.dp)
                    Text("Saved on this phone only. They aren't told.", style = MaterialTheme.typography.bodySmall)
                }
                error?.let { Text(it, style = MaterialTheme.typography.bodyMedium.copy(color = brand.danger)) }
            }
        }
    }
}

private fun encodeUrl(text: String): String = buildString {
    text.encodeToByteArray().forEach { b ->
        val c = b.toInt().toChar()
        if (c.isLetterOrDigit() && b >= 0 || c in "-_.~") append(c) else append('%').append("0123456789ABCDEF"[(b.toInt() shr 4) and 15]).append("0123456789ABCDEF"[b.toInt() and 15])
    }
}

/** The plan's progress card at the top of My schedule. */
@Composable
fun PlanSummary(done: Int, total: Int, onCalendar: () -> Unit = {}) {
    val brand = LocalBrand.current
    val pct = if (total == 0) 0 else done * 100 / total
    val shape = RoundedCornerShape(brand.radius.dp)
    Column(
        Modifier.fillMaxWidth().background(androidx.compose.ui.graphics.Brush.linearGradient(listOf(Color.White, brand.peach)), shape).border(1.dp, brand.line, shape).padding(16.dp),
        verticalArrangement = Arrangement.spacedBy(10.dp),
    ) {
        Row(Modifier.fillMaxWidth(), horizontalArrangement = Arrangement.SpaceBetween, verticalAlignment = Alignment.CenterVertically) {
            Column {
                Text("Your plan", style = MaterialTheme.typography.titleLarge)
                Text(if (done == total) "All $total done" else "$done of $total done · ${total - done} left", style = MaterialTheme.typography.bodyMedium.copy(color = brand.muted))
            }
            Text("$pct%", style = MaterialTheme.typography.titleLarge.copy(color = brand.primaryText, fontWeight = FontWeight.ExtraBold))
        }
        Box(Modifier.fillMaxWidth().height(8.dp).background(brand.line, RoundedCornerShape(50))) {
            if (done > 0) Box(Modifier.fillMaxWidth(done.toFloat() / total).height(8.dp).background(brand.accent, RoundedCornerShape(50)))
        }
        Row(Modifier.clickable(role = Role.Button, onClick = onCalendar).padding(top = 2.dp), horizontalArrangement = Arrangement.spacedBy(8.dp), verticalAlignment = Alignment.CenterVertically) {
            LineIcon("calendar", brand.primary, size = 16.dp)
            Text("Add all to calendar", style = MaterialTheme.typography.labelLarge.copy(color = brand.primaryText))
        }
    }
}

/** Filter chips for the plan: All / To do / Done / Couldn't meet / Hidden, each with its count. */
@OptIn(ExperimentalLayoutApi::class)
@Composable
fun PlanFilters(counts: Map<String, Int>, selected: String, onSelect: (String) -> Unit) {
    FlowRow(horizontalArrangement = Arrangement.spacedBy(8.dp), verticalArrangement = Arrangement.spacedBy(8.dp)) {
        listOf("all" to "All", "todo" to "To do", "done" to "Done", "missed" to "Couldn't meet", "hidden" to "Hidden").forEach { (key, label) ->
            if (key == "all" || (counts[key] ?: 0) > 0) ChoicePill("$label ${counts[key] ?: 0}", selected == key) { onSelect(key) }
        }
    }
}

/** One person on the plan: who, when, the note, and Met / Couldn't meet / Edit. */
@Composable
fun PersonPlanCard(slug: String, m: Meeting, hidden: Boolean) {
    val env = LocalEnv.current
    val brand = LocalBrand.current
    var edit by remember { mutableStateOf(false) }
    val state = when (m.status) { "met" -> "✓ Met"; "missed" -> "Couldn't meet"; "skipped" -> "Hidden"; else -> "" }

    Card {
        Row(horizontalArrangement = Arrangement.spacedBy(12.dp), verticalAlignment = Alignment.Top) {
            Box(Modifier.size(44.dp).clip(CircleShape).background(brand.peach), contentAlignment = Alignment.Center) {
                RemoteImage(m.avatarUrl, Modifier.size(44.dp)) { Image(painterResource(Res.drawable.art_avatar), null, Modifier.size(44.dp)) }
            }
            Column(Modifier.weight(1f), verticalArrangement = Arrangement.spacedBy(4.dp)) {
                Row(horizontalArrangement = Arrangement.spacedBy(8.dp), verticalAlignment = Alignment.CenterVertically) {
                    Text(m.name, style = MaterialTheme.typography.titleMedium)
                    if (state.isNotBlank()) Text(state, style = MaterialTheme.typography.labelMedium.copy(color = brand.primaryText))
                }
                Text(
                    m.at?.let { "At ${it.substringBefore('T')}, ${it.substringAfter('T').take(5)}" } ?: "Any time at the event",
                    style = MaterialTheme.typography.bodySmall,
                )
                if (m.note.isNotBlank()) Text(m.note, style = MaterialTheme.typography.bodyMedium)
                if (hidden) {
                    ActionButton("Show again", filled = false) { env.user.saveMeeting(slug, m.copy(status = null)) }
                } else {
                    FlowRow2 {
                        StatusButton("✓ Met", m.status == "met", false) { env.user.saveMeeting(slug, m.copy(status = if (m.status == "met") null else "met")) }
                        StatusButton("✗ Couldn't meet", m.status == "missed", true) { env.user.saveMeeting(slug, m.copy(status = if (m.status == "missed") null else "missed")) }
                        Text("Edit · Calendar", style = MaterialTheme.typography.labelLarge.copy(color = brand.primaryText), modifier = Modifier.clickable(role = Role.Button) { edit = true }.padding(vertical = 10.dp, horizontal = 6.dp))
                    }
                }
            }
        }
    }
    if (edit) MeetSheet(slug, m.rosterId, m.name, m.avatarUrl, m.sub, m) { edit = false }
}

@OptIn(ExperimentalLayoutApi::class)
@Composable
private fun FlowRow2(content: @Composable () -> Unit) {
    FlowRow(horizontalArrangement = Arrangement.spacedBy(8.dp), verticalArrangement = Arrangement.spacedBy(4.dp), itemVerticalAlignment = Alignment.CenterVertically) { content() }
}

/** ✓ Attended / ✗ Missed style buttons on a plan item. */
@Composable
fun StatusButton(label: String, on: Boolean, negative: Boolean, onClick: () -> Unit) {
    val brand = LocalBrand.current
    val pill = RoundedCornerShape(50)
    val tint = if (negative) brand.danger else brand.success
    Text(
        label, style = MaterialTheme.typography.labelMedium.copy(color = if (on) Color.White else tint),
        modifier = Modifier.heightIn(min = 32.dp).background(if (on) tint else Color.Transparent, pill).border(1.dp, tint, pill).clickable(role = Role.Button, onClick = onClick).padding(horizontal = 12.dp, vertical = 6.dp),
    )
}

@Composable
fun PlanEmpty(title: String, text: String) {
    val brand = LocalBrand.current
    Column(Modifier.fillMaxWidth().padding(vertical = 16.dp), horizontalAlignment = Alignment.CenterHorizontally, verticalArrangement = Arrangement.spacedBy(8.dp)) {
        Image(painterResource(Res.drawable.art_people), null, Modifier.size(width = 120.dp, height = 90.dp))
        Text(title, style = MaterialTheme.typography.titleMedium)
        Text(text, style = MaterialTheme.typography.bodyMedium.copy(color = brand.muted), textAlign = androidx.compose.ui.text.style.TextAlign.Center)
    }
}

private fun prettyDate(iso: String): String {
    val d = runCatching { kotlinx.datetime.LocalDate.parse(iso) }.getOrNull() ?: return iso
    return d.dayOfWeek.name.take(3).lowercase().replaceFirstChar { it.uppercase() } + ", " + d.month.name.take(3).lowercase().replaceFirstChar { it.uppercase() } + " " + d.dayOfMonth
}

/** Every saved session with a time, and every person with a set time, as one calendar file. */
fun planCalendar(slug: String, sessions: List<club.campbuddy.shared.model.Session>, meetings: List<Meeting>): ByteArray {
    fun utc(instant: kotlinx.datetime.Instant): String = instant.toString().replace("-", "").replace(":", "").substringBefore('.').removeSuffix("Z") + "Z"
    val items = sessions.mapNotNull { s ->
        val start = club.campbuddy.shared.domain.EventClock.startOf(s) ?: return@mapNotNull null
        val end = club.campbuddy.shared.domain.EventClock.endOf(s)?.takeIf { it > start } ?: kotlinx.datetime.Instant.fromEpochSeconds(start.epochSeconds + 30 * 60)
        IcsItem("session-$slug-${s.id}", s.title, utc(start), utc(end), "", s.trackNames.firstOrNull().orEmpty())
    } + meetings.filter { it.status == null && it.at != null }.mapNotNull { m ->
        val date = m.at!!.substringBefore('T'); val time = m.at.substringAfter('T').take(5)
        val start = Ics.local(date, time) ?: return@mapNotNull null
        IcsItem("meet-$slug-${m.rosterId}", "Meet ${m.name}", start, Ics.local(date, time, 30)!!, m.note)
    }

    return Ics.build(items).encodeToByteArray()
}
