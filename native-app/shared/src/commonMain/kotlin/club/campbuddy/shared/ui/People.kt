package club.campbuddy.shared.ui

import androidx.compose.foundation.background
import androidx.compose.ui.draw.clip
import campbuddy_native.shared.generated.resources.pattern_people
import org.jetbrains.compose.resources.painterResource
import campbuddy_native.shared.generated.resources.art_people
import campbuddy_native.shared.generated.resources.Res
import androidx.compose.foundation.Image
import androidx.compose.foundation.border
import androidx.compose.foundation.layout.Arrangement
import androidx.compose.foundation.layout.Box
import androidx.compose.foundation.layout.Column
import androidx.compose.foundation.layout.ExperimentalLayoutApi
import androidx.compose.foundation.layout.FlowRow
import androidx.compose.foundation.layout.Row
import androidx.compose.foundation.layout.fillMaxWidth
import androidx.compose.foundation.layout.padding
import androidx.compose.foundation.layout.size
import androidx.compose.foundation.lazy.LazyListScope
import androidx.compose.foundation.shape.CircleShape
import androidx.compose.foundation.shape.RoundedCornerShape
import androidx.compose.material3.AlertDialog
import androidx.compose.material3.MaterialTheme
import androidx.compose.material3.OutlinedTextField
import androidx.compose.material3.Text
import androidx.compose.runtime.Composable
import androidx.compose.runtime.LaunchedEffect
import androidx.compose.runtime.Stable
import androidx.compose.runtime.getValue
import androidx.compose.runtime.mutableStateOf
import androidx.compose.runtime.remember
import androidx.compose.runtime.rememberCoroutineScope
import androidx.compose.runtime.setValue
import androidx.compose.ui.Alignment
import androidx.compose.ui.Modifier
import androidx.compose.ui.text.font.FontWeight
import androidx.compose.ui.unit.dp
import androidx.compose.ui.unit.sp
import club.campbuddy.shared.data.ApiException
import club.campbuddy.shared.data.MyDiscovery
import club.campbuddy.shared.domain.Discovery
import club.campbuddy.shared.model.DiscoveryCard
import club.campbuddy.shared.model.DiscoveryForm
import club.campbuddy.shared.model.MessageRequest
import club.campbuddy.shared.model.MutualWave
import club.campbuddy.shared.model.ReportRequest
import club.campbuddy.shared.model.RosterEntry
import club.campbuddy.shared.model.WaveRequest
import club.campbuddy.shared.model.WaveState
import kotlinx.coroutines.CoroutineScope
import kotlinx.coroutines.delay
import kotlinx.coroutines.launch

/**
 * Everything the People tab does with attendee discovery: the public cards, this phone's own profile,
 * waves and the short chats. Calls never throw into the UI: a failure becomes [message].
 */
@Stable
class DiscoveryModel(private val slug: String, private val env: Env, private val scope: CoroutineScope) {
    var cards by mutableStateOf<List<DiscoveryCard>>(emptyList())
        private set
    var waves by mutableStateOf(WaveState())
        private set
    var loaded by mutableStateOf(false)
        private set
    var offline by mutableStateOf(false)
        private set
    var busy by mutableStateOf(false)
        private set
    var message by mutableStateOf<String?>(null)

    val mine: MyDiscovery? get() = env.user.discovery(slug)
    val myCard: DiscoveryCard? get() = mine?.let { m -> cards.firstOrNull { it.discoveryId == m.discoveryId } }
    val hidden: Set<String> get() = env.user.blocked(slug)

    fun refresh() {
        scope.launch {
            runCatching { env.repository.discovery(slug) }.onSuccess { cards = it.value; offline = it.fromCache; loaded = true }.onFailure { loaded = true; offline = true }
            refreshWaves()
        }
    }

    suspend fun refreshWaves() {
        val me = mine ?: return
        try {
            waves = env.api.waves(slug, me.discoveryId, me.ownerToken)
        } catch (e: ApiException) {
            // The profile is gone (it expired or an organizer removed it): forget the secret so the phone can join again.
            if (e.status == 403 || e.status == 404) env.user.forgetDiscovery(slug)
        } catch (_: Exception) {
            // Offline: keep what is shown.
        }
    }

