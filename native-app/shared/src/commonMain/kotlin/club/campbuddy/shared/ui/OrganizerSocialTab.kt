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
import androidx.compose.foundation.layout.aspectRatio
import androidx.compose.foundation.layout.fillMaxSize
import androidx.compose.foundation.layout.fillMaxWidth
import androidx.compose.foundation.layout.offset
import androidx.compose.foundation.layout.padding
import androidx.compose.foundation.layout.size
import androidx.compose.foundation.lazy.LazyColumn
import androidx.compose.foundation.lazy.items
import androidx.compose.foundation.shape.CircleShape
import androidx.compose.foundation.shape.RoundedCornerShape
import androidx.compose.material3.MaterialTheme
import androidx.compose.material3.Text
import androidx.compose.runtime.Composable
import androidx.compose.runtime.getValue
import androidx.compose.runtime.mutableStateMapOf
import androidx.compose.runtime.mutableStateOf
import androidx.compose.runtime.remember
import androidx.compose.runtime.rememberCoroutineScope
import androidx.compose.runtime.setValue
import androidx.compose.ui.Alignment
import androidx.compose.ui.Modifier
import androidx.compose.ui.draw.clip
import androidx.compose.ui.draw.drawWithContent
import androidx.compose.ui.graphics.Color
import androidx.compose.ui.graphics.layer.GraphicsLayer
import androidx.compose.ui.graphics.layer.drawLayer
import androidx.compose.ui.graphics.rememberGraphicsLayer
import androidx.compose.ui.layout.ContentScale
import androidx.compose.ui.platform.LocalClipboardManager
import androidx.compose.ui.text.AnnotatedString
import androidx.compose.ui.text.font.FontWeight
import androidx.compose.ui.text.input.KeyboardType
import androidx.compose.ui.text.style.TextOverflow
import androidx.compose.ui.unit.dp
import androidx.compose.ui.unit.sp
import club.campbuddy.shared.data.Loaded
import club.campbuddy.shared.model.ArtPiece
import club.campbuddy.shared.model.SocialColors
import club.campbuddy.shared.model.SocialKitData
import club.campbuddy.shared.model.SocialPerson
import club.campbuddy.shared.model.SocialPost
import kotlinx.coroutines.launch

// An event's Social media tab: the posts and people cards drawn on the phone in the event's brand colours
// (share, save, copy the caption, or send to the Publish webhook), brand colours and the site's artwork,
// the QR kit, and the webhook. The words, captions and links come from the server (SocialKit).

private fun Modifier.capture(layer: GraphicsLayer): Modifier = this.drawWithContent {
    layer.record { this@drawWithContent.drawContent() }
    drawLayer(layer)
}

/** What a person can change in a post before sharing it (the website's three fields), per post key. */
private class PostWords(headline: String, line: String, caption: String) {
    var headline by mutableStateOf(headline)
    var line by mutableStateOf(line)
    var caption by mutableStateOf(caption)
}

/** "?…&utm_content=x" on the event's QR link, as the website's qrLink(). */
private fun withContent(qr: String, content: String) = qr + (if ('?' in qr) "&" else "?") + "utm_content=" + content

/** Cards of a list post per picture, by size (the website's pages): more fit on a story. */
private fun perPage(body: String, size: CardSize, design: String = ""): Int = when (body) {
    // Daylight: up to three people as cards, more as an even grid (as on the website).
    "photos" -> if (design == "daylight") when (size) { CardSize.Square -> 3; CardSize.Portrait -> 6; CardSize.Story -> 8 } else when (size) { CardSize.Square -> 6; CardSize.Portrait -> 8; CardSize.Story -> 12 }
    "logos" -> when (size) { CardSize.Square -> 6; CardSize.Portrait -> 9; CardSize.Story -> 12 }
    "list" -> when (size) { CardSize.Square -> 4; CardSize.Portrait -> 6; CardSize.Story -> 9 }
    "tables" -> when (size) { CardSize.Square -> 4; CardSize.Portrait -> 5; CardSize.Story -> 8 }
    else -> Int.MAX_VALUE
}

