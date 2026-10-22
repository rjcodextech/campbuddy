package club.campbuddy.shared.ui

import androidx.compose.foundation.background
import androidx.compose.foundation.border
import androidx.compose.foundation.clickable
import androidx.compose.foundation.horizontalScroll
import androidx.compose.foundation.layout.Arrangement
import androidx.compose.foundation.layout.Box
import androidx.compose.foundation.layout.BoxScope
import androidx.compose.foundation.layout.Column
import androidx.compose.foundation.layout.ColumnScope
import androidx.compose.foundation.layout.Row
import androidx.compose.foundation.layout.RowScope
import androidx.compose.foundation.layout.fillMaxWidth
import androidx.compose.foundation.layout.heightIn
import androidx.compose.foundation.layout.navigationBarsPadding
import androidx.compose.foundation.layout.padding
import androidx.compose.foundation.layout.size
import androidx.compose.foundation.layout.widthIn
import androidx.compose.foundation.rememberScrollState
import androidx.compose.foundation.shape.CircleShape
import androidx.compose.foundation.shape.RoundedCornerShape
import androidx.compose.foundation.text.KeyboardOptions
import androidx.compose.material3.MaterialTheme
import androidx.compose.material3.OutlinedTextField
import androidx.compose.material3.Switch
import androidx.compose.material3.SwitchDefaults
import androidx.compose.material3.Text
import androidx.compose.runtime.Composable
import androidx.compose.runtime.LaunchedEffect
import androidx.compose.runtime.getValue
import androidx.compose.runtime.mutableStateOf
import androidx.compose.runtime.remember
import androidx.compose.runtime.rememberCoroutineScope
import androidx.compose.runtime.setValue
import androidx.compose.runtime.staticCompositionLocalOf
import androidx.compose.ui.Alignment
import androidx.compose.ui.Modifier
import androidx.compose.ui.draw.clip
import androidx.compose.ui.graphics.Color
import androidx.compose.ui.semantics.Role
import androidx.compose.ui.text.font.FontWeight
import androidx.compose.ui.text.input.KeyboardType
import androidx.compose.ui.text.style.TextOverflow
import androidx.compose.ui.unit.dp
import androidx.compose.ui.unit.sp
import club.campbuddy.shared.data.ApiException
import club.campbuddy.shared.domain.EventClock
import club.campbuddy.shared.model.PageMeta
import kotlinx.coroutines.CancellationException
import kotlinx.coroutines.CoroutineScope
import kotlinx.coroutines.delay
import kotlinx.coroutines.launch
import kotlinx.datetime.TimeZone
import kotlinx.datetime.toLocalDateTime

// The organizer side's own small parts: header, badges, stat tiles, filters, form fields, a confirm sheet, the
// message strip at the bottom, paging. Drawn in the app's colours like the rest; kept apart so the attendee
// screens stay untouched.

/** The one-line message at the bottom of the organizer screens ("Saved.", or the server's reason). */
class OrgToast {
    var text by mutableStateOf<String?>(null)
        private set
    var error by mutableStateOf(false)
        private set
    var stamp by mutableStateOf(0)
        private set

    fun show(message: String?, isError: Boolean = false) {
        if (message.isNullOrBlank()) return
        text = message
        error = isError
        stamp++
    }

    fun hide() { text = null }
}

val LocalOrgToast = staticCompositionLocalOf { OrgToast() }

/** Runs a change on the server one at a time: [busy] while it runs, then the server's sentence (or its error) in the toast. */
class OrgActions(private val scope: CoroutineScope, private val toast: OrgToast) {
    var busy by mutableStateOf(false)
        private set

    fun run(block: suspend () -> String?, onDone: () -> Unit = {}) {
        if (busy) return
        busy = true
        scope.launch {
            try {
                toast.show(block() ?: "Saved.")
                onDone()
            } catch (cancelled: CancellationException) {
                throw cancelled
            } catch (e: ApiException) {
                toast.show(e.message, isError = true)
            } catch (e: Exception) {
                toast.show("Could not reach CampBuddy. Check your connection and try again.", isError = true)
            } finally {
                busy = false
            }
        }
    }
}

@Composable
fun rememberOrgActions(): OrgActions {
    val scope = rememberCoroutineScope()
    val toast = LocalOrgToast.current
    return remember(toast) { OrgActions(scope, toast) }
}

