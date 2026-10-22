package club.campbuddy.shared.ui

import androidx.compose.foundation.background
import androidx.compose.foundation.border
import androidx.compose.foundation.clickable
import androidx.compose.foundation.layout.Arrangement
import androidx.compose.foundation.layout.Box
import androidx.compose.foundation.layout.Column
import androidx.compose.foundation.layout.ExperimentalLayoutApi
import androidx.compose.foundation.layout.FlowRow
import androidx.compose.foundation.layout.PaddingValues
import androidx.compose.foundation.layout.Row
import androidx.compose.foundation.layout.fillMaxSize
import androidx.compose.foundation.layout.fillMaxWidth
import androidx.compose.foundation.layout.heightIn
import androidx.compose.foundation.layout.padding
import androidx.compose.foundation.layout.size
import androidx.compose.foundation.lazy.LazyColumn
import androidx.compose.foundation.lazy.LazyRow
import androidx.compose.foundation.shape.RoundedCornerShape
import androidx.compose.foundation.text.BasicTextField
import androidx.compose.foundation.text.KeyboardActions
import androidx.compose.foundation.text.KeyboardOptions
import androidx.compose.material3.MaterialTheme
import androidx.compose.material3.Text
import androidx.compose.runtime.Composable
import androidx.compose.runtime.getValue
import androidx.compose.runtime.mutableStateOf
import androidx.compose.runtime.remember
import androidx.compose.runtime.rememberCoroutineScope
import androidx.compose.runtime.saveable.rememberSaveable
import androidx.compose.runtime.setValue
import androidx.compose.ui.Alignment
import androidx.compose.ui.Modifier
import androidx.compose.ui.draw.drawBehind
import androidx.compose.ui.draw.rotate
import androidx.compose.ui.draw.drawWithContent
import androidx.compose.ui.geometry.Offset
import androidx.compose.ui.geometry.Size
import androidx.compose.ui.graphics.Color
import androidx.compose.ui.graphics.PathEffect
import androidx.compose.ui.graphics.SolidColor
import androidx.compose.ui.graphics.drawscope.Stroke
import androidx.compose.ui.graphics.layer.GraphicsLayer
import androidx.compose.ui.graphics.layer.drawLayer
import androidx.compose.ui.graphics.rememberGraphicsLayer
import androidx.compose.ui.semantics.Role
import androidx.compose.ui.semantics.contentDescription
import androidx.compose.ui.semantics.semantics
import androidx.compose.ui.text.SpanStyle
import androidx.compose.ui.text.buildAnnotatedString
import androidx.compose.ui.text.font.FontWeight
import androidx.compose.ui.text.input.ImeAction
import androidx.compose.ui.text.input.KeyboardType
import androidx.compose.ui.text.withStyle
import androidx.compose.ui.unit.dp
import androidx.compose.ui.unit.sp
import club.campbuddy.shared.data.MyShare
import club.campbuddy.shared.domain.CampCard
import club.campbuddy.shared.model.RosterEntry
import kotlinx.coroutines.launch
import kotlinx.serialization.json.JsonArray
import kotlinx.serialization.json.JsonObject
import kotlinx.serialization.json.JsonPrimitive
import kotlinx.serialization.json.buildJsonObject
import kotlinx.serialization.json.put

private val SAMPLE = CampCard(name = "Sunil Kumar Sharma", role = "WordPress Engineer", interests = listOf("Gutenberg", "WooCommerce"), visible = setOf("role", "interests"))

private const val LINK_MESSAGE = "That doesn't look like a link. Try something like yoursite.com."
private const val HANDLE_MESSAGE = "Use just your handle: letters, numbers or underscores, up to 15."

/** What the attendee-list copy of a card holds: only what is on the card, and the link its QR opens (the website's sharedFields). */
private fun sharedFields(card: CampCard): JsonObject = buildJsonObject {
    CampCard.VISIBLE_FIELDS.forEach { (key, _) ->
        if (key !in card.visible) return@forEach
        if (key == "interests") {
            if (card.interests.isNotEmpty()) put("interests", JsonArray(card.interests.map { JsonPrimitive(it) }))
        } else if (card.value(key).isNotBlank()) put(key, card.value(key).trim())
    }
    card.qr?.let { field -> card.qrLink?.let { url -> put("qr", buildJsonObject { put("type", field); put("url", url) }) } }
}