private fun todayCaption(kit: SocialKitData, day: String, list: List<club.campbuddy.shared.model.SocialSession>): String {
    val label = kit.schedule.days.firstOrNull { it.key == day }?.text.orEmpty()
    val lines = list.joinToString("\n") { s -> "${s.time}  ${s.title}" + if (s.speakers.isNotEmpty()) " (${s.speakers.joinToString(", ")})" else "" }
    return "$label at ${kit.event.name}:\n\n$lines\n\nSave your picks and get a reminder before each talk in CampBuddy: ${withContent(kit.event.qr, "today")}\n\n${kit.event.hashtags} #CampBuddy"
}

private fun spotlightCaption(kit: SocialKitData, s: club.campbuddy.shared.model.SocialSession?): String {
    if (s == null) return ""
    val who = if (s.speakers.isNotEmpty()) " with ${s.speakers.joinToString(", ")}" else ""
    val where = s.track?.let { " in $it" } ?: ""
    return "Up next at ${kit.event.name}: \"${s.title}\"$who, ${s.time}$where.\n\nSave it in CampBuddy and get a reminder before it starts: ${withContent(kit.event.qr, "spotlight")}\n\n${kit.event.hashtags} #CampBuddy"
}

private val ROLE_NAMES = mapOf(
    "organizer" to "Organizers", "speaker" to "Speakers", "volunteer" to "Volunteers", "microsponsor" to "Microsponsors",
    "sponsor" to "Sponsors", "media_partner" to "Media Partners", "table_lead" to "Table Leads",
)

@OptIn(ExperimentalLayoutApi::class)
@Composable
internal fun SocialTab(ctx: EventCtx) {
    var stamp by remember { mutableStateOf(0) }
    var section by remember { mutableStateOf("posts") }
    var size by remember { mutableStateOf(CardSize.Portrait) }
    var print by remember { mutableStateOf(false) }
    var postDesign by remember { mutableStateOf("daylight") }
    var peopleDesign by remember { mutableStateOf("daylight") }
    var role by remember { mutableStateOf<String?>(null) }
    var shown by remember { mutableStateOf(10) }
    val words = remember { mutableStateMapOf<String, PostWords>() }
    val picks = remember { mutableStateMapOf<String, String>() }
    val loader = rememberLoader("org-social:${ctx.eventId}:$stamp") { Loaded(ctx.api.socialKit(ctx.token, ctx.eventId), false) }
    val exportWidth = if (print) size.w * 3 else size.w

    LazyColumn(Modifier.fillMaxSize(), contentPadding = PaddingValues(start = 16.dp, end = 16.dp, top = 16.dp, bottom = 96.dp), verticalArrangement = Arrangement.spacedBy(12.dp)) {
        // All five sections in view at once (they wrap), not a row that hides the last ones.
        item {
            FlowRow(horizontalArrangement = Arrangement.spacedBy(8.dp), verticalArrangement = Arrangement.spacedBy(4.dp)) {
                listOf("posts" to "Posts", "people" to "People cards", "look" to "Colours & art", "qr" to "QR codes", "publish" to "Publish").forEach { (key, label) ->
                    CbChip(selected = key == section, onClick = { section = key }, label = { Text(label) })
                }
            }
        }
        if (section == "posts" || section == "people") item {
            OrgSection("Format", "columns", "For every picture below; the website's sizes and designs") {
                ChipGroup("Size", CardSize.entries.map { it to it.label }, size) { size = it }
                ChipGroup("Quality", listOf(false to "Social (1080 px)", true to "Print (3×, ${size.w * 3} px)"), print) { print = it }
                if (section == "posts") ChipGroup("Post design", POST_DESIGNS, postDesign) { postDesign = it }
                else ChipGroup("People card design", PEOPLE_DESIGNS, peopleDesign) { peopleDesign = it }
            }
        }
        when (val s = loader.state.value) {
            LoadState.Loading -> item { Loading() }
            is LoadState.Failed -> item { OrgFailed(s.message, loader.reload, ctx.onExpired) }
            is LoadState.Ready -> {
                val k = s.value
                when (section) {
                    "people" -> {
                        val counts = ROLE_NAMES.keys.associateWith { r -> k.people.count { r in it.roleKeys } }.filterValues { it > 0 }
                        val cards = k.people.count { it.hasCard }
                        item {
                            ChipGroup(
                                "Who",
                                listOf<Pair<String?, String>>(null to "Everyone (${k.people.size})") + counts.map { (r, n) -> r to "${ROLE_NAMES[r]} ($n)" } + (if (cards > 0) listOf<Pair<String?, String>>("card" to "Shared a Camp Card ($cards)") else emptyList()),
                                role,
                            ) { role = it; shown = 10 }
                        }
                        val list = k.people.filter { p -> role == null || (role == "card" && p.hasCard) || (role != "card" && role in p.roleKeys) }
                        if (k.people.isEmpty()) item { EmptyNote("No speakers, organizers or volunteers marked yet. Set roles in Attendees.", "users") }
                        items(list.take(shown), key = { "p-${it.id}" }) { p ->
                            val qr = p.link ?: withContent(k.event.qr, "people-" + (p.roleKeys.firstOrNull() ?: "camp-card").replace('_', '-'))
                            ShareableCard(ctx, k, "people-${p.id}", p.name, p.caption, exportWidth, pages = 1, page = 1, onPage = {}) {
                                PersonPicture(k, p, peopleDesign, size, qr)
                            }
                        }
                        if (list.size > shown) item { OrgButton("Show ${minOf(10, list.size - shown)} more (${list.size - shown} left)", { shown += 10 }, Modifier.fillMaxWidth(), icon = "chevron-down") }
                    }
                    "look" -> {
                        item { ColoursEditor(ctx, k) { stamp++ } }
                        item { ArtworkEditor(ctx, k) { stamp++ } }
                    }
                    "qr" -> {
                        item { Text("Each code opens this WordCamp in CampBuddy and says where it was scanned (Admin → Analytics).", style = MaterialTheme.typography.bodySmall) }
                        items(k.qr, key = { "qr-${it.key}" }) { q -> QrCard(q.label, q.hint, q.url) }
                    }
                    "publish" -> item { WebhookEditor(ctx, k) { stamp++ } }
                    else -> {
                        item { Text("Drawn in the brand colours with a QR to the event. Change the words, then share or save the picture, copy the caption, or send both to your Publish webhook.", style = MaterialTheme.typography.bodySmall) }
                        items(k.posts.mapIndexed { i, p -> i to p }, key = { "post-${it.second.key}" }) { (index, post) ->
                            val w = words.getOrPut(post.key) { PostWords(post.headline, post.line, post.caption) }
                            PostItem(ctx, k, post, w, picks, size, postDesign, exportWidth, index)
                        }
                    }
                }
            }
        }
    }
}

