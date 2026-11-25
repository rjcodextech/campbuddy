package club.campbuddy.shared.ui

import androidx.compose.foundation.background
import androidx.compose.ui.draw.clip
import campbuddy_native.shared.generated.resources.pattern_people
import org.jetbrains.compose.resources.painterResource
import campbuddy_native.shared.generated.resources.art_people
import campbuddy_native.shared.generated.resources.Res
import androidx.compose.foundation.Image
import androidx.compose.foundation.border
import androidx.compose.foundation.clickable
import androidx.compose.foundation.layout.Arrangement
import androidx.compose.foundation.layout.Box
import androidx.compose.foundation.layout.Column
import androidx.compose.foundation.layout.ExperimentalLayoutApi
import androidx.compose.foundation.layout.FlowRow
import androidx.compose.foundation.layout.Row
import androidx.compose.foundation.layout.Spacer
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
import androidx.compose.ui.semantics.Role
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
fun LazyListScope.discoveryItems(model: DiscoveryModel, roster: List<RosterEntry>, onShareCampCard: () -> Unit = {}) {
    item(key = "disc-top") { DiscoveryTop(model, roster, onShareCampCard) }
}

@OptIn(ExperimentalLayoutApi::class)
@Composable
private fun DiscoveryTop(model: DiscoveryModel, roster: List<RosterEntry>, onShareCampCard: () -> Unit) {
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
    val chatOpen = waves.chat?.open != false

    Column(verticalArrangement = Arrangement.spacedBy(12.dp)) {
        model.message?.let { Text(it, style = MaterialTheme.typography.bodyLarge.copy(color = brand.primaryText)) }

        if (joining) {
            JoinForm(model, roster, env.user.profile.interests, onClose = { joining = false })
        } else if (mine == null) {
            Row(
                Modifier
                    .fillMaxWidth()
                    .clip(RoundedCornerShape(18.dp))
                    .background(androidx.compose.ui.graphics.Color(0xFF0F172A))
                    .border(1.dp, androidx.compose.ui.graphics.Color(0xFF334155), RoundedCornerShape(18.dp))
                    .padding(16.dp),
                horizontalArrangement = Arrangement.spacedBy(12.dp),
                verticalAlignment = Alignment.CenterVertically,
            ) {
                Column(Modifier.weight(1f), verticalArrangement = Arrangement.spacedBy(4.dp)) {
                    Text(
                        "Meet People Sharing Interests",
                        style = MaterialTheme.typography.titleMedium.copy(color = androidx.compose.ui.graphics.Color.White, fontWeight = FontWeight.Bold),
                    )
                    Text(
                        "Create a private card, wave at matches & unlock 1-to-1 chat.",
                        style = MaterialTheme.typography.bodySmall.copy(color = androidx.compose.ui.graphics.Color(0xFF94A3B8)),
                    )
                }
                Box(
                    Modifier
                        .clip(RoundedCornerShape(50))
                        .background(brand.primary)
                        .clickable(role = Role.Button) { joining = true }
                        .padding(horizontal = 18.dp, vertical = 10.dp),
                    contentAlignment = Alignment.Center,
                ) {
                    Text("Join", style = MaterialTheme.typography.labelLarge.copy(color = androidx.compose.ui.graphics.Color.White, fontWeight = FontWeight.Bold))
                }
            }
        } else {
            Card {
                Column(verticalArrangement = Arrangement.spacedBy(8.dp)) {
                    Row(Modifier.fillMaxWidth(), horizontalArrangement = Arrangement.SpaceBetween, verticalAlignment = Alignment.CenterVertically) {
                        Text("You are in attendee discovery", style = MaterialTheme.typography.titleLarge)
                        Box(
                            Modifier
                                .clip(RoundedCornerShape(50))
                                .background(androidx.compose.ui.graphics.Color(0xFFECFDF5))
                                .padding(horizontal = 8.dp, vertical = 3.dp),
                        ) {
                            Text("Active", style = MaterialTheme.typography.labelSmall.copy(color = androidx.compose.ui.graphics.Color(0xFF059669), fontWeight = FontWeight.Bold))
                        }
                    }
                    Text(
                        model.myCard?.name?.let { "Shown as $it." } ?: "Shown without a name. People only see your name if you wave at each other.",
                        style = MaterialTheme.typography.bodyMedium.copy(color = brand.muted),
                    )
                    model.myCard?.let { card -> TagRow(card.fields.tags, emptyList()) }
                    Row(horizontalArrangement = Arrangement.spacedBy(8.dp)) {
                        QuietButton("Edit", { joining = true })
                        QuietButton("Leave", { confirmLeave = true })
                    }
                }
            }

            // Waves Inbox & Mutual Match Chat Hub (M51)
            val sentPending = waves.sent.filter { id -> waves.mutual.none { it.discoveryId == id } }
            if (waves.received.isNotEmpty() || waves.mutual.isNotEmpty() || sentPending.isNotEmpty()) {
                Card {
                    Column(verticalArrangement = Arrangement.spacedBy(10.dp)) {
                        Row(Modifier.fillMaxWidth(), horizontalArrangement = Arrangement.SpaceBetween, verticalAlignment = Alignment.CenterVertically) {
                            Text("Waves & Chats", style = MaterialTheme.typography.titleLarge)
                            Text(
                                "${waves.mutual.size} mutual · ${waves.received.size} waiting",
                                style = MaterialTheme.typography.labelSmall.copy(color = brand.primaryText, fontWeight = FontWeight.Bold),
                            )
                        }

                        waves.received.forEach { id ->
                            val who = byId[id]
                            Column(
                                Modifier
                                    .fillMaxWidth()
                                    .clip(RoundedCornerShape(14.dp))
                                    .background(androidx.compose.ui.graphics.Color.White)
                                    .border(1.5.dp, brand.primary, RoundedCornerShape(14.dp))
                                    .padding(12.dp),
                                verticalArrangement = Arrangement.spacedBy(8.dp),
                            ) {
                                Box(
                                    Modifier.clip(RoundedCornerShape(50)).background(brand.peach).padding(horizontal = 8.dp, vertical = 2.dp),
                                ) {
                                    Text("INCOMING WAVE", style = MaterialTheme.typography.labelSmall.copy(color = brand.primaryText, fontWeight = FontWeight.ExtraBold))
                                }
                                Text("A match wants to meet you", style = MaterialTheme.typography.titleMedium.copy(fontWeight = FontWeight.Bold))
                                if (id in waves.receivedWithMessage) {
                                    Text("They wrote a note! Wave back to swap names and read their message.", style = MaterialTheme.typography.bodySmall.copy(color = brand.muted))
                                }
                                who?.fields?.tags?.takeIf { it.isNotEmpty() }?.let {
                                    Text(it.joinToString(" · "), style = MaterialTheme.typography.bodySmall.copy(color = brand.muted))
                                }
                                who?.let { ActionButton("Wave back", filled = true) { waveFor = it } }
                            }
                        }

                        waves.mutual.forEach { m ->
                            Row(
                                Modifier
                                    .fillMaxWidth()
                                    .clip(RoundedCornerShape(14.dp))
                                    .background(androidx.compose.ui.graphics.Color(0xFFECFDF5))
                                    .border(1.dp, androidx.compose.ui.graphics.Color(0xFF86EFAC), RoundedCornerShape(14.dp))
                                    .clickable { chat = m.discoveryId }
                                    .padding(12.dp),
                                horizontalArrangement = Arrangement.spacedBy(12.dp),
                                verticalAlignment = Alignment.CenterVertically,
                            ) {
                                Box(Modifier.size(40.dp)) {
                                    Box(Modifier.size(40.dp).background(androidx.compose.ui.graphics.Color.White, CircleShape).border(1.5.dp, brand.success, CircleShape), contentAlignment = Alignment.Center) {
                                        Text((m.name ?: "M").take(1).uppercase(), style = MaterialTheme.typography.titleMedium.copy(color = androidx.compose.ui.graphics.Color(0xFF047857), fontWeight = FontWeight.Bold))
                                    }
                                    Box(Modifier.size(11.dp).background(brand.success, CircleShape).border(2.dp, androidx.compose.ui.graphics.Color.White, CircleShape).align(Alignment.BottomEnd))
                                }
                                Column(Modifier.weight(1f)) {
                                    Text(m.name ?: "Mutual Match", style = MaterialTheme.typography.titleMedium.copy(fontWeight = FontWeight.Bold))
                                    Text(
                                        m.messages.lastOrNull()?.body ?: "You both waved · Tap Chat to say hello",
                                        style = MaterialTheme.typography.bodySmall.copy(color = androidx.compose.ui.graphics.Color(0xFF047857)),
                                        maxLines = 2,
                                    )
                                }
                                Box(
                                    Modifier.clip(RoundedCornerShape(50)).background(brand.ink).clickable { chat = m.discoveryId }.padding(horizontal = 14.dp, vertical = 8.dp),
                                    contentAlignment = Alignment.Center,
                                ) {
                                    Text("Chat →", style = MaterialTheme.typography.labelMedium.copy(color = androidx.compose.ui.graphics.Color.White, fontWeight = FontWeight.Bold))
                                }
                            }
                        }

                        sentPending.forEach { id ->
                            val who = byId[id]
                            Row(
                                Modifier
                                    .fillMaxWidth()
                                    .clip(RoundedCornerShape(12.dp))
                                    .background(brand.background)
                                    .border(1.dp, brand.line, RoundedCornerShape(12.dp))
                                    .padding(horizontal = 12.dp, vertical = 10.dp),
                                horizontalArrangement = Arrangement.spacedBy(10.dp),
                                verticalAlignment = Alignment.CenterVertically,
                            ) {
                                Column(Modifier.weight(1f)) {
                                    Text(who?.name ?: "Wave sent", style = MaterialTheme.typography.titleSmall.copy(fontWeight = FontWeight.Bold))
                                    Text("Waved ✓ · Waiting for wave back", style = MaterialTheme.typography.bodySmall.copy(color = brand.muted))
                                }
                                LinkButton("Take back", { model.takeBack(id) })
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
            targetName = target.name,
            needsName = model.myCard?.name == null,
            chatOpen = chatOpen,
            closedLabel = waves.chat?.opensLabel,
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
        val card = byId[id]
        if (thread == null) chat = null else ChatDialog(
            thread = thread,
            sharedTags = Discovery.shared(myTags, card?.fields?.tags.orEmpty()),
            chatStatus = waves.chat,
            max = waves.maxMessages,
            busy = model.busy,
            error = model.message,
            onSend = { model.send(id, it) },
            onShareCampCard = { chat = null; onShareCampCard() },
            onReport = { chat = null; card?.let { reportFor = it } },
            onClose = { chat = null },
        )
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
        Column(verticalArrangement = Arrangement.spacedBy(10.dp)) {
            Row(horizontalArrangement = Arrangement.spacedBy(12.dp), verticalAlignment = Alignment.CenterVertically) {
                Box(Modifier.size(46.dp)) {
                    Box(Modifier.size(46.dp).background(brand.peach, CircleShape).border(1.dp, brand.line, CircleShape), contentAlignment = Alignment.Center) {
                        Text((card.name ?: "?").take(1).uppercase(), style = MaterialTheme.typography.titleMedium.copy(color = brand.primaryText, fontWeight = FontWeight.Bold))
                    }
                    Box(Modifier.size(11.dp).background(brand.success, CircleShape).border(2.dp, androidx.compose.ui.graphics.Color.White, CircleShape).align(Alignment.BottomEnd))
                }
                Column(Modifier.weight(1f)) {
                    Row(Modifier.fillMaxWidth(), horizontalArrangement = Arrangement.SpaceBetween, verticalAlignment = Alignment.CenterVertically) {
                        Text(card.name ?: "Someone at this WordCamp", style = MaterialTheme.typography.titleLarge, modifier = Modifier.weight(1f))
                        if (common.isNotEmpty()) {
                            Box(
                                Modifier.clip(RoundedCornerShape(50)).background(androidx.compose.ui.graphics.Color(0xFFECFDF5)).padding(horizontal = 8.dp, vertical = 3.dp),
                            ) {
                                Text("${common.size} shared", style = MaterialTheme.typography.labelSmall.copy(color = androidx.compose.ui.graphics.Color(0xFF059669), fontWeight = FontWeight.Bold))
                            }
                        }
                    }
                    card.fields.profession?.let { Text(it, style = MaterialTheme.typography.bodyMedium.copy(color = brand.muted)) }
                }
            }
            card.fields.whoToMeet?.let { Text("Looking to meet: $it", style = MaterialTheme.typography.bodyMedium) }
            TagRow(card.fields.tags, common)
            Row(horizontalArrangement = Arrangement.spacedBy(8.dp), verticalAlignment = Alignment.CenterVertically) {
                when {
                    mutual -> Box(
                        Modifier.clip(RoundedCornerShape(50)).background(brand.ink).clickable(role = Role.Button, onClick = onChat).padding(horizontal = 16.dp, vertical = 9.dp),
                    ) { Text("Chat →", style = MaterialTheme.typography.labelLarge.copy(color = androidx.compose.ui.graphics.Color.White, fontWeight = FontWeight.Bold)) }
                    waved -> { ActionButton("Waved ✓", filled = false) {}; LinkButton("Take back", onTakeBack) }
                    theyWaved -> ActionButton("Wave back", filled = true, onClick = onWave)
                    else -> ActionButton("Wave Hello", filled = true, onClick = onWave)
                }
                Spacer(Modifier.weight(1f))
                LinkButton("Hide", onHide)
                LinkButton("Report", onReport)
            }
        }
    }
}

@Composable
private fun WaveDialog(
    targetName: String?,
    needsName: Boolean,
    chatOpen: Boolean,
    closedLabel: String?,
    onSend: (String?, String?) -> Unit,
    onCancel: () -> Unit,
) {
    val brand = LocalBrand.current
    var name by remember { mutableStateOf("") }
    var text by remember { mutableStateOf("") }
    Sheet(
        onClose = onCancel,
        head = {
            Row(horizontalArrangement = Arrangement.spacedBy(12.dp), verticalAlignment = Alignment.CenterVertically) {
                Box(Modifier.size(44.dp).background(brand.peach, CircleShape), contentAlignment = Alignment.Center) {
                    LineIcon("hand", brand.primary, size = 22.dp)
                }
                Column {
                    Text("WAVE AT THIS MATCH", style = MaterialTheme.typography.labelSmall.copy(color = brand.primaryText, fontWeight = FontWeight.ExtraBold))
                    Text(targetName ?: "Wave hello", style = MaterialTheme.typography.titleLarge)
                }
            }
        },
        actions = { SheetActions("Send wave", { onSend(name, text) }, "Cancel", onCancel) },
    ) {
        Text("If they wave back, you both see each other's name and messages. If not, nothing is shared.", style = MaterialTheme.typography.bodyMedium.copy(color = brand.muted))
        if (needsName) OutlinedTextField(name, { name = it.take(60) }, label = { Text("Your first name (revealed on match)") }, singleLine = true, modifier = Modifier.fillMaxWidth(), shape = RoundedCornerShape(12.dp))
        if (chatOpen) {
            OutlinedTextField(text, { text = it.take(140) }, label = { Text("Optional intro message (1 of 3)") }, placeholder = { Text("e.g. Coffee after the keynote?") }, modifier = Modifier.fillMaxWidth(), shape = RoundedCornerShape(12.dp))
            Text("You each get 3 short messages to agree where to meet. Then swap Camp Cards.", style = MaterialTheme.typography.bodySmall.copy(color = brand.muted))
        } else {
            Text(
                closedLabel?.let { "Wave now, and chat will open $it (event time)." } ?: "Chat opens during WordCamp hours, 1 hour before the first talk.",
                style = MaterialTheme.typography.bodySmall.copy(color = brand.muted),
            )
        }
    }
}

@Composable
private fun ReportDialog(onSend: (String, String?) -> Unit, onCancel: () -> Unit) {
    val brand = LocalBrand.current
    var reason by remember { mutableStateOf("spam") }
    var note by remember { mutableStateOf("") }
    Sheet(
        onClose = onCancel,
        head = {
            Row(horizontalArrangement = Arrangement.spacedBy(12.dp), verticalAlignment = Alignment.CenterVertically) {
                Box(Modifier.size(44.dp).background(androidx.compose.ui.graphics.Color(0xFFFEE2E2), CircleShape), contentAlignment = Alignment.Center) {
                    LineIcon("shield", brand.danger, size = 20.dp)
                }
                Column {
                    Text("REPORT & BLOCK PROFILE", style = MaterialTheme.typography.labelSmall.copy(color = brand.danger, fontWeight = FontWeight.ExtraBold))
                    Text("Hide this attendee", style = MaterialTheme.typography.titleLarge)
                }
            }
        },
        actions = { SheetActions("Report & Block", { onSend(reason, note) }, "Cancel", onCancel) },
    ) {
        Text("They disappear from your matches and waves right away. Organizers review every report within 24 hours.", style = MaterialTheme.typography.bodyMedium.copy(color = brand.muted))
        Discovery.REPORT_REASONS.forEach { (key, label) -> ChoicePill(label, selected = reason == key) { reason = key } }
        OutlinedTextField(note, { note = it.take(300) }, label = { Text("Anything we should know? (optional)") }, modifier = Modifier.fillMaxWidth(), shape = RoundedCornerShape(12.dp))
    }
}

@Composable
private fun ChatDialog(
    thread: MutualWave,
    sharedTags: List<String>,
    chatStatus: club.campbuddy.shared.model.ChatStatus?,
    max: Int,
    busy: Boolean,
    error: String?,
    onSend: (String) -> Unit,
    onShareCampCard: () -> Unit,
    onReport: () -> Unit,
    onClose: () -> Unit,
) {
    val brand = LocalBrand.current
    var body by remember(thread.messages.size) { mutableStateOf("") }
    val multiDay = thread.messages.mapNotNull { it.day }.distinct().size > 1
    var sentByMe = 0
    var sentByThem = 0

    Sheet(
        onClose = onClose,
        head = {
            Row(horizontalArrangement = Arrangement.spacedBy(10.dp), verticalAlignment = Alignment.CenterVertically) {
                Box(Modifier.size(40.dp)) {
                    Box(Modifier.size(40.dp).background(brand.peach, CircleShape).border(1.5.dp, brand.success, CircleShape), contentAlignment = Alignment.Center) {
                        Text((thread.name ?: "M").take(1).uppercase(), style = MaterialTheme.typography.titleMedium.copy(color = brand.primaryText, fontWeight = FontWeight.Bold))
                    }
                    Box(Modifier.size(11.dp).background(brand.success, CircleShape).border(2.dp, androidx.compose.ui.graphics.Color.White, CircleShape).align(Alignment.BottomEnd))
                }
                Column(Modifier.weight(1f)) {
                    Text(thread.name ?: "Mutual Match", style = MaterialTheme.typography.titleLarge)
                    Text(
                        if (sharedTags.isNotEmpty()) "Mutual match · ${sharedTags.take(2).joinToString(" & ")}" else "Mutual match · Discovery chat",
                        style = MaterialTheme.typography.bodySmall.copy(color = androidx.compose.ui.graphics.Color(0xFF059669), fontWeight = FontWeight.SemiBold),
                    )
                }
            }
        },
        actions = {
            if (thread.canSend) SheetActions(if (busy) "Sending…" else "Send message", { if (body.isNotBlank() && !busy) onSend(body) }, "Close", onClose)
            else SheetActions("Share Camp Card →", onShareCampCard, "Close", onClose)
        },
    ) {
        // Event Time Window Pill (M52 / M53)
        val windowBg = if (chatStatus?.open != false) androidx.compose.ui.graphics.Color(0xFFECFDF5) else androidx.compose.ui.graphics.Color(0xFF0F172A)
        val windowBorder = if (chatStatus?.open != false) androidx.compose.ui.graphics.Color(0xFFA7F3D0) else androidx.compose.ui.graphics.Color(0xFF334155)
        val windowTint = if (chatStatus?.open != false) androidx.compose.ui.graphics.Color(0xFF047857) else androidx.compose.ui.graphics.Color.White
        val windowText = when {
            chatStatus == null -> "Mutual match · $max short messages each today"
            chatStatus.open && chatStatus.closesLabel != null ->
                "Chat open until ${chatStatus.closesLabel}" + (if (chatStatus.days > 1 && chatStatus.dayNumber != null) " · Day ${chatStatus.dayNumber} of ${chatStatus.days}" else "") + " · $max msgs/day"
            chatStatus.ended -> "WordCamp ended · Share Camp Cards to stay in touch"
            chatStatus.opensLabel != null -> "Chat closed · Opens ${chatStatus.opensLabel} (event time)"
            else -> "Chat opens during WordCamp hours"
        }
        Row(
            Modifier.fillMaxWidth().background(windowBg, RoundedCornerShape(12.dp)).border(1.dp, windowBorder, RoundedCornerShape(12.dp)).padding(horizontal = 12.dp, vertical = 8.dp),
            horizontalArrangement = Arrangement.spacedBy(8.dp),
            verticalAlignment = Alignment.CenterVertically,
        ) {
            LineIcon("clock", if (chatStatus?.open != false) androidx.compose.ui.graphics.Color(0xFF059669) else androidx.compose.ui.graphics.Color(0xFFF59E0B), size = 14.dp)
            Text(windowText, style = MaterialTheme.typography.labelMedium.copy(color = windowTint, fontWeight = FontWeight.Bold))
        }

        if (thread.messages.isEmpty()) {
            Text("Both of you waved! Take turns and keep it short to agree where to meet.", style = MaterialTheme.typography.bodySmall.copy(color = brand.muted))
        }

        var lastDay: String? = null
        thread.messages.forEach { m ->
            if (multiDay && !m.day.isNullOrBlank() && m.day != lastDay) {
                lastDay = m.day
                Box(Modifier.fillMaxWidth().padding(vertical = 2.dp), contentAlignment = Alignment.Center) {
                    Text(m.day, style = MaterialTheme.typography.labelSmall.copy(color = brand.muted), modifier = Modifier.background(androidx.compose.ui.graphics.Color.White, RoundedCornerShape(50)).border(1.dp, brand.line, RoundedCornerShape(50)).padding(horizontal = 10.dp, vertical = 3.dp))
                }
            }
            val msgNum = if (m.mine) { sentByMe++; sentByMe } else { sentByThem++; sentByThem }
            Row(Modifier.fillMaxWidth(), horizontalArrangement = if (m.mine) Arrangement.End else Arrangement.Start) {
                Column(
                    Modifier
                        .clip(RoundedCornerShape(16.dp))
                        .background(if (m.mine) brand.primary else androidx.compose.ui.graphics.Color.White)
                        .border(if (m.mine) 0.dp else 1.dp, if (m.mine) androidx.compose.ui.graphics.Color.Transparent else brand.line, RoundedCornerShape(16.dp))
                        .padding(horizontal = 14.dp, vertical = 10.dp),
                    verticalArrangement = Arrangement.spacedBy(3.dp),
                ) {
                    Text(
                        m.body,
                        style = MaterialTheme.typography.bodyMedium.copy(color = if (m.mine) androidx.compose.ui.graphics.Color.White else brand.ink),
                    )
                    Text(
                        if (m.mine) "Message $msgNum of $max · sent ✓" else "Their message $msgNum of $max",
                        style = MaterialTheme.typography.labelSmall.copy(color = if (m.mine) androidx.compose.ui.graphics.Color(0xFFFFE4DE) else brand.muted, fontSize = 10.sp),
                    )
                }
            }
        }

        // Turn-taking or Daily Limit Callout (M52 / M53)
        if (thread.reason == "waiting") {
            Column(
                Modifier.fillMaxWidth().clip(RoundedCornerShape(14.dp)).background(androidx.compose.ui.graphics.Color(0xFFFEF3C7)).border(1.dp, androidx.compose.ui.graphics.Color(0xFFF59E0B), RoundedCornerShape(14.dp)).padding(12.dp),
                verticalArrangement = Arrangement.spacedBy(4.dp),
            ) {
                Text("Waiting for their reply", style = MaterialTheme.typography.labelLarge.copy(color = androidx.compose.ui.graphics.Color(0xFF92400E), fontWeight = FontWeight.Bold))
                Text("Take turns to keep it relaxed — you have sent ${thread.mine} of $max messages today.", style = MaterialTheme.typography.bodySmall.copy(color = androidx.compose.ui.graphics.Color(0xFF92400E)))
            }
        } else if (thread.reason == "limit") {
            Column(
                Modifier.fillMaxWidth().clip(RoundedCornerShape(14.dp)).background(androidx.compose.ui.graphics.Color(0xFFFFF7ED)).border(1.dp, brand.primary, RoundedCornerShape(14.dp)).padding(12.dp),
                verticalArrangement = Arrangement.spacedBy(4.dp),
            ) {
                Text("All $max of today's messages sent", style = MaterialTheme.typography.labelLarge.copy(color = brand.primaryText, fontWeight = FontWeight.Bold))
                Text("Swap your Camp Card below to continue offline or stay connected after WordCamp.", style = MaterialTheme.typography.bodySmall.copy(color = brand.muted))
            }
        } else {
            Discovery.whyNot(thread.reason, max)?.let { Text(it, style = MaterialTheme.typography.bodyMedium.copy(color = brand.muted)) }
        }

        if (thread.canSend) {
            OutlinedTextField(
                value = body,
                onValueChange = { body = it.take(140) },
                label = { Text("Message ${(thread.mine + 1).coerceAtMost(max)} of $max today...") },
                modifier = Modifier.fillMaxWidth(),
                shape = RoundedCornerShape(14.dp),
            )
        }

        // Quick CampCard & Report footer
        Row(Modifier.fillMaxWidth(), horizontalArrangement = Arrangement.SpaceBetween, verticalAlignment = Alignment.CenterVertically) {
            if (thread.canSend) {
                LinkButton("Share Camp Card →", onShareCampCard)
            } else {
                Box(Modifier.weight(1f))
            }
            LinkButton("Report & Block", onReport)
        }
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
    var toMeetEntries by remember {
        val initial = mineCard?.fields?.whoToMeet ?: profile.whoToMeet.orEmpty()
        mutableStateOf(
            if (initial.isBlank()) emptyList()
            else initial.split(",").map { it.trim() }.filter { it.isNotBlank() }
        )
    }
    var wporg by remember { mutableStateOf(profile.wporg.orEmpty()) }
    val joining = model.mine == null
    var agreed by remember { mutableStateOf(false) }

    Card {
        Column(verticalArrangement = Arrangement.spacedBy(10.dp)) {
            Text(if (model.mine == null) "Join attendee discovery" else "Edit your profile", style = MaterialTheme.typography.titleLarge)
            Text("Only what you fill in here is shown, and only to other people in discovery. Joining is your choice: leave any time, and it is deleted about 90 days after the event.", style = MaterialTheme.typography.bodyLarge.copy(color = brand.muted))

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
            WhoToMeetField(
                selectedEntries = toMeetEntries,
                onEntriesChanged = { toMeetEntries = it }
            )
            OutlinedTextField(wporg, { wporg = it.take(120) }, label = { Text("WordPress.org username (optional)") }, singleLine = true, modifier = Modifier.fillMaxWidth())

            // Asked once, when joining (DPDP Act; App\Support\Consent on the server).
            if (joining) ConsentCheck("what I filled in here with attendees of this WordCamp", agreed) { agreed = it }

            val ready = tags.isNotEmpty() && (how != "typed" || typed.trim().length >= 2) && (how != "list" || pick != null) && (!joining || agreed)
            Row(horizontalArrangement = Arrangement.spacedBy(8.dp)) {
                PrimaryButton(if (model.busy) "Saving…" else "Save", {
                    if (ready && !model.busy) model.join(
                        DiscoveryForm(
                            tags = tags, profession = profession.trim().ifEmpty { null },
                            whoToMeet = toMeetEntries.joinToString(", ").trim().ifEmpty { null },
                            displayName = if (how == "typed") typed.trim() else null,
                            wporgUsername = wporg.trim().ifEmpty { null },
                            attendeeRosterId = if (how == "list") pick else null,
                            consent = if (joining) true else null,
                        ),
                        onDone = onClose,
                    )
                }, enabled = ready)
                QuietButton("Cancel", onClose)
            }
        }
    }
}

@OptIn(ExperimentalLayoutApi::class)
@Composable
private fun WhoToMeetField(
    selectedEntries: List<String>,
    onEntriesChanged: (List<String>) -> Unit,
) {
    val brand = LocalBrand.current
    var input by remember { mutableStateOf("") }

    val suggestions = listOf(
        "Developers", "Designers", "Founders", "Freelancers", "Agencies",
        "Core Contributors", "Mentors", "Job Opportunities", "Sponsors",
        "Students / Beginners", "Content Creators", "Plugin Developers", "Theme Developers"
    )

    Column(verticalArrangement = Arrangement.spacedBy(6.dp)) {
        Text("Who would you like to meet?", style = MaterialTheme.typography.titleMedium)
        Text(
            "Pick from suggestions or type custom roles (multiple allowed).",
            style = MaterialTheme.typography.bodySmall.copy(color = brand.muted)
        )

        if (selectedEntries.isNotEmpty()) {
            FlowRow(
                horizontalArrangement = Arrangement.spacedBy(6.dp),
                verticalArrangement = Arrangement.spacedBy(6.dp),
                modifier = Modifier.padding(vertical = 2.dp)
            ) {
                selectedEntries.forEach { entry ->
                    Row(
                        modifier = Modifier
                            .background(brand.peach, RoundedCornerShape(50))
                            .border(1.dp, brand.primary.copy(alpha = 0.3f), RoundedCornerShape(50))
                            .padding(start = 10.dp, end = 6.dp, top = 4.dp, bottom = 4.dp),
                        horizontalArrangement = Arrangement.spacedBy(4.dp),
                        verticalAlignment = Alignment.CenterVertically
                    ) {
                        Text(
                            entry,
                            style = MaterialTheme.typography.labelMedium.copy(
                                color = brand.ink,
                                fontWeight = FontWeight.SemiBold
                            )
                        )
                        Box(
                            modifier = Modifier
                                .size(18.dp)
                                .clip(CircleShape)
                                .clickable {
                                    onEntriesChanged(selectedEntries - entry)
                                },
                            contentAlignment = Alignment.Center
                        ) {
                            Text("×", style = MaterialTheme.typography.bodyMedium.copy(color = brand.primaryText, fontWeight = FontWeight.Bold))
                        }
                    }
                }
            }
        }

        Row(
            modifier = Modifier.fillMaxWidth(),
            horizontalArrangement = Arrangement.spacedBy(8.dp),
            verticalAlignment = Alignment.CenterVertically
        ) {
            OutlinedTextField(
                value = input,
                onValueChange = { input = it.take(60) },
                label = { Text("Add looking to meet") },
                placeholder = { Text("e.g. Developers, Founders...") },
                singleLine = true,
                modifier = Modifier.weight(1f),
                shape = RoundedCornerShape(brand.radius.dp)
            )
            val canAdd = input.trim().length >= 2 && !selectedEntries.any { it.equals(input.trim(), ignoreCase = true) }
            PrimaryButton(
                "+ Add",
                onClick = {
                    if (canAdd) {
                        onEntriesChanged(selectedEntries + input.trim())
                        input = ""
                    }
                },
                enabled = canAdd
            )
        }

        val availableSuggestions = suggestions.filter { sugg ->
            !selectedEntries.any { it.equals(sugg, ignoreCase = true) } &&
            (input.isBlank() || sugg.contains(input.trim(), ignoreCase = true))
        }

        if (availableSuggestions.isNotEmpty()) {
            FlowRow(
                horizontalArrangement = Arrangement.spacedBy(6.dp),
                verticalArrangement = Arrangement.spacedBy(6.dp),
                modifier = Modifier.padding(top = 2.dp)
            ) {
                availableSuggestions.take(6).forEach { sugg ->
                    Box(
                        modifier = Modifier
                            .background(brand.surface, RoundedCornerShape(50))
                            .border(1.dp, brand.line, RoundedCornerShape(50))
                            .clickable {
                                onEntriesChanged(selectedEntries + sugg)
                                if (input.isNotBlank() && sugg.contains(input.trim(), ignoreCase = true)) {
                                    input = ""
                                }
                            }
                            .padding(horizontal = 10.dp, vertical = 4.dp)
                    ) {
                        Text(
                            "+ $sugg",
                            style = MaterialTheme.typography.labelSmall.copy(
                                color = brand.muted,
                                fontWeight = FontWeight.Medium
                            )
                        )
                    }
                }
            }
        }
    }
}