@Composable
fun BoxScope.OrgToastHost(toast: OrgToast) {
    val brand = LocalBrand.current
    val text = toast.text
    LaunchedEffect(toast.stamp) {
        if (toast.text != null) {
            delay(if (toast.error) 6000 else 3500)
            toast.hide()
        }
    }
    if (text == null) return
    val shape = RoundedCornerShape(12.dp)
    Row(
        Modifier.align(Alignment.BottomCenter).navigationBarsPadding().padding(16.dp).widthIn(max = 480.dp).fillMaxWidth()
            .background(if (toast.error) brand.danger else brand.ink, shape)
            .clickable(onClick = toast::hide)
            .padding(horizontal = 16.dp, vertical = 12.dp),
        horizontalArrangement = Arrangement.spacedBy(10.dp),
        verticalAlignment = Alignment.CenterVertically,
    ) {
        LineIcon(if (toast.error) "siren" else "check", Color.White, size = 18.dp)
        Text(text, style = MaterialTheme.typography.bodyMedium.copy(color = Color.White), modifier = Modifier.weight(1f))
    }
}

// ---- header ----------------------------------------------------------------------------------------------

/** A screen's top row: back, title (and a line under it), then up to a few round tools. */
@Composable
fun OrgHeader(title: String, subtitle: String? = null, onBack: (() -> Unit)? = null, trailing: @Composable RowScope.() -> Unit = {}) {
    val brand = LocalBrand.current
    Column(Modifier.fillMaxWidth().background(brand.surface)) {
        Row(
            Modifier.fillMaxWidth().heightIn(min = 60.dp).padding(start = if (onBack != null) 4.dp else 16.dp, end = 8.dp, top = 6.dp, bottom = 6.dp),
            verticalAlignment = Alignment.CenterVertically,
            horizontalArrangement = Arrangement.spacedBy(4.dp),
        ) {
            if (onBack != null) OrgIconButton("chevron-left", "Back", onBack)
            Column(Modifier.weight(1f).padding(start = 4.dp)) {
                Text(title, style = MaterialTheme.typography.titleLarge, maxLines = 1, overflow = TextOverflow.Ellipsis)
                if (!subtitle.isNullOrBlank()) Text(subtitle, style = MaterialTheme.typography.bodySmall, maxLines = 1, overflow = TextOverflow.Ellipsis)
            }
            trailing()
        }
        Box(Modifier.fillMaxWidth().size(height = 1.dp, width = 1.dp).background(brand.line))
    }
}

/** A round tool button, 44 dp to touch. */
@Composable
fun OrgIconButton(icon: String, label: String, onClick: () -> Unit, tint: Color? = null, filled: Boolean = true) {
    val brand = LocalBrand.current
    Box(Modifier.size(44.dp).clip(CircleShape).clickable(role = Role.Button, onClickLabel = label, onClick = onClick), contentAlignment = Alignment.Center) {
        Box(Modifier.size(34.dp).background(if (filled) brand.peach else Color.Transparent, CircleShape), contentAlignment = Alignment.Center) {
            LineIcon(icon, tint ?: brand.ink, size = 18.dp)
        }
    }
}

// ---- badges ----------------------------------------------------------------------------------------------

enum class Tone { Ok, Warn, Danger, Muted, Primary, Accent }

@Composable
private fun toneColor(tone: Tone): Color {
    val brand = LocalBrand.current
    return when (tone) {
        Tone.Ok -> brand.success
        Tone.Warn -> Color(0xFFB45309)
        Tone.Danger -> brand.danger
        Tone.Muted -> brand.muted
        Tone.Primary -> brand.primary
        Tone.Accent -> Color(0xFF8A5A00)
    }
}

@Composable
fun OrgBadge(text: String, tone: Tone = Tone.Muted, icon: String? = null) {
    val color = toneColor(tone)
    Row(
        Modifier.background(color.copy(alpha = 0.11f), RoundedCornerShape(50)).border(1.dp, color.copy(alpha = 0.30f), RoundedCornerShape(50))
            .padding(horizontal = 8.dp, vertical = 3.dp),
        horizontalArrangement = Arrangement.spacedBy(4.dp),
        verticalAlignment = Alignment.CenterVertically,
    ) {
        if (icon != null) LineIcon(icon, color, size = 12.dp)
        Text(text, style = MaterialTheme.typography.labelMedium.copy(color = color, fontSize = 11.5.sp, lineHeight = 14.sp), maxLines = 1)
    }
}