/** Choices that wrap onto more lines, so every size and design is in view (no sideways scrolling to find one). */
@OptIn(ExperimentalLayoutApi::class)
@Composable
private fun <K> ChipGroup(label: String, options: List<Pair<K, String>>, selected: K, onSelect: (K) -> Unit) {
    Column(verticalArrangement = Arrangement.spacedBy(2.dp)) {
        Text(label.uppercase(), style = MaterialTheme.typography.labelMedium.copy(fontSize = 10.5.sp, letterSpacing = 0.6.sp))
        FlowRow(horizontalArrangement = Arrangement.spacedBy(8.dp)) {
            options.forEach { (key, text) -> CbChip(selected = key == selected, onClick = { onSelect(key) }, label = { Text(text) }) }
        }
    }
}

/** One post: its picker (Today: a day, Spotlight: a talk), its words, the picture (paged when long) and tools. */
@OptIn(ExperimentalLayoutApi::class)
@Composable
private fun PostItem(
    ctx: EventCtx, k: SocialKitData, post: club.campbuddy.shared.model.SocialPost, w: PostWords, picks: MutableMap<String, String>,
    size: CardSize, design: String, exportWidth: Int, index: Int,
) {
    var page by remember(post.key, size) { mutableStateOf(1) }
    var editing by remember { mutableStateOf(false) }
    val schedule = k.schedule
    val pick = picks[post.key] ?: when (post.picker) {
        "day" -> schedule.days.firstOrNull()?.key
        "session" -> schedule.next ?: schedule.sessions.firstOrNull()?.id
        else -> null
    }
    val daySessions = if (post.picker == "day" && pick != null) schedule.sessions.filter { it.day == pick } else emptyList()
    val session = if (post.picker == "session") schedule.sessions.firstOrNull { it.id == pick } else null
    // The caption follows the picked day or talk (as on the website), unless the person typed their own.
    fun pickAndCaption(value: String) {
        picks[post.key] = value
        if (post.picker == "day") w.caption = todayCaption(k, value, schedule.sessions.filter { it.day == value })
        if (post.picker == "session") { w.caption = spotlightCaption(k, schedule.sessions.firstOrNull { it.id == value }); w.headline = ""; w.line = "" }
    }
    if (post.picker != null && pick != null && picks[post.key] == null) androidx.compose.runtime.LaunchedEffect(post.key) { pickAndCaption(pick) }

    val qr = post.link.ifBlank { withContent(k.event.qr, post.key) }
    val faces = when (post.body) { "photos" -> k.speakers.filter { it.photo != null }.ifEmpty { k.speakers }; "logos" -> k.sponsors; else -> emptyList() }
    val listCount = when (post.body) { "photos", "logos" -> faces.size; "list" -> daySessions.size; "tables" -> k.tables.size; else -> 0 }
    val per = perPage(post.body, size, design)
    val pages = if (listCount > per) (listCount + per - 1) / per else 1
    val current = page.coerceIn(1, pages)
    fun <T> slice(list: List<T>) = if (pages > 1) list.drop((current - 1) * per).take(per) else list
    val content = PostContent(
        key = post.key,
        kicker = if (post.picker == "day") schedule.days.firstOrNull { it.key == pick }?.text ?: post.kicker else post.kicker,
        headline = w.headline.ifBlank { session?.title ?: post.headline },
        line = w.line.ifBlank { session?.let { listOfNotNull(it.time, it.track).joinToString(" · ") } ?: "" },
        body = if (post.picker == "day") "list" else post.body,
        qr = qr,
        days = post.days,
        faces = slice(faces),
        sessions = slice(daySessions),
        tables = slice(k.tables),
        session = session,
        page = current,
        pages = pages,
    )

    ShareableCard(ctx, k, post.key, post.label, w.caption, exportWidth, pages, current, onPage = { page = it }, header = {
        if (post.picker == "day" && schedule.days.isNotEmpty()) OrgFilterRow("Day", schedule.days.map { it.key to it.text }, pick) { pickAndCaption(it!!) }
        if (post.picker == "session" && schedule.sessions.isNotEmpty()) OrgFilterRow("Session", schedule.sessions.map { it.id to (it.time + " · " + it.title.take(36)) }, pick) { pickAndCaption(it!!) }
        LinkButton(if (editing) "Done changing words" else "Change the words", { editing = !editing })
        if (editing) {
            OrgField("Headline on the image", w.headline, { w.headline = it }, max = 60, placeholder = session?.title ?: post.headline)
            OrgField("Line under it", w.line, { w.line = it }, max = 90)
            OrgField("Caption", w.caption, { w.caption = it }, lines = 6, max = 3000)
            OrgButton("Back to CampBuddy's words", { w.headline = post.headline; w.line = post.line; w.caption = post.caption; if (pick != null) pickAndCaption(pick) }, icon = "refresh")
        }
    }) {
        PostPicture(k, content, design, size, index)
    }
}