    private fun run(done: String? = null, block: suspend () -> Unit) {
        scope.launch {
            busy = true
            message = null
            try {
                block()
                if (done != null) message = done
            } catch (e: ApiException) {
                message = e.message
            } catch (_: Exception) {
                message = "Could not reach CampBuddy. Check your connection and try again."
            } finally {
                busy = false
            }
        }
    }

    fun join(form: DiscoveryForm, onDone: () -> Unit) = run {
        val existing = mine
        if (existing == null) {
            val joined = env.api.joinDiscovery(slug, form)
            env.user.saveDiscovery(slug, MyDiscovery(joined.discoveryId, joined.ownerToken))
        } else {
            env.api.updateDiscovery(slug, existing.discoveryId, existing.ownerToken, form)
        }
        cards = env.repository.discovery(slug).value
        refreshWaves()
        onDone()
    }

    fun leave() = run("You left attendee discovery.") {
        mine?.let { env.api.leaveDiscovery(slug, it.discoveryId, it.ownerToken) }
        env.user.forgetDiscovery(slug)
        waves = WaveState()
        cards = env.repository.discovery(slug).value
    }

    fun wave(to: String, name: String?, text: String?) = run("Waved. They only see that a match would like to meet.") {
        val me = mine ?: return@run
        waves = env.api.wave(slug, me.discoveryId, me.ownerToken, WaveRequest(to, name?.takeIf { it.isNotBlank() }, text?.takeIf { it.isNotBlank() }))
    }

    fun takeBack(to: String) = run {
        val me = mine ?: return@run
        waves = env.api.takeBackWave(slug, me.discoveryId, me.ownerToken, to)
    }

    fun send(to: String, body: String) = run {
        val me = mine ?: return@run
        waves = env.api.message(slug, me.discoveryId, me.ownerToken, MessageRequest(to, body))
    }

    fun report(id: String, reason: String, note: String?) = run("Thanks. An organizer will take a look.") {
        env.api.reportDiscovery(slug, id, ReportRequest(env.user.deviceId(), reason, note?.takeIf { it.isNotBlank() }))
        env.user.block(slug, id)
    }

    fun hide(id: String) {
        env.user.block(slug, id)
        message = "Hidden on this phone."
    }
}

/** The People tab's top: join (or your profile), waves and chats, and the best matches. */
@OptIn(ExperimentalLayoutApi::class)
fun LazyListScope.discoveryItems(model: DiscoveryModel, roster: List<RosterEntry>) {
    item(key = "disc-top") { DiscoveryTop(model, roster) }
}