fun statusTone(status: String): Tone = when (status) {
    "active" -> Tone.Ok
    "approved" -> Tone.Primary
    "draft" -> Tone.Warn
    else -> Tone.Muted
}

fun timingTone(state: String?): Tone = when (state) {
    "ongoing" -> Tone.Ok
    "upcoming" -> Tone.Accent
    else -> Tone.Muted
}

fun levelTone(level: String): Tone = when (level) {
    "ok", "sent", "auto" -> Tone.Ok
    "error", "missed" -> Tone.Danger
    "warning", "partial", "waiting" -> Tone.Warn
    "scheduled", "ready" -> Tone.Primary
    else -> Tone.Muted
}

fun statusLabel(status: String): String = status.replace('_', ' ').replaceFirstChar { it.uppercase() }

// ---- stat tiles ------------------------------------------------------------------------------------------

data class Stat(val label: String, val value: String, val icon: String, val tone: Tone = Tone.Primary, val onClick: (() -> Unit)? = null)

/** Numbers in a grid of tiles, [columns] to a row. */
@Composable
fun StatGrid(stats: List<Stat>, columns: Int = 3) {
    Column(verticalArrangement = Arrangement.spacedBy(10.dp)) {
        stats.chunked(columns).forEach { row ->
            Row(Modifier.fillMaxWidth(), horizontalArrangement = Arrangement.spacedBy(10.dp)) {
                row.forEach { StatTile(it, Modifier.weight(1f)) }
                repeat(columns - row.size) { Box(Modifier.weight(1f)) }
            }
        }
    }
}

@Composable
fun StatTile(stat: Stat, modifier: Modifier = Modifier) {
    val brand = LocalBrand.current
    val color = toneColor(stat.tone)
    val shape = RoundedCornerShape(brand.radius.dp)
    Column(
        modifier.background(brand.surface, shape).border(1.dp, brand.line, shape)
            .let { if (stat.onClick != null) it.clickable(role = Role.Button, onClick = stat.onClick) else it }
            .padding(12.dp),
        verticalArrangement = Arrangement.spacedBy(6.dp),
    ) {
        Box(Modifier.size(30.dp).background(color.copy(alpha = 0.12f), CircleShape), contentAlignment = Alignment.Center) { LineIcon(stat.icon, color, size = 16.dp) }
        Text(stat.value, style = MaterialTheme.typography.titleLarge.copy(fontWeight = FontWeight.ExtraBold, fontSize = 22.sp, lineHeight = 26.sp))
        Text(stat.label, style = MaterialTheme.typography.bodySmall.copy(fontSize = 12.sp, lineHeight = 15.sp), maxLines = 2)
    }
}

// ---- section card ----------------------------------------------------------------------------------------

/** A titled card: an icon, a title and a line under it, an optional action on the right, then the content. */
@Composable
fun OrgSection(title: String, icon: String? = null, subtitle: String? = null, action: (@Composable () -> Unit)? = null, content: @Composable ColumnScope.() -> Unit) {
    val brand = LocalBrand.current
    Card {
        Column(verticalArrangement = Arrangement.spacedBy(12.dp)) {
            Row(Modifier.fillMaxWidth(), verticalAlignment = Alignment.CenterVertically, horizontalArrangement = Arrangement.spacedBy(10.dp)) {
                if (icon != null) Box(Modifier.size(34.dp).background(brand.peach, CircleShape), contentAlignment = Alignment.Center) { LineIcon(icon, brand.primary, size = 18.dp) }
                Column(Modifier.weight(1f)) {
                    Text(title, style = MaterialTheme.typography.titleMedium)
                    if (!subtitle.isNullOrBlank()) Text(subtitle, style = MaterialTheme.typography.bodySmall)
                }
                action?.invoke()
            }
            content()
        }
    }
}

@Composable
fun KeyValue(label: String, value: String?) {
    if (value.isNullOrBlank()) return
    Row(Modifier.fillMaxWidth(), horizontalArrangement = Arrangement.spacedBy(12.dp)) {
        Text(label, style = MaterialTheme.typography.bodySmall, modifier = Modifier.widthIn(min = 96.dp, max = 140.dp))
        Text(value, style = MaterialTheme.typography.bodyMedium, modifier = Modifier.weight(1f))
    }
}