/** One picture with its tools: share, save, copy caption, publish; a pager when the post needs several cards. */
@Composable
private fun ShareableCard(
    ctx: EventCtx, kit: SocialKitData, kind: String, title: String, caption: String, exportWidth: Int,
    pages: Int, page: Int, onPage: (Int) -> Unit,
    header: (@Composable () -> Unit)? = null,
    card: @Composable () -> Unit,
) {
    val env = LocalEnv.current
    val toast = LocalOrgToast.current
    val clipboard = LocalClipboardManager.current
    val scope = rememberCoroutineScope()
    val actions = rememberOrgActions()
    val layer = rememberGraphicsLayer()
    var showCaption by remember { mutableStateOf(false) }
    var askPublish by remember { mutableStateOf(false) }
    val file = "campbuddy-${kit.event.slug}-$kind" + (if (pages > 1) "-$page" else "") + ".png"

    Card {
        Column(verticalArrangement = Arrangement.spacedBy(10.dp)) {
            Row(verticalAlignment = Alignment.CenterVertically) {
                Text(title, style = MaterialTheme.typography.titleMedium.copy(fontSize = 15.sp), modifier = Modifier.weight(1f))
                if (kind.startsWith("people-")) OrgBadge("Person", Tone.Muted, "users")
                if (pages > 1) OrgBadge("Card $page of $pages", Tone.Primary)
            }
            header?.invoke()
            Box(Modifier.fillMaxWidth().captureAt(layer, exportWidth)) { card() }
            if (pages > 1) Row(Modifier.fillMaxWidth(), verticalAlignment = Alignment.CenterVertically, horizontalArrangement = Arrangement.spacedBy(8.dp)) {
                OrgButton("Previous", { onPage(page - 1) }, enabled = page > 1, icon = "chevron-left")
                Text("Card $page of $pages", style = MaterialTheme.typography.bodySmall, modifier = Modifier.weight(1f), textAlign = androidx.compose.ui.text.style.TextAlign.Center)
                OrgButton("Next", { onPage(page + 1) }, enabled = page < pages, icon = "chevron-right")
            }
            Row(Modifier.fillMaxWidth(), horizontalArrangement = Arrangement.spacedBy(8.dp)) {
                OrgButton("Share", { scope.launch { env.deps.shareImage(layer.toImageBitmap(), file) } }, Modifier.weight(1f), kind = BtnKind.Primary, icon = "share")
                OrgButton("Save", {
                    scope.launch { toast.show(if (env.deps.saveImage(layer.toImageBitmap(), file)) "Saved to your photos." else "Could not save. Use Share instead.", isError = false) }
                }, Modifier.weight(1f), icon = "download")
            }
            Row(Modifier.fillMaxWidth(), horizontalArrangement = Arrangement.spacedBy(8.dp)) {
                OrgButton("Copy caption", { clipboard.setText(AnnotatedString(caption)); toast.show("Caption copied.") }, Modifier.weight(1f), icon = "clipboard", enabled = caption.isNotBlank())
                // Publish sends the social-size picture (a print-size one is too big for most webhooks).
                if (kit.webhook.set) OrgButton(if (actions.busy) "Sending…" else if (exportWidth > 1080) "Publish (Social only)" else "Publish", { askPublish = true }, Modifier.weight(1f), icon = "megaphone", enabled = !actions.busy && exportWidth <= 1080)
            }
            if (caption.isNotBlank()) {
                LinkButton(if (showCaption) "Hide caption" else "Show caption", { showCaption = !showCaption })
                if (showCaption) Text(caption, style = MaterialTheme.typography.bodySmall.copy(color = LocalBrand.current.ink))
            }
        }
    }
    if (askPublish) ConfirmSheet("Publish “$title”?", "Sends this picture and its caption to your webhook (${kit.webhook.masked ?: "set"}), which posts it where you set it up.", "Publish", danger = false, onConfirm = {
        actions.run({
            val png = env.deps.encodePng(layer.toImageBitmap()) ?: return@run "This phone cannot send pictures yet. Use Share instead."
            ctx.api.publishSocial(ctx.token, ctx.eventId, png, title, caption, kind).message
        })
    }, onClose = { askPublish = false })
}