/** The Camp Card tab: the seven layouts, the form that fills them, and the switch that puts the card on the attendee list. */
@OptIn(ExperimentalLayoutApi::class)
@Composable
fun CampCardTab(slug: String, eventName: String, logo: String?, mark: String? = null) {
    val env = LocalEnv.current
    val words = LocalWords.current
    val brand = LocalBrand.current
    val scope = rememberCoroutineScope()
    val profile = env.user.profile
    val seed = CampCard(role = profile.profession.orEmpty(), wordpressOrg = profile.wporg.orEmpty())
    var saved by remember { mutableStateOf(CampCard.fromMap(env.user.card(), seed)) }

    // The form's own values; Save copies them to the card.
    var name by remember { mutableStateOf(saved.name) }
    var role by remember { mutableStateOf(saved.role) }
    var company by remember { mutableStateOf(saved.company) }
    var city by remember { mutableStateOf(saved.city) }
    var interests by remember { mutableStateOf(saved.interests) }
    var ask by remember { mutableStateOf(saved.askMeAbout) }
    var linkedin by remember { mutableStateOf(saved.linkedin) }
    var website by remember { mutableStateOf(saved.website) }
    var wporg by remember { mutableStateOf(saved.wordpressOrg) }
    var twitter by remember { mutableStateOf(saved.twitter) }
    var qrField by remember { mutableStateOf(saved.qrField) }
    var visible by remember { mutableStateOf(saved.visible) }
    var errors by remember { mutableStateOf<Map<String, String>>(emptyMap()) }
    var editOpen by rememberSaveable { mutableStateOf(true) }
    var note by remember { mutableStateOf<String?>(null) }

    val live = saved.complete
    val shown = if (live) saved else SAMPLE
    val faceData = CardFaceData(eventName, logo, mark, shown, sample = !live)
    val layers = CAMP_CARD_LAYOUTS.associate { (key, _) -> key to rememberGraphicsLayer() }

    // ---- the attendee-list copy ----
    var share by remember { mutableStateOf(env.user.share(slug)) }
    var picking by remember { mutableStateOf(false) }
    var shareBusy by remember { mutableStateOf(false) }
    val rosterLoader = rememberLoader("roster:$slug") { env.repository.roster(slug) }

    fun payload(card: CampCard, rosterId: Long): JsonObject {
        val mine = env.user.discovery(slug)
        return buildJsonObject {
            put("attendee_roster_id", rosterId)
            mine?.let { put("discovery_id", it.discoveryId); put("discovery_token", it.ownerToken) }
            put("card", sharedFields(card))
        }
    }

    fun turnOn(rosterId: Long, who: String) {
        scope.launch {
            shareBusy = true
            try {
                val reply = env.api.shareCard(slug, payload(saved, rosterId))
                val mine = MyShare(reply.shareId, reply.ownerToken, rosterId, who)
                env.user.saveShare(slug, mine); share = mine; note = "Your Camp Card is on the attendee list."
            } catch (e: club.campbuddy.shared.data.ApiException) {
                note = if (e.status == 422) "Someone has already linked this name. If that wasn't you, ask an organizer." else "Couldn't share it right now. Try again."
            } catch (_: Exception) {
                note = "Couldn't share it right now. Try again."
            } finally { shareBusy = false; picking = false }
        }
    }

    fun turnOff() {
        val mine = share ?: return
        scope.launch {
            shareBusy = true
            runCatching { env.api.removeSharedCard(slug, mine.shareId, mine.ownerToken) }
            env.user.forgetShare(slug); share = null; note = "Your Camp Card is off the attendee list."; shareBusy = false
        }
    }

    fun save() {
        val e = mutableMapOf<String, String>()
        if (name.isBlank()) e["name"] = "Enter your name. It goes on your card."
        val tidy = mutableMapOf<String, String>()
        fun link(field: String, raw: String) {
            if (raw.isBlank()) return
            val ok = if (field == "twitter") CampCard.normalizeHandle(raw) else CampCard.tidyLink(field, raw)
            if (ok == null) e[field] = if (field == "twitter") HANDLE_MESSAGE else LINK_MESSAGE else tidy[field] = ok
        }
        link("linkedin", linkedin); link("website", website); link("wordpressOrg", wporg); link("twitter", twitter)
        if (e.isEmpty() && tidy.isEmpty()) e["links"] = "Add at least one link so your QR code has somewhere to go."
        errors = e
        if (e.isNotEmpty()) return

        linkedin = tidy["linkedin"].orEmpty(); website = tidy["website"].orEmpty(); wporg = tidy["wordpressOrg"].orEmpty(); twitter = tidy["twitter"].orEmpty()
        val card = CampCard(name.trim(), role.trim(), company.trim(), city.trim(), interests, ask.trim(), linkedin, website, wporg, twitter, qrField, visible)
        env.user.saveCard(card.toMap())
        saved = card
        note = "Camp Card saved on this phone."
        // The attendee-list copy follows the card.
        share?.let { mine ->
            scope.launch {
                try { env.api.updateSharedCard(slug, mine.shareId, mine.ownerToken, payload(card, mine.rosterId)) }
                catch (ex: club.campbuddy.shared.data.ApiException) { if (ex.status == 403) { env.user.forgetShare(slug); share = null } else note = "Saved on this phone. The attendee list copy updates next time you save." }
                catch (_: Exception) { note = "Saved on this phone. The attendee list copy updates next time you save." }
            }
        }
    }

    LazyColumn(Modifier.fillMaxSize(), contentPadding = PaddingValues(16.dp), verticalArrangement = Arrangement.spacedBy(12.dp)) {
        item { Text(words("tabs.camp_card"), style = MaterialTheme.typography.headlineSmall) }
        item {
            Text(
                if (live) "Swipe through the layouts. Your card stays on this phone until you choose to share it." else "This is a preview. Fill in the form below to make it yours.",
                style = MaterialTheme.typography.bodyLarge.copy(color = brand.muted),
            )
        }
        item {
            LazyRow(horizontalArrangement = Arrangement.spacedBy(16.dp)) {
                CAMP_CARD_LAYOUTS.forEach { (key, label) ->
                    item(key = key) {
                        Card(Modifier.fillParentMaxWidth(0.915f)) {
                            Column(verticalArrangement = Arrangement.spacedBy(8.dp)) {
                                Text(label, style = MaterialTheme.typography.bodyMedium.copy(color = brand.muted, fontWeight = FontWeight.SemiBold))
                                CampCardFace(key, faceData, Modifier.capture(layers.getValue(key)))
                                Row(Modifier.fillMaxWidth(), horizontalArrangement = Arrangement.spacedBy(10.dp)) {
                                    IconButtonOutline("share", "Share", Modifier.weight(1f)) { scope.launch { env.deps.shareImage(layers.getValue(key).toImageBitmap(), "campbuddy-$key.png") } }
                                    IconButtonOutline("download", "Download", Modifier.weight(1f)) {
                                        scope.launch { note = if (env.deps.saveImage(layers.getValue(key).toImageBitmap(), "campbuddy-$key.png")) "Saved to your photos." else "Could not save. Use Share instead." }
                                    }
                                }
                            }
                        }
                    }
                }
            }
        }
        note?.let { n -> item { Text(n, style = MaterialTheme.typography.bodyMedium.copy(color = brand.primary, fontWeight = FontWeight.SemiBold)) } }

        // ---- Edit Camp Card ----
        item {
            Row(Modifier.fillMaxWidth().clickable(role = Role.Button) { editOpen = !editOpen }.padding(vertical = 8.dp), horizontalArrangement = Arrangement.SpaceBetween, verticalAlignment = Alignment.CenterVertically) {
                Text("Edit Camp Card", style = MaterialTheme.typography.headlineSmall)
                Box(Modifier.rotate(if (editOpen) 180f else 0f)) { LineIcon("chevron-down", brand.ink, size = 22.dp) }
            }
        }
        if (editOpen) {
            item {
                Row(horizontalArrangement = Arrangement.spacedBy(10.dp), verticalAlignment = Alignment.Top) {
                    Box(Modifier.padding(top = 3.dp)) { LineIcon("lock", brand.muted, size = 16.dp) }
                    Text(
                        "Saved on this device only. Nothing you type here is sent to CampBuddy, unless you turn on \"Show my Camp Card on the attendee list\" below.",
                        style = MaterialTheme.typography.bodyMedium.copy(color = brand.muted),
                    )
                }
            }
            item {
                FormCard("About you", "This is what shows on your card.") {
                    FormField("Name", name, { name = it.take(60) }, "e.g. Sunil Kumar Sharma", required = true, error = errors["name"])
                    FormField("Role or title", role, { role = it.take(60) }, "e.g. WordPress Engineer")
                    FormField("Company or community", company, { company = it.take(60) }, "e.g. WPSimplified")
                    FormField("City", city, { city = it.take(40) }, "e.g. Jaipur")
                }
            }
            item {
                FormCard("Your interests", "So people know what to talk to you about.") {
                    TagInput(interests, { interests = it })
                    FormField("Ask me about", ask, { ask = it.take(80) }, "e.g. Block themes", hint = "A conversation starter for people who see your card.")
                }
            }
            item {
                FormCard("Find me online", "Add at least one. Your QR code points to one of them.", required = true) {
                    errors["links"]?.let { Text(it, style = MaterialTheme.typography.bodyMedium.copy(color = brand.danger)) }
                    FormField("LinkedIn", linkedin, { linkedin = it }, "linkedin.com/in/your-name", keyboard = KeyboardType.Uri, error = errors["linkedin"])
                    FormField("Personal website", website, { website = it }, "yoursite.com", keyboard = KeyboardType.Uri, error = errors["website"])
                    FormField("WordPress.org profile", wporg, { wporg = it }, "profiles.wordpress.org/your-name", keyboard = KeyboardType.Uri, error = errors["wordpressOrg"])
                    FormField("X / Twitter", twitter, { twitter = it }, "yourhandle", prefix = "@", error = errors["twitter"])
                }
            }
            item {
                FormCard("On your card", "Choose what's visible and where your QR code goes.") {
                    val draft = CampCard(linkedin = linkedin, website = website, wordpressOrg = wporg, twitter = twitter)
                    Column(verticalArrangement = Arrangement.spacedBy(8.dp)) {
                        Text("QR code links to", style = MaterialTheme.typography.titleMedium.copy(fontSize = 14.sp))
                        FlowRow(horizontalArrangement = Arrangement.spacedBy(8.dp), verticalArrangement = Arrangement.spacedBy(8.dp)) {
                            listOf("linkedin" to "LinkedIn", "website" to "Website", "wordpressOrg" to "WordPress.org", "twitter" to "X / Twitter").forEach { (field, label) ->
                                val has = draft.link(field) != null
                                Box(Modifier.drawBehind { if (!has) drawRect(Color.White.copy(alpha = 0.45f)) }) {
                                    ChoicePill(label, selected = has && (qrField ?: CampCard.LINK_FIELDS.firstOrNull { draft.link(it) != null }) == field) { if (has) qrField = field }
                                }
                            }
                        }
                        Text("Faded options need their link filled in above.", style = MaterialTheme.typography.bodySmall)
                    }
                    Column(verticalArrangement = Arrangement.spacedBy(8.dp)) {
                        Text("Show on my card", style = MaterialTheme.typography.titleMedium.copy(fontSize = 14.sp))
                        FlowRow(horizontalArrangement = Arrangement.spacedBy(8.dp), verticalArrangement = Arrangement.spacedBy(8.dp)) {
                            CampCard.VISIBLE_FIELDS.forEach { (key, label) -> ChoicePill(label, selected = key in visible) { visible = if (key in visible) visible - key else visible + key } }
                        }
                        Text("Your name and QR code always show.", style = MaterialTheme.typography.bodySmall)
                    }
                }
            }
            item {
                Text(
                    buildAnnotatedString { withStyle(SpanStyle(color = brand.primary, fontWeight = FontWeight.Bold)) { append("* ") }; append("Needed to generate your card: your name and at least one link.") },
                    style = MaterialTheme.typography.bodySmall,
                )
            }
            item { PrimaryButton("Save Camp Card", { save() }, Modifier.fillMaxWidth()) }
        }

        // ---- on the attendee list ----
        item {
            Card {
                Column(verticalArrangement = Arrangement.spacedBy(10.dp)) {
                    Row(horizontalArrangement = Arrangement.spacedBy(12.dp), verticalAlignment = Alignment.Top) {
                        Column(Modifier.weight(1f), verticalArrangement = Arrangement.spacedBy(4.dp)) {
                            Text("Show my Camp Card on the attendee list", style = MaterialTheme.typography.titleMedium)
                            Text(
                                "People who tap your name in Explore → People see your card and can save it. Only what is on your card is shared: the fields you chose under \"Show on my card\" and the link your QR opens. Turn it off any time.",
                                style = MaterialTheme.typography.bodyMedium.copy(color = brand.muted),
                            )
                        }
                        Switch(share != null || picking) {
                            if (shareBusy) return@Switch
                            if (share != null) turnOff()
                            else if (!saved.complete) note = "Save your Camp Card first: your name and at least one link."
                            else picking = true
                        }
                    }
                    if (share != null) Text("Shown on the attendee list as ${share?.name.orEmpty()}.", style = MaterialTheme.typography.bodyMedium.copy(color = brand.primary, fontWeight = FontWeight.SemiBold))
                    if (picking && share == null) SharePicker(rosterLoader.state.value, onPick = { turnOn(it.id, it.name) }, onCancel = { picking = false })
                }
            }
        }
    }
}