@OptIn(ExperimentalLayoutApi::class)
@Composable
private fun DiscoveryTop(model: DiscoveryModel, roster: List<RosterEntry>) {
    val env = LocalEnv.current
    val brand = LocalBrand.current
    var joining by remember { mutableStateOf(false) }
    var waveFor by remember { mutableStateOf<DiscoveryCard?>(null) }
    var chat by remember { mutableStateOf<String?>(null) }
    var reportFor by remember { mutableStateOf<DiscoveryCard?>(null) }
    var confirmLeave by remember { mutableStateOf(false) }
    val slugKey = model.mine?.discoveryId

    LaunchedEffect(Unit) {
        model.refresh()
        while (true) {
            delay(20_000)
            model.refreshWaves()
        }
    }

    val mine = model.mine
    val myTags = model.myCard?.fields?.tags ?: env.user.profile.interests
    val ranked = Discovery.rank(model.cards, myTags, mine?.discoveryId, model.hidden)
    val waves = model.waves
    val byId = model.cards.associateBy { it.discoveryId }

    Column(verticalArrangement = Arrangement.spacedBy(12.dp)) {
        model.message?.let { Text(it, style = MaterialTheme.typography.bodyLarge.copy(color = brand.primary)) }

        if (joining) {
            JoinForm(model, roster, env.user.profile.interests, onClose = { joining = false })
        } else if (mine == null) {
            Card(Modifier.clip(RoundedCornerShape(brand.radius.dp)).patternBackground(Res.drawable.pattern_people, brand.surface)) {
                Column(verticalArrangement = Arrangement.spacedBy(10.dp)) {
                    Image(painterResource(Res.drawable.art_people), contentDescription = null, modifier = Modifier.size(width = 64.dp, height = 48.dp))
                    Text("Find people who match your interests", style = MaterialTheme.typography.titleMedium)
                    Text(
                        "Pick a few interests and see who here shares them. Names and photos help you find each other in the crowd. Leave any time.",
                        style = MaterialTheme.typography.bodyMedium.copy(color = brand.muted),
                    )
                    PrimaryButton("Join attendee discovery", { joining = true }, Modifier.fillMaxWidth().padding(top = 4.dp))
                }
            }
        } else {
            Card {
                Column(verticalArrangement = Arrangement.spacedBy(8.dp)) {
                    Text("You are in attendee discovery", style = MaterialTheme.typography.titleLarge)
                    Text(
                        model.myCard?.name?.let { "Shown as $it." } ?: "Shown without a name. People only see your name if you wave at each other.",
                        style = MaterialTheme.typography.bodyLarge.copy(color = brand.muted),
                    )
                    model.myCard?.let { card -> TagRow(card.fields.tags, emptyList()) }
                    Row(horizontalArrangement = Arrangement.spacedBy(8.dp)) {
                        QuietButton("Edit", { joining = true })
                        QuietButton("Leave", { confirmLeave = true })
                    }
                }
            }

            if (waves.mutual.isNotEmpty() || waves.received.isNotEmpty()) {
                Card {
                    Column(verticalArrangement = Arrangement.spacedBy(8.dp)) {
                        Text("Your waves", style = MaterialTheme.typography.titleLarge)
                        waves.received.forEach { id ->
                            val who = byId[id]
                            Row(Modifier.fillMaxWidth(), horizontalArrangement = Arrangement.spacedBy(12.dp), verticalAlignment = Alignment.CenterVertically) {
                                Column(Modifier.weight(1f)) {
                                    Text("A match waved at you", style = MaterialTheme.typography.titleMedium)
                                    who?.fields?.tags?.takeIf { it.isNotEmpty() }?.let { Text(it.joinToString(" · "), style = MaterialTheme.typography.bodyMedium.copy(color = brand.muted)) }
                                    if (id in waves.receivedWithMessage) Text("They wrote something. Wave back to read it.", style = MaterialTheme.typography.bodyMedium.copy(color = brand.primary))
                                }
                                who?.let { ActionButton("Wave back", filled = true) { waveFor = it } }
                            }
                        }
                        waves.mutual.forEach { m ->
                            Row(Modifier.fillMaxWidth(), horizontalArrangement = Arrangement.spacedBy(12.dp), verticalAlignment = Alignment.CenterVertically) {
                                Column(Modifier.weight(1f)) {
                                    Text(m.name ?: "A match", style = MaterialTheme.typography.titleMedium)
                                    Text(m.messages.lastOrNull()?.body ?: "You both waved. Say hello.", style = MaterialTheme.typography.bodyMedium.copy(color = brand.muted), maxLines = 2)
                                }
                                ActionButton("Chat", filled = true) { chat = m.discoveryId }
                            }
                        }
                    }
                }
            }
        }

        if (mine != null && !joining) {
            Text("People you may want to meet", style = MaterialTheme.typography.titleLarge, modifier = Modifier.padding(top = 8.dp))
            if (!model.loaded) Loading()
            if (model.loaded && ranked.isEmpty()) Text("Nobody else has joined yet. Check back later.", style = MaterialTheme.typography.bodyLarge.copy(color = brand.muted))
            ranked.take(30).forEach { card ->
                MatchCard(
                    card = card, myTags = myTags,
                    waved = card.discoveryId in waves.sent,
                    theyWaved = card.discoveryId in waves.received,
                    mutual = waves.mutual.any { it.discoveryId == card.discoveryId },
                    onWave = { waveFor = card },
                    onTakeBack = { model.takeBack(card.discoveryId) },
                    onChat = { chat = card.discoveryId },
                    onReport = { reportFor = card },
                    onHide = { model.hide(card.discoveryId) },
                )
            }
        }

        Text("Who's attending", style = MaterialTheme.typography.headlineSmall, modifier = Modifier.padding(top = 12.dp))
        Text("From the event's own Attendees page", style = MaterialTheme.typography.bodySmall)
    }

    if (confirmLeave) {
        Sheet(
            "Leave attendee discovery?", onClose = { confirmLeave = false },
            actions = { SheetActions("Leave", { confirmLeave = false; model.leave() }, "Stay", { confirmLeave = false }) },
        ) { Text("Your profile and waves are removed. You can join again any time.", style = MaterialTheme.typography.bodyLarge) }
    }

    waveFor?.let { target ->
        WaveDialog(
            needsName = model.myCard?.name == null,
            onSend = { name, text -> waveFor = null; model.wave(target.discoveryId, name, text) },
            onCancel = { waveFor = null },
        )
    }

    reportFor?.let { target ->
        ReportDialog(
            onSend = { reason, note -> reportFor = null; model.report(target.discoveryId, reason, note) },
            onCancel = { reportFor = null },
        )
    }

    chat?.let { id ->
        val thread = waves.mutual.firstOrNull { it.discoveryId == id }
        if (thread == null) chat = null else ChatDialog(thread, waves.maxMessages, busy = model.busy, error = model.message, onSend = { model.send(id, it) }, onClose = { chat = null })
    }

    // A fresh start for the form when the profile appears or goes.
    LaunchedEffect(slugKey) { if (slugKey != null) joining = false }
}