// ---- colours & artwork ---------------------------------------------------------------------------------------

@OptIn(ExperimentalLayoutApi::class)
@Composable
private fun ColoursEditor(ctx: EventCtx, kit: SocialKitData, onSaved: () -> Unit) {
    val actions = rememberOrgActions()
    var primary by remember(kit.colors) { mutableStateOf(kit.colors.primary) }
    var secondary by remember(kit.colors) { mutableStateOf(kit.colors.secondary) }
    var ink by remember(kit.colors) { mutableStateOf(kit.colors.ink) }
    var paper by remember(kit.colors) { mutableStateOf(kit.colors.paper) }
    var slot by remember { mutableStateOf("primary") }
    val valid = listOf(primary, secondary, ink, paper).all { Regex("^#[0-9a-fA-F]{6}$").matches(it) }

    OrgSection("Brand colours", "sparkles", if (kit.colorsSaved) "Saved for this WordCamp" else "Not saved yet: these are read from the site or CampBuddy's own") {
        listOf(Triple("primary", "Main", primary), Triple("secondary", "Accent", secondary), Triple("ink", "Text", ink), Triple("paper", "Paper", paper)).forEach { (key, label, value) ->
            Row(verticalAlignment = Alignment.CenterVertically, horizontalArrangement = Arrangement.spacedBy(10.dp)) {
                Box(
                    Modifier.size(40.dp).clip(CircleShape).background(hex(value, Color.Transparent))
                        .border(if (slot == key) 3.dp else 1.dp, if (slot == key) LocalBrand.current.primary else LocalBrand.current.line, CircleShape)
                        .clickable { slot = key },
                )
                OrgField(label, value, { v ->
                    val clean = "#" + v.removePrefix("#").filter { it.isLetterOrDigit() }.take(6)
                    when (key) { "primary" -> primary = clean; "secondary" -> secondary = clean; "ink" -> ink = clean; else -> paper = clean }
                }, Modifier.weight(1f), max = 7)
            }
        }
        if (kit.site.colors.isNotEmpty()) {
            Text("The site's colours — tap one to put it in the ringed circle", style = MaterialTheme.typography.bodySmall)
            FlowRow(horizontalArrangement = Arrangement.spacedBy(8.dp), verticalArrangement = Arrangement.spacedBy(8.dp)) {
                kit.site.colors.take(16).forEach { c ->
                    Box(Modifier.size(32.dp).clip(CircleShape).background(hex(c, Color.Transparent)).border(1.dp, LocalBrand.current.line, CircleShape).clickable {
                        when (slot) { "primary" -> primary = c; "secondary" -> secondary = c; "ink" -> ink = c; else -> paper = c }
                    })
                }
            }
        }
        Row(horizontalArrangement = Arrangement.spacedBy(8.dp)) {
            OrgButton(if (actions.busy) "Saving…" else "Save colours", {
                actions.run({ ctx.api.saveSocialColors(ctx.token, ctx.eventId, SocialColors(primary.lowercase(), secondary.lowercase(), ink.lowercase(), paper.lowercase())).message }, onSaved)
            }, Modifier.weight(1f), kind = BtnKind.Primary, icon = "check", enabled = valid && !actions.busy)
            OrgButton("Read site again", { actions.run({ ctx.api.refreshSiteTheme(ctx.token, ctx.eventId).message }, onSaved) }, Modifier.weight(1f), icon = "refresh", enabled = !actions.busy)
        }
        kit.site.tagline?.let { KeyValue("Site tagline", it) }
    }
}