@Composable
fun EmptyNote(text: String, icon: String = "sparkles") {
    val brand = LocalBrand.current
    Column(Modifier.fillMaxWidth().padding(vertical = 28.dp, horizontal = 16.dp), horizontalAlignment = Alignment.CenterHorizontally, verticalArrangement = Arrangement.spacedBy(10.dp)) {
        Box(Modifier.size(52.dp).background(brand.peach, CircleShape), contentAlignment = Alignment.Center) { LineIcon(icon, brand.primary, size = 24.dp) }
        Text(text, style = MaterialTheme.typography.bodyMedium.copy(color = brand.muted), textAlign = androidx.compose.ui.text.style.TextAlign.Center)
    }
}

// ---- buttons ---------------------------------------------------------------------------------------------

enum class BtnKind { Primary, Quiet, Danger }

/** The compact button (44 high) the organizer cards use, with an optional icon. */
@Composable
fun OrgButton(text: String, onClick: () -> Unit, modifier: Modifier = Modifier, kind: BtnKind = BtnKind.Quiet, icon: String? = null, enabled: Boolean = true) {
    val brand = LocalBrand.current
    val shape = Radii.input
    val (bg, fg, border) = when (kind) {
        BtnKind.Primary -> Triple(brand.primary, Color.White, brand.primary)
        BtnKind.Danger -> Triple(brand.danger.copy(alpha = 0.08f), brand.danger, brand.danger.copy(alpha = 0.45f))
        BtnKind.Quiet -> Triple(brand.surface, brand.ink, brand.line)
    }
    Row(
        modifier.heightIn(min = 44.dp).clip(shape).background(if (enabled) bg else bg.copy(alpha = 0.45f), shape).border(1.dp, border, shape)
            .clickable(enabled = enabled, role = Role.Button, onClick = onClick)
            .padding(horizontal = 14.dp, vertical = 10.dp),
        horizontalArrangement = Arrangement.spacedBy(6.dp, Alignment.CenterHorizontally),
        verticalAlignment = Alignment.CenterVertically,
    ) {
        if (icon != null) LineIcon(icon, fg, size = 16.dp)
        Text(text, style = MaterialTheme.typography.labelLarge.copy(color = fg), maxLines = 1)
    }
}

// ---- filters ---------------------------------------------------------------------------------------------

/** A text box with the search icon; [onChange] gets every key, the caller decides when to search. */
@Composable
fun OrgSearch(value: String, onChange: (String) -> Unit, placeholder: String, modifier: Modifier = Modifier) {
    val brand = LocalBrand.current
    OutlinedTextField(
        value, onChange, modifier.fillMaxWidth(),
        placeholder = { Text(placeholder) },
        leadingIcon = { LineIcon("search", brand.muted, size = 18.dp) },
        trailingIcon = if (value.isNotEmpty()) ({ Box(Modifier.size(40.dp).clip(CircleShape).clickable(onClickLabel = "Clear") { onChange("") }, contentAlignment = Alignment.Center) { LineIcon("x", brand.muted, size = 16.dp) } }) else null,
        singleLine = true,
        shape = RoundedCornerShape(brand.radius.dp),
    )
}

/** A text the person types, searched for 350 ms after the last key (not on every key). */
@Composable
fun rememberDebounced(value: String, wait: Long = 350): String {
    var settled by remember { mutableStateOf(value) }
    LaunchedEffect(value) {
        delay(wait)
        settled = value
    }
    return settled
}

/** One row of filter chips, scrolling sideways; a null key is "All". */
@Composable
fun <K> OrgFilterRow(label: String?, options: List<Pair<K, String>>, selected: K, onSelect: (K) -> Unit) {
    Column(verticalArrangement = Arrangement.spacedBy(2.dp)) {
        if (label != null) Text(label.uppercase(), style = MaterialTheme.typography.labelMedium.copy(fontSize = 11.sp, letterSpacing = 0.6.sp))
        Row(Modifier.fillMaxWidth().horizontalScroll(rememberScrollState()), horizontalArrangement = Arrangement.spacedBy(8.dp)) {
            options.forEach { (key, text) -> CbChip(selected = key == selected, onClick = { onSelect(key) }, label = { Text(text) }) }
        }
    }
}

// ---- form fields -----------------------------------------------------------------------------------------