/** Records what a composable draws into [layer] as well, so the card can be turned into a picture on demand. */
private fun Modifier.capture(layer: GraphicsLayer): Modifier = this.drawWithContent {
    layer.record { this@drawWithContent.drawContent() }
    drawLayer(layer)
}

@Composable
private fun IconButtonOutline(icon: String, text: String, modifier: Modifier, onClick: () -> Unit) {
    val brand = LocalBrand.current
    Row(
        modifier.heightIn(min = 50.dp).border(1.dp, brand.line, Radii.pill).clickable(role = Role.Button, onClick = onClick).padding(horizontal = 12.dp),
        horizontalArrangement = Arrangement.spacedBy(8.dp, Alignment.CenterHorizontally), verticalAlignment = Alignment.CenterVertically,
    ) {
        LineIcon(icon, brand.ink, size = 20.dp)
        Text(text, style = MaterialTheme.typography.titleMedium.copy(color = brand.ink))
    }
}

/** A rounded on/off switch like the website's (.cc-share__switch). */
@Composable
private fun Switch(on: Boolean, onToggle: () -> Unit) {
    val brand = LocalBrand.current
    Box(
        Modifier.size(width = 48.dp, height = 28.dp).background(if (on) brand.success else Color(0xFFCDC4BD), RoundedCornerShape(50))
            .clickable(role = Role.Switch, onClick = onToggle).semantics { contentDescription = "Show my Camp Card on the attendee list" },
        contentAlignment = if (on) Alignment.CenterEnd else Alignment.CenterStart,
    ) { Box(Modifier.padding(3.dp).size(22.dp).background(Color.White, RoundedCornerShape(50))) }
}

