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
@OptIn(ExperimentalLayoutApi::class)
@Composable
fun MeetSheet(slug: String, rosterId: Long, name: String, avatarUrl: String?, sub: String, existing: Meeting?, onClose: () -> Unit) {
    val env = LocalEnv.current
    val brand = LocalBrand.current
    var note by remember { mutableStateOf(existing?.note.orEmpty()) }
    var timed by remember { mutableStateOf(existing?.at != null) }
    var date by remember { mutableStateOf(existing?.at?.substringBefore('T').orEmpty()) }
    var time by remember { mutableStateOf(existing?.at?.substringAfter('T', "")?.take(5).orEmpty()) }
    var error by remember { mutableStateOf<String?>(null) }

    fun at(): String? = if (timed) "$date" + "T" + time else null

    fun save() {
        if (timed && !(Regex("\\d{4}-\\d{2}-\\d{2}").matches(date) && Regex("\\d{2}:\\d{2}").matches(time))) { error = "Use a date like 2026-10-03 and a time like 14:30."; return }
        env.user.saveMeeting(slug, Meeting(rosterId, name, avatarUrl, sub, note.trim(), at(), existing?.status?.takeIf { it != "skipped" }))
        onClose()
    }

    AlertDialog(
        onDismissRequest = onClose,
        title = {
            Row(horizontalArrangement = Arrangement.spacedBy(12.dp), verticalAlignment = Alignment.CenterVertically) {
                Box(Modifier.size(48.dp).clip(CircleShape).background(brand.peach), contentAlignment = Alignment.Center) {
                    RemoteImage(avatarUrl, Modifier.size(48.dp)) { Image(painterResource(Res.drawable.art_avatar), null, Modifier.size(48.dp)) }
                }
                Column {
                    Text("ADD TO PEOPLE TO MEET", style = MaterialTheme.typography.labelMedium.copy(color = brand.primary, letterSpacing = 0.72.sp))
                    Text(name, style = MaterialTheme.typography.titleLarge)
                    if (sub.isNotBlank()) Text(sub, style = MaterialTheme.typography.bodySmall)
                }
            }
        },
        text = {
            Column(Modifier.verticalScroll(rememberScrollState()), verticalArrangement = Arrangement.spacedBy(10.dp)) {
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
                    Row(horizontalArrangement = Arrangement.spacedBy(8.dp)) {
                        OutlinedTextField(date, { date = it.take(10) }, label = { Text("Date") }, placeholder = { Text("2026-10-03") }, singleLine = true, modifier = Modifier.weight(1f), keyboardOptions = androidx.compose.foundation.text.KeyboardOptions(keyboardType = KeyboardType.Number))
                        OutlinedTextField(time, { time = it.take(5) }, label = { Text("Time") }, placeholder = { Text("14:30") }, singleLine = true, modifier = Modifier.weight(1f))
                    }
                    if (Regex("\\d{4}-\\d{2}-\\d{2}").matches(date) && Regex("\\d{2}:\\d{2}").matches(time)) {
                        val start = date.replace("-", "") + "T" + time.replace(":", "") + "00"
                        ActionButton("Google Calendar  ↗", filled = false) {
                            env.deps.openUrl("https://calendar.google.com/calendar/render?action=TEMPLATE&text=" + encodeUrl("Meet $name") + "&dates=$start/$start&details=" + encodeUrl(note))
                        }
                    }
                }
                Row(horizontalArrangement = Arrangement.spacedBy(8.dp), verticalAlignment = Alignment.CenterVertically) {
                    LineIcon("lock", brand.muted, size = 14.dp)
                    Text("Saved on this phone only. They aren't told.", style = MaterialTheme.typography.bodySmall)
                }
                error?.let { Text(it, style = MaterialTheme.typography.bodyMedium.copy(color = brand.danger)) }
            }
        },
        confirmButton = { LinkButton("Save to My schedule", { save() }) },
        dismissButton = {
            Row {
                if (existing != null) LinkButton("Remove", { env.user.removeMeeting(slug, rosterId); onClose() })
                LinkButton("Close", onClose)
            }
        },
    )
}

private fun encodeUrl(text: String): String = buildString {
    text.encodeToByteArray().forEach { b ->
        val c = b.toInt().toChar()
        if (c.isLetterOrDigit() && b >= 0 || c in "-_.~") append(c) else append('%').append("0123456789ABCDEF"[(b.toInt() shr 4) and 15]).append("0123456789ABCDEF"[b.toInt() and 15])
    }
}

/** The plan's progress card at the top of My schedule. */
@Composable
fun PlanSummary(done: Int, total: Int) {
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
            Text("$pct%", style = MaterialTheme.typography.titleLarge.copy(color = brand.primary, fontWeight = FontWeight.ExtraBold))
        }
        Box(Modifier.fillMaxWidth().height(8.dp).background(brand.line, RoundedCornerShape(50))) {
            if (done > 0) Box(Modifier.fillMaxWidth(done.toFloat() / total).height(8.dp).background(brand.accent, RoundedCornerShape(50)))
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
                    if (state.isNotBlank()) Text(state, style = MaterialTheme.typography.labelMedium.copy(color = brand.primary))
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
                        Text("Edit · Calendar", style = MaterialTheme.typography.labelLarge.copy(color = brand.primary), modifier = Modifier.clickable(role = Role.Button) { edit = true }.padding(vertical = 10.dp, horizontal = 6.dp))
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