@Composable
fun OrgField(
    label: String,
    value: String,
    onChange: (String) -> Unit,
    modifier: Modifier = Modifier,
    placeholder: String? = null,
    help: String? = null,
    lines: Int = 1,
    keyboard: KeyboardType = KeyboardType.Text,
    max: Int? = null,
    password: Boolean = false,
) {
    val brand = LocalBrand.current
    OutlinedTextField(
        value,
        { onChange(if (max != null) it.take(max) else it) },
        modifier.fillMaxWidth(),
        label = { Text(label) },
        placeholder = placeholder?.let { { Text(it) } },
        supportingText = if (help != null || (max != null && lines > 1)) ({
            Row(Modifier.fillMaxWidth()) {
                Text(help.orEmpty(), modifier = Modifier.weight(1f))
                if (max != null && lines > 1) Text("${value.length}/$max")
            }
        }) else null,
        singleLine = lines == 1,
        minLines = lines,
        visualTransformation = if (password) androidx.compose.ui.text.input.PasswordVisualTransformation() else androidx.compose.ui.text.input.VisualTransformation.None,
        keyboardOptions = KeyboardOptions(keyboardType = keyboard),
        shape = RoundedCornerShape(brand.radius.dp),
    )
}

@Composable
fun OrgToggle(title: String, checked: Boolean, onChange: (Boolean) -> Unit, help: String? = null) {
    val brand = LocalBrand.current
    Row(
        Modifier.fillMaxWidth().clip(RoundedCornerShape(8.dp)).clickable(role = Role.Switch) { onChange(!checked) }.padding(vertical = 6.dp),
        verticalAlignment = Alignment.CenterVertically,
        horizontalArrangement = Arrangement.spacedBy(12.dp),
    ) {
        Column(Modifier.weight(1f)) {
            Text(title, style = MaterialTheme.typography.titleMedium.copy(fontSize = 15.sp))
            if (help != null) Text(help, style = MaterialTheme.typography.bodySmall)
        }
        Switch(checked, onChange, colors = SwitchDefaults.colors(checkedTrackColor = brand.primary, checkedThumbColor = Color.White))
    }
}

/** A day field ("2026-10-03"): tap to pick it on a calendar; the cross empties it. */
@OptIn(androidx.compose.material3.ExperimentalMaterial3Api::class)
@Composable
fun OrgDateField(label: String, value: String, onChange: (String) -> Unit, modifier: Modifier = Modifier) {
    val brand = LocalBrand.current
    var pick by remember { mutableStateOf(false) }
    PickerBox(label, value.ifBlank { null }?.let { heroDates(it, null) } ?: "Not set", "calendar", modifier, onClick = { pick = true }, onClear = if (value.isNotBlank()) ({ onChange("") }) else null)
    if (pick) {
        val state = androidx.compose.material3.rememberDatePickerState(initialSelectedDateMillis = value.takeIf { it.length == 10 }?.let { runCatching { kotlinx.datetime.LocalDate.parse(it).toEpochDays() * 86_400_000L }.getOrNull() })
        Sheet(label, onClose = { pick = false }, actions = {
            SheetActions("OK", { state.selectedDateMillis?.let { onChange(kotlinx.datetime.LocalDate.fromEpochDays((it / 86_400_000L).toInt()).toString()) }; pick = false }, "Cancel", { pick = false })
        }) {
            androidx.compose.material3.DatePicker(
                state, title = null, headline = null, showModeToggle = false,
                colors = androidx.compose.material3.DatePickerDefaults.colors(containerColor = Color.Transparent, selectedDayContainerColor = brand.primary, todayDateBorderColor = brand.primary, todayContentColor = brand.primary),
            )
        }
    }
}