@Composable
private fun FormCard(title: String, desc: String, required: Boolean = false, content: @Composable () -> Unit) {
    val brand = LocalBrand.current
    Card {
        Column(verticalArrangement = Arrangement.spacedBy(14.dp)) {
            Column {
                Text(
                    buildAnnotatedString { append(title); if (required) withStyle(SpanStyle(color = brand.primary)) { append("*") } },
                    style = MaterialTheme.typography.titleLarge,
                )
                Text(desc, style = MaterialTheme.typography.bodyMedium.copy(color = brand.muted), modifier = Modifier.padding(top = 2.dp))
            }
            content()
        }
    }
}

@Composable
private fun FormField(
    label: String, value: String, onChange: (String) -> Unit, placeholder: String, required: Boolean = false, hint: String? = null,
    prefix: String? = null, keyboard: KeyboardType = KeyboardType.Text, error: String? = null,
) {
    val brand = LocalBrand.current
    val shape = Radii.input
    Column(verticalArrangement = Arrangement.spacedBy(6.dp)) {
        Text(
            buildAnnotatedString { append(label); if (required) withStyle(SpanStyle(color = brand.primary)) { append(" *") } },
            style = MaterialTheme.typography.titleMedium.copy(fontSize = 14.sp),
        )
        Row(
            Modifier.fillMaxWidth().heightIn(min = 46.dp).background(Color.White, shape).border(1.dp, if (error != null) brand.danger else brand.line, shape).padding(horizontal = 12.dp),
            verticalAlignment = Alignment.CenterVertically,
        ) {
            prefix?.let { Text(it, style = MaterialTheme.typography.bodyLarge.copy(color = brand.muted), modifier = Modifier.padding(end = 4.dp)) }
            Box(Modifier.weight(1f)) {
                if (value.isEmpty()) Text(placeholder, style = MaterialTheme.typography.bodyLarge.copy(color = brand.muted.copy(alpha = 0.7f)))
                BasicTextField(
                    value, onChange, singleLine = true, textStyle = MaterialTheme.typography.bodyLarge, cursorBrush = SolidColor(brand.primary),
                    keyboardOptions = KeyboardOptions(keyboardType = keyboard, autoCorrectEnabled = keyboard == KeyboardType.Text),
                    modifier = Modifier.fillMaxWidth().semantics { contentDescription = label },
                )
            }
        }
        hint?.let { Text(it, style = MaterialTheme.typography.bodySmall) }
        error?.let { Text(it, style = MaterialTheme.typography.bodyMedium.copy(color = brand.danger)) }
    }
}