@Composable
private fun ArtworkEditor(ctx: EventCtx, kit: SocialKitData, onSaved: () -> Unit) {
    val actions = rememberOrgActions()
    OrgSection("Artwork", "camera", "From the WordCamp site (up to 5 in use). Uploading new art is on the website.") {
        if (kit.site.art.isEmpty()) Text(kit.site.error ?: "No artwork found on the site yet. Try “Read site again”.", style = MaterialTheme.typography.bodySmall)
        kit.site.art.forEach { piece -> ArtRow(piece, inUse = true, busy = actions.busy,
            onKind = { kind -> actions.run({ ctx.api.artAction(ctx.token, ctx.eventId, "kind", piece.id, kind).message }, onSaved) },
            onToggle = { actions.run({ ctx.api.artAction(ctx.token, ctx.eventId, "remove", piece.id).message }, onSaved) }) }
        if (kit.site.more.isNotEmpty()) {
            Text("More from the site", style = MaterialTheme.typography.titleMedium.copy(fontSize = 15.sp))
            kit.site.more.take(12).forEach { piece -> ArtRow(piece, inUse = false, busy = actions.busy, onKind = null,
                onToggle = { actions.run({ ctx.api.artAction(ctx.token, ctx.eventId, "use", piece.id).message }, onSaved) }) }
        }
    }
}