/** A time field ("14:30"), picked on a clock. */
@OptIn(androidx.compose.material3.ExperimentalMaterial3Api::class)
@Composable
fun OrgTimeField(label: String, value: String, onChange: (String) -> Unit, modifier: Modifier = Modifier) {
    val brand = LocalBrand.current
    var pick by remember { mutableStateOf(false) }
    PickerBox(label, value.ifBlank { "Not set" }, "clock", modifier, onClick = { pick = true }, onClear = null)
    if (pick) {
        val state = androidx.compose.material3.rememberTimePickerState(initialHour = value.take(2).toIntOrNull() ?: 9, initialMinute = value.drop(3).toIntOrNull() ?: 0, is24Hour = false)
        Sheet(label, onClose = { pick = false }, actions = {
            SheetActions("OK", { onChange(state.hour.toString().padStart(2, '0') + ":" + state.minute.toString().padStart(2, '0')); pick = false }, "Cancel", { pick = false })
        }) {
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
}

@Composable
private fun PickerBox(label: String, shown: String, icon: String, modifier: Modifier, onClick: () -> Unit, onClear: (() -> Unit)?) {
    val brand = LocalBrand.current
    val shape = RoundedCornerShape(brand.radius.dp)
    Row(
        modifier.fillMaxWidth().heightIn(min = 56.dp).clip(shape).border(1.dp, brand.muted.copy(alpha = 0.6f), shape)
            .clickable(role = Role.Button, onClickLabel = label, onClick = onClick).padding(start = 14.dp, end = 6.dp),
        verticalAlignment = Alignment.CenterVertically,
        horizontalArrangement = Arrangement.spacedBy(10.dp),
    ) {
        LineIcon(icon, brand.muted, size = 18.dp)
        Column(Modifier.weight(1f).padding(vertical = 6.dp)) {
            Text(label, style = MaterialTheme.typography.bodySmall.copy(fontSize = 12.sp, lineHeight = 14.sp))
            Text(shown, style = MaterialTheme.typography.bodyLarge)
        }
        if (onClear != null) Box(Modifier.size(40.dp).clip(CircleShape).clickable(onClickLabel = "Clear", onClick = onClear), contentAlignment = Alignment.Center) { LineIcon("x", brand.muted, size = 16.dp) }
    }
}

// ---- confirm ---------------------------------------------------------------------------------------------

/** "Are you sure?" as a sheet. With [typeToConfirm], the button only works once that word is typed (deleting a whole event). */
@Composable
fun ConfirmSheet(title: String, text: String, confirm: String, onConfirm: () -> Unit, onClose: () -> Unit, danger: Boolean = true, typeToConfirm: String? = null) {
    var typed by remember { mutableStateOf("") }
    val brand = LocalBrand.current
    Sheet(title, onClose, actions = {
        Row(Modifier.fillMaxWidth(), horizontalArrangement = Arrangement.spacedBy(12.dp)) {
            OrgButton("Cancel", onClose, Modifier.weight(1f))
            OrgButton(confirm, { onConfirm(); onClose() }, Modifier.weight(1f), kind = if (danger) BtnKind.Danger else BtnKind.Primary, enabled = typeToConfirm == null || typed.trim() == typeToConfirm)
        }
    }) {
        Text(text, style = MaterialTheme.typography.bodyMedium)
        if (typeToConfirm != null) {
            Text("Type  $typeToConfirm  to confirm.", style = MaterialTheme.typography.bodySmall.copy(color = brand.danger))
            OrgField("Confirm", typed, { typed = it })
        }
    }
}

// ---- paging ----------------------------------------------------------------------------------------------

@Composable
fun OrgPager(meta: PageMeta, onPage: (Int) -> Unit) {
    if (meta.lastPage <= 1) return
    Row(Modifier.fillMaxWidth().padding(vertical = 4.dp), verticalAlignment = Alignment.CenterVertically, horizontalArrangement = Arrangement.spacedBy(8.dp)) {
        OrgButton("Previous", { onPage(meta.page - 1) }, enabled = meta.page > 1, icon = "chevron-left")
        Text("Page ${meta.page} of ${meta.lastPage} · ${meta.total}", style = MaterialTheme.typography.bodySmall, modifier = Modifier.weight(1f), textAlign = androidx.compose.ui.text.style.TextAlign.Center)
        OrgButton("Next", { onPage(meta.page + 1) }, enabled = meta.page < meta.lastPage, icon = "chevron-right")
    }
}

// ---- words -----------------------------------------------------------------------------------------------

/** "12 Oct, 4:05 PM" in this phone's time zone, from the server's ISO time. */
fun orgWhen(iso: String?): String? {
    val at = EventClock.instant(iso) ?: return null
    val zone = TimeZone.currentSystemDefault()
    val local = at.toLocalDateTime(zone)
    val month = local.month.name.take(3).lowercase().replaceFirstChar { it.uppercase() }
    return "${local.dayOfMonth} $month ${local.year}, ${EventClock.time(at, zone)}"
}

fun eventDates(starts: String?, ends: String?): String = heroDates(starts, ends) ?: "No dates yet"

/** "1 report", "3 reports". */
fun plural(n: Int, one: String, many: String = one + "s"): String = "$n ${if (n == 1) one else many}"