/** The interests box: tags inside it, added with Enter or comma, up to eight, with tap-to-add ideas underneath. */
@OptIn(ExperimentalLayoutApi::class)
@Composable
private fun TagInput(tags: List<String>, onChange: (List<String>) -> Unit) {
    val brand = LocalBrand.current
    val shape = Radii.input
    var text by remember { mutableStateOf("") }

    fun add(raw: String) {
        val tag = raw.trim().take(30)
        if (tag.isEmpty() || tags.any { it.equals(tag, ignoreCase = true) } || tags.size >= CampCard.MAX_INTERESTS) return
        onChange(tags + tag)
    }

    Column(verticalArrangement = Arrangement.spacedBy(6.dp)) {
        Text("WordPress interests", style = MaterialTheme.typography.titleMedium.copy(fontSize = 14.sp))
        Column(Modifier.fillMaxWidth().background(Color.White, shape).border(1.dp, brand.line, shape).padding(horizontal = 12.dp, vertical = 8.dp), verticalArrangement = Arrangement.spacedBy(6.dp)) {
            if (tags.isNotEmpty()) {
                FlowRow(horizontalArrangement = Arrangement.spacedBy(6.dp), verticalArrangement = Arrangement.spacedBy(6.dp)) {
                    tags.forEach { tag ->
                        Row(
                            Modifier.background(brand.peach, RoundedCornerShape(50)).padding(start = 10.dp, end = 6.dp, top = 4.dp, bottom = 4.dp),
                            horizontalArrangement = Arrangement.spacedBy(6.dp), verticalAlignment = Alignment.CenterVertically,
                        ) {
                            Text(tag, style = MaterialTheme.typography.bodyMedium.copy(fontWeight = FontWeight.SemiBold))
                            Box(Modifier.size(20.dp).clickable(role = Role.Button, onClickLabel = "Remove $tag") { onChange(tags - tag) }, contentAlignment = Alignment.Center) { LineIcon("x", brand.ink, size = 12.dp) }
                        }
                    }
                }
            }
            Row(verticalAlignment = Alignment.CenterVertically) {
                Box(Modifier.weight(1f).heightIn(min = 30.dp), contentAlignment = Alignment.CenterStart) {
                    if (text.isEmpty()) Text("Add an interest", style = MaterialTheme.typography.bodyLarge.copy(color = brand.muted.copy(alpha = 0.7f)))
                    BasicTextField(
                        text,
                        { v -> if (v.contains(',')) { v.split(',').forEach { add(it) }; text = "" } else text = v.take(30) },
                        singleLine = true, textStyle = MaterialTheme.typography.bodyLarge, cursorBrush = SolidColor(brand.primary),
                        keyboardOptions = KeyboardOptions(imeAction = ImeAction.Done), keyboardActions = KeyboardActions(onDone = { add(text); text = "" }),
                        modifier = Modifier.fillMaxWidth().semantics { contentDescription = "Add an interest" },
                    )
                }
                if (text.isNotBlank()) Text("Add", style = MaterialTheme.typography.labelLarge.copy(color = brand.primary), modifier = Modifier.clickable(role = Role.Button) { add(text); text = "" }.padding(8.dp))
            }
        }
        Text("${tags.size} of ${CampCard.MAX_INTERESTS} added. Press Enter or comma after each one.", style = MaterialTheme.typography.bodySmall)
        FlowRow(horizontalArrangement = Arrangement.spacedBy(8.dp), verticalArrangement = Arrangement.spacedBy(8.dp)) {
            CampCard.SUGGESTED_INTERESTS.filter { s -> tags.none { it.equals(s, ignoreCase = true) } }.forEach { s ->
                val pill = RoundedCornerShape(50)
                Row(
                    Modifier.heightIn(min = 44.dp).drawBehind { drawRoundRect(brand.muted.copy(alpha = 0.6f), style = Stroke(1.dp.toPx(), pathEffect = PathEffect.dashPathEffect(floatArrayOf(6f, 5f))), cornerRadius = androidx.compose.ui.geometry.CornerRadius(size.height / 2)) }
                        .clickable(role = Role.Button) { add(s) }.padding(horizontal = 14.dp),
                    horizontalArrangement = Arrangement.spacedBy(6.dp), verticalAlignment = Alignment.CenterVertically,
                ) {
                    Text("+", style = MaterialTheme.typography.labelLarge.copy(color = brand.primary, fontWeight = FontWeight.ExtraBold))
                    Text(s, style = MaterialTheme.typography.labelLarge.copy(color = brand.ink, fontWeight = FontWeight.SemiBold))
                }
            }
        }
    }
}