@OptIn(ExperimentalLayoutApi::class)
@Composable
private fun TagRow(tags: List<String>, highlight: List<String>) {
    val brand = LocalBrand.current
    FlowRow(horizontalArrangement = Arrangement.spacedBy(6.dp), verticalArrangement = Arrangement.spacedBy(6.dp)) {
        tags.forEach { tag ->
            val common = highlight.any { it.equals(tag, ignoreCase = true) }
            Text(
                tag.replaceFirstChar { it.uppercase() },
                style = MaterialTheme.typography.labelMedium.copy(color = if (common) brand.ink else brand.muted, fontWeight = if (common) FontWeight.Bold else FontWeight.Normal),
                modifier = Modifier.background(if (common) brand.peach else brand.background, RoundedCornerShape(50)).border(1.dp, brand.line, RoundedCornerShape(50)).padding(horizontal = 10.dp, vertical = 4.dp),
            )
        }
    }
}

@Composable
private fun MatchCard(
    card: DiscoveryCard, myTags: List<String>, waved: Boolean, theyWaved: Boolean, mutual: Boolean,
    onWave: () -> Unit, onTakeBack: () -> Unit, onChat: () -> Unit, onReport: () -> Unit, onHide: () -> Unit,
) {
    val brand = LocalBrand.current
    val common = Discovery.shared(myTags, card.fields.tags)

    Card {
        Column(verticalArrangement = Arrangement.spacedBy(8.dp)) {
            Row(horizontalArrangement = Arrangement.spacedBy(12.dp), verticalAlignment = Alignment.CenterVertically) {
                Box(Modifier.size(44.dp).background(brand.peach, CircleShape), contentAlignment = Alignment.Center) {
                    Text((card.name ?: "?").take(1).uppercase(), style = MaterialTheme.typography.titleMedium.copy(color = brand.primary))
                }
                Column(Modifier.weight(1f)) {
                    Text(card.name ?: "Someone at this WordCamp", style = MaterialTheme.typography.titleLarge)
                    card.fields.profession?.let { Text(it, style = MaterialTheme.typography.bodyMedium.copy(color = brand.muted)) }
                }
            }
            card.fields.whoToMeet?.let { Text("Looking to meet: $it", style = MaterialTheme.typography.bodyLarge) }
            TagRow(card.fields.tags, common)
            if (common.isNotEmpty()) Text("${common.size} in common", style = MaterialTheme.typography.labelMedium.copy(color = brand.primary, fontWeight = FontWeight.Bold))
            Row(horizontalArrangement = Arrangement.spacedBy(8.dp), verticalAlignment = Alignment.CenterVertically) {
                when {
                    mutual -> ActionButton("Chat", filled = true, onClick = onChat)
                    waved -> { ActionButton("Waved ✓", filled = false) {}; LinkButton("Take back", onTakeBack) }
                    theyWaved -> ActionButton("Wave back", filled = true, onClick = onWave)
                    else -> ActionButton("Wave", filled = true, onClick = onWave)
                }
            }
            Row(horizontalArrangement = Arrangement.spacedBy(4.dp)) {
                LinkButton("Hide", onHide)
                LinkButton("Report", onReport)
            }
        }
    }
}