@Composable
private fun ArtRow(piece: ArtPiece, inUse: Boolean, busy: Boolean, onKind: ((String) -> Unit)?, onToggle: () -> Unit) {
    val brand = LocalBrand.current
    Column(Modifier.fillMaxWidth().background(brand.background, RoundedCornerShape(8.dp)).padding(8.dp), verticalArrangement = Arrangement.spacedBy(6.dp)) {
        Row(verticalAlignment = Alignment.CenterVertically, horizontalArrangement = Arrangement.spacedBy(10.dp)) {
            Box(Modifier.size(64.dp).clip(RoundedCornerShape(6.dp)).background(brand.line)) {
                RemoteImage(piece.url, Modifier.size(64.dp), contentScale = ContentScale.Crop) {}
            }
            Column(Modifier.weight(1f)) {
                Text(piece.name.ifBlank { "Artwork" }, style = MaterialTheme.typography.bodyMedium, maxLines = 2, overflow = TextOverflow.Ellipsis)
                Row(horizontalArrangement = Arrangement.spacedBy(4.dp)) {
                    if (piece.kind.isNotBlank()) OrgBadge(piece.kind, Tone.Muted)
                    if (piece.uploaded) OrgBadge("Uploaded", Tone.Primary)
                    if (piece.hidden) OrgBadge("Taken off", Tone.Warn)
                }
            }
            OrgButton(if (inUse) "Take off" else "Use this", onToggle, kind = if (inUse) BtnKind.Danger else BtnKind.Quiet, enabled = !busy)
        }
        if (onKind != null) OrgFilterRow(null, listOf("pattern" to "Pattern", "illustration" to "Illustration", "photo" to "Photo"), piece.kind) { if (it != piece.kind) onKind(it) }
    }
}

// ---- QR codes, publish -----------------------------------------------------------------------------------

@Composable
private fun QrCard(label: String, hint: String, url: String) {
    val env = LocalEnv.current
    val toast = LocalOrgToast.current
    val clipboard = LocalClipboardManager.current
    val scope = rememberCoroutineScope()
    val layer = rememberGraphicsLayer()
    Card {
        Row(verticalAlignment = Alignment.CenterVertically, horizontalArrangement = Arrangement.spacedBy(12.dp)) {
            Box(Modifier.capture(layer).background(Color.White).padding(4.dp)) { QrCodeView(url, size = 112.dp) }
            Column(Modifier.weight(1f), verticalArrangement = Arrangement.spacedBy(6.dp)) {
                Text(label, style = MaterialTheme.typography.titleMedium.copy(fontSize = 15.sp))
                Text(hint, style = MaterialTheme.typography.bodySmall)
                Row(horizontalArrangement = Arrangement.spacedBy(6.dp)) {
                    OrgIconButton("share", "Share QR", { scope.launch { env.deps.shareImage(layer.toImageBitmap(), "campbuddy-qr-${label.lowercase().replace(Regex("[^a-z]+"), "-")}.png") } })
                    OrgIconButton("link", "Copy link", { clipboard.setText(AnnotatedString(url)); toast.show("Link copied.") })
                    OrgIconButton("external", "Open link", { env.deps.openUrl(url) })
                }
            }
        }
    }
}

@Composable
private fun WebhookEditor(ctx: EventCtx, kit: SocialKitData, onSaved: () -> Unit) {
    val actions = rememberOrgActions()
    var url by remember { mutableStateOf("") }
    var askRemove by remember { mutableStateOf(false) }
    OrgSection("Publish webhook", "megaphone", "Make.com, Zapier, n8n or Pipedream: they post the picture and caption to your accounts") {
        if (kit.webhook.set) {
            KeyValue("Now", kit.webhook.masked)
            Text("A Publish button shows under every card.", style = MaterialTheme.typography.bodySmall)
        } else {
            Text("No webhook yet. Without one, use Share / Save and Copy caption.", style = MaterialTheme.typography.bodySmall)
        }
        OrgField(if (kit.webhook.set) "Replace with" else "Webhook address", url, { url = it.trim() }, keyboard = KeyboardType.Uri, max = 500, placeholder = "https://hook.eu1.make.com/…", help = "The address is a secret: only its end is shown again")
        Row(horizontalArrangement = Arrangement.spacedBy(8.dp)) {
            OrgButton(if (actions.busy) "Saving…" else "Save webhook", { actions.run({ ctx.api.saveWebhook(ctx.token, ctx.eventId, url).message }, { url = ""; onSaved() }) }, Modifier.weight(1f), kind = BtnKind.Primary, icon = "check", enabled = url.startsWith("https://") && !actions.busy)
            if (kit.webhook.set) OrgButton("Remove", { askRemove = true }, Modifier.weight(1f), kind = BtnKind.Danger, icon = "trash", enabled = !actions.busy)
        }
    }
    if (askRemove) ConfirmSheet("Remove the webhook?", "Publish stops working until a new one is saved.", "Remove", onConfirm = {
        actions.run({ ctx.api.saveWebhook(ctx.token, ctx.eventId, null).message }, onSaved)
    }, onClose = { askRemove = false })
}