/** "Which one are you?": the attendee-list names matching what was typed. */
@Composable
private fun SharePicker(state: LoadState<List<RosterEntry>>, onPick: (RosterEntry) -> Unit, onCancel: () -> Unit) {
    val brand = LocalBrand.current
    var query by remember { mutableStateOf("") }
    val entries = (state as? LoadState.Ready)?.value.orEmpty()
    val matches = if (query.trim().length < 2) emptyList() else entries.filter { it.name.contains(query.trim(), ignoreCase = true) }.take(8)

    Column(verticalArrangement = Arrangement.spacedBy(8.dp)) {
        Text("Which name on the list is yours?", style = MaterialTheme.typography.titleMedium)
        SearchField(query, "Type your name…") { query = it }
        if (state is LoadState.Loading) Text("Loading the attendee list…", style = MaterialTheme.typography.bodySmall)
        if (state is LoadState.Failed) Text("Couldn't load the attendee list. Check your connection and try again.", style = MaterialTheme.typography.bodyMedium.copy(color = brand.danger))
        matches.forEach { entry ->
            Text(
                entry.name, style = MaterialTheme.typography.bodyLarge.copy(fontWeight = FontWeight.SemiBold),
                modifier = Modifier.fillMaxWidth().border(1.dp, brand.line, Radii.input).clickable(role = Role.Button) { onPick(entry) }.padding(12.dp),
            )
        }
        LinkButton("Cancel", onCancel)
    }
}