@Composable
private fun WaveDialog(needsName: Boolean, onSend: (String?, String?) -> Unit, onCancel: () -> Unit) {
    var name by remember { mutableStateOf("") }
    var text by remember { mutableStateOf("") }
    Sheet("Send a wave", onClose = onCancel, actions = { SheetActions("Wave", { onSend(name, text) }, "Cancel", onCancel) }) {
        Text("They only see that a match would like to meet. If they wave back, you both see each other's name.", style = MaterialTheme.typography.bodyLarge)
        if (needsName) OutlinedTextField(name, { name = it.take(60) }, label = { Text("Your first name") }, singleLine = true, modifier = Modifier.fillMaxWidth())
        OutlinedTextField(text, { text = it.take(140) }, label = { Text("A first message (optional)") }, modifier = Modifier.fillMaxWidth())
    }
}

@Composable
private fun ReportDialog(onSend: (String, String?) -> Unit, onCancel: () -> Unit) {
    var reason by remember { mutableStateOf("spam") }
    var note by remember { mutableStateOf("") }
    Sheet("Report this profile", onClose = onCancel, actions = { SheetActions("Report", { onSend(reason, note) }, "Cancel", onCancel) }) {
        Text("An organizer will review it. The profile is hidden on this phone from now on.", style = MaterialTheme.typography.bodyLarge)
        Discovery.REPORT_REASONS.forEach { (key, label) -> ChoicePill(label, selected = reason == key) { reason = key } }
        OutlinedTextField(note, { note = it.take(300) }, label = { Text("Anything to add? (optional)") }, modifier = Modifier.fillMaxWidth())
    }
}

@Composable
private fun ChatDialog(thread: MutualWave, max: Int, busy: Boolean, error: String?, onSend: (String) -> Unit, onClose: () -> Unit) {
    val brand = LocalBrand.current
    var body by remember(thread.messages.size) { mutableStateOf("") }
    Sheet(
        thread.name ?: "Chat", onClose = onClose,
        actions = {
            if (thread.canSend) SheetActions(if (busy) "Sending…" else "Send", { if (body.isNotBlank() && !busy) onSend(body) }, "Close", onClose)
            else SheetActions("Close", onClose)
        },
    ) {
        if (thread.messages.isEmpty()) Text("You both waved. Agree where to meet.", style = MaterialTheme.typography.bodyMedium.copy(color = brand.muted))
        thread.messages.forEach { m ->
            Text(
                (if (m.mine) "You: " else "${thread.name ?: "They"}: ") + m.body,
                style = MaterialTheme.typography.bodyLarge.copy(fontWeight = if (m.mine) FontWeight.Normal else FontWeight.SemiBold),
            )
        }
        Discovery.whyNot(thread.reason, max)?.let { Text(it, style = MaterialTheme.typography.bodyMedium.copy(color = brand.muted)) }
        if (thread.canSend) OutlinedTextField(body, { body = it.take(140) }, label = { Text("Message") }, modifier = Modifier.fillMaxWidth())
        error?.let { Text(it, style = MaterialTheme.typography.bodyMedium.copy(color = brand.danger)) }
    }
}

/** Join, or edit, the discovery profile: who is shown, which interests (up to five) and a line or two. */
@OptIn(ExperimentalLayoutApi::class)
@Composable
private fun JoinForm(model: DiscoveryModel, roster: List<RosterEntry>, startTags: List<String>, onClose: () -> Unit) {
    val brand = LocalBrand.current
    val env = LocalEnv.current
    val profile = env.user.profile
    val mineCard = model.myCard
    var how by remember { mutableStateOf(if (mineCard?.rosterId != null) "list" else if (mineCard?.name != null) "typed" else "anon") }
    var typed by remember { mutableStateOf(if (mineCard?.onAttendeeList == true) "" else mineCard?.name.orEmpty()) }
    var pick by remember { mutableStateOf(mineCard?.rosterId) }
    var search by remember { mutableStateOf("") }
    var tags by remember { mutableStateOf((mineCard?.fields?.tags ?: startTags).filter { t -> Discovery.TAGS.any { it.equals(t, true) } }.map { it.lowercase() }.take(Discovery.MAX_TAGS)) }
    var profession by remember { mutableStateOf(mineCard?.fields?.profession ?: profile.profession.orEmpty()) }
    var toMeet by remember { mutableStateOf(mineCard?.fields?.whoToMeet ?: profile.whoToMeet.orEmpty()) }
    var wporg by remember { mutableStateOf(profile.wporg.orEmpty()) }

    Card {
        Column(verticalArrangement = Arrangement.spacedBy(10.dp)) {
            Text(if (model.mine == null) "Join attendee discovery" else "Edit your profile", style = MaterialTheme.typography.titleLarge)
            Text("Only what you fill in here is shown, and only to other people in discovery.", style = MaterialTheme.typography.bodyLarge.copy(color = brand.muted))

            Text("How should you appear?", style = MaterialTheme.typography.titleMedium)
            FlowRow(horizontalArrangement = Arrangement.spacedBy(8.dp), verticalArrangement = Arrangement.spacedBy(8.dp)) {
                ChoicePill("Pick my name", how == "list") { how = "list" }
                ChoicePill("Type my name", how == "typed") { how = "typed" }
                ChoicePill("Stay anonymous", how == "anon") { how = "anon" }
            }
            if (how == "list") {
                SearchField(search, "Search the attendee list…") { search = it }
                val shown = if (search.isBlank()) roster.take(0) else roster.filter { it.name.contains(search, ignoreCase = true) }.take(6)
                shown.forEach { entry -> ChoicePill(entry.name, pick == entry.id) { pick = entry.id } }
                if (pick != null && search.isBlank()) Text("Picked: " + (roster.firstOrNull { it.id == pick }?.name ?: "your name"), style = MaterialTheme.typography.bodyMedium.copy(color = brand.muted))
            }
            if (how == "typed") OutlinedTextField(typed, { typed = it.take(60) }, label = { Text("Your name") }, singleLine = true, modifier = Modifier.fillMaxWidth())

            Text("What describes you? (pick 1 to ${Discovery.MAX_TAGS})", style = MaterialTheme.typography.titleMedium)
            FlowRow(horizontalArrangement = Arrangement.spacedBy(8.dp), verticalArrangement = Arrangement.spacedBy(8.dp)) {
                Discovery.TAGS.forEach { tag ->
                    ChoicePill(tag.replaceFirstChar { it.uppercase() }, tag in tags) {
                        tags = if (tag in tags) tags - tag else if (tags.size < Discovery.MAX_TAGS) tags + tag else tags
                    }
                }
            }
            OutlinedTextField(profession, { profession = it.take(100) }, label = { Text("Your profession") }, singleLine = true, modifier = Modifier.fillMaxWidth())
            OutlinedTextField(toMeet, { toMeet = it.take(255) }, label = { Text("Looking to meet") }, singleLine = true, modifier = Modifier.fillMaxWidth())
            OutlinedTextField(wporg, { wporg = it.take(120) }, label = { Text("WordPress.org username (optional)") }, singleLine = true, modifier = Modifier.fillMaxWidth())

            val ready = tags.isNotEmpty() && (how != "typed" || typed.trim().length >= 2) && (how != "list" || pick != null)
            Row(horizontalArrangement = Arrangement.spacedBy(8.dp)) {
                PrimaryButton(if (model.busy) "Saving…" else "Save", {
                    if (ready && !model.busy) model.join(
                        DiscoveryForm(
                            tags = tags, profession = profession.trim().ifEmpty { null }, whoToMeet = toMeet.trim().ifEmpty { null },
                            displayName = if (how == "typed") typed.trim() else null,
                            wporgUsername = wporg.trim().ifEmpty { null },
                            attendeeRosterId = if (how == "list") pick else null,
                        ),
                        onDone = onClose,
                    )
                }, enabled = ready)
                QuietButton("Cancel", onClose)
            }
        }
    }
}
