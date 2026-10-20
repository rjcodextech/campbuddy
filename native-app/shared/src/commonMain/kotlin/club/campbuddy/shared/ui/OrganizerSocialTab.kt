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

/** "#7a1f2b" as a colour; [fallback] for anything else. */
private fun hex(value: String, fallback: Color): Color {
    val v = value.removePrefix("#").takeIf { it.length == 6 }?.toLongOrNull(16) ?: return fallback
    return Color(0xFF000000 or v)
}

private fun Modifier.capture(layer: GraphicsLayer): Modifier = this.drawWithContent {
    layer.record { this@drawWithContent.drawContent() }
    drawLayer(layer)
}

@OptIn(ExperimentalLayoutApi::class)
@Composable
internal fun SocialTab(ctx: EventCtx) {
    var stamp by remember { mutableStateOf(0) }
    var section by remember { mutableStateOf("posts") }
    val loader = rememberLoader("org-social:${ctx.eventId}:$stamp") { Loaded(ctx.api.socialKit(ctx.token, ctx.eventId), false) }

    LazyColumn(Modifier.fillMaxSize(), contentPadding = PaddingValues(start = 16.dp, end = 16.dp, top = 16.dp, bottom = 96.dp), verticalArrangement = Arrangement.spacedBy(12.dp)) {
        // All five sections in view at once (they wrap), not a row that hides the last ones.
        item {
            FlowRow(horizontalArrangement = Arrangement.spacedBy(8.dp), verticalArrangement = Arrangement.spacedBy(4.dp)) {
                listOf("posts" to "Posts", "people" to "People cards", "look" to "Colours & art", "qr" to "QR codes", "publish" to "Publish").forEach { (key, label) ->
                    CbChip(selected = key == section, onClick = { section = key }, label = { Text(label) })
                }
            }
        }
        when (val s = loader.state.value) {
            LoadState.Loading -> item { Loading() }
            is LoadState.Failed -> item { OrgFailed(s.message, loader.reload, ctx.onExpired) }
            is LoadState.Ready -> {
                val k = s.value
                when (section) {
                    "people" -> {
                        item { Text("Everyone on the attendee list with a role or a shared Camp Card — ${plural(k.people.size, "card")}.", style = MaterialTheme.typography.bodySmall) }
                        if (k.people.isEmpty()) item { EmptyNote("No speakers, organizers or volunteers marked yet. Set roles in Attendees.", "users") }
                        items(k.people, key = { "p-${it.id}" }) { p -> ShareableCard(ctx, k, "people-${p.id}", p.name, p.caption) { PersonCard(k, p) } }
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
                        item { Text("Drawn in the brand colours with a QR to the event. Share or save the picture, copy the caption, or send both to your Publish webhook.", style = MaterialTheme.typography.bodySmall) }
                        items(k.posts, key = { "post-${it.key}" }) { p -> ShareableCard(ctx, k, p.key, p.label, p.caption) { PostCard(k, p) } }
                    }
                }
            }
        }
    }
}

/** One card with its tools: share, save, copy caption, publish. */
@Composable
private fun ShareableCard(ctx: EventCtx, kit: SocialKitData, kind: String, title: String, caption: String, card: @Composable () -> Unit) {
    val env = LocalEnv.current
    val toast = LocalOrgToast.current
    val clipboard = LocalClipboardManager.current
    val scope = rememberCoroutineScope()
    val actions = rememberOrgActions()
    val layer = rememberGraphicsLayer()
    var showCaption by remember { mutableStateOf(false) }
    var askPublish by remember { mutableStateOf(false) }
    val file = "campbuddy-${kit.event.slug}-$kind.png"

    Card {
        Column(verticalArrangement = Arrangement.spacedBy(10.dp)) {
            Row(verticalAlignment = Alignment.CenterVertically) {
                Text(title, style = MaterialTheme.typography.titleMedium.copy(fontSize = 15.sp), modifier = Modifier.weight(1f))
                if (kind.startsWith("people-")) OrgBadge("Person", Tone.Muted, "users")
            }
            Box(Modifier.fillMaxWidth().aspectRatio(1f).clip(RoundedCornerShape(8.dp)).capture(layer)) { card() }
            Row(Modifier.fillMaxWidth(), horizontalArrangement = Arrangement.spacedBy(8.dp)) {
                OrgButton("Share", { scope.launch { env.deps.shareImage(layer.toImageBitmap(), file) } }, Modifier.weight(1f), kind = BtnKind.Primary, icon = "share")
                OrgButton("Save", {
                    scope.launch { toast.show(if (env.deps.saveImage(layer.toImageBitmap(), file)) "Saved to your photos." else "Could not save. Use Share instead.", isError = false) }
                }, Modifier.weight(1f), icon = "download")
            }
            Row(Modifier.fillMaxWidth(), horizontalArrangement = Arrangement.spacedBy(8.dp)) {
                OrgButton("Copy caption", { clipboard.setText(AnnotatedString(caption)); toast.show("Caption copied.") }, Modifier.weight(1f), icon = "clipboard", enabled = caption.isNotBlank())
                if (kit.webhook.set) OrgButton(if (actions.busy) "Sending…" else "Publish", { askPublish = true }, Modifier.weight(1f), icon = "megaphone", enabled = !actions.busy)
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

// ---- the drawn cards ---------------------------------------------------------------------------------------

@Composable
private fun CardFrame(kit: SocialKitData, qr: String, content: @Composable () -> Unit) {
    val primary = hex(kit.colors.primary, Color(0xFF7A1F2B))
    val secondary = hex(kit.colors.secondary, Color(0xFFE0A11B))
    val paper = hex(kit.colors.paper, Color(0xFFFFF8EE))
    val ink = hex(kit.colors.ink, Color(0xFF231F20))
    val art = kit.site.art.firstOrNull { it.kind == "pattern" || it.kind == "illustration" }

    Box(Modifier.fillMaxSize().background(primary)) {
        // The site's own pattern or illustration, faint behind; else two soft circles in the second colour.
        if (art != null) {
            RemoteImage(art.url, Modifier.fillMaxSize().background(Color.Transparent), contentScale = ContentScale.Crop) {}
            Box(Modifier.fillMaxSize().background(primary.copy(alpha = 0.82f)))
        } else {
            Box(Modifier.size(220.dp).offset(x = 180.dp, y = (-70).dp).background(secondary.copy(alpha = 0.22f), CircleShape))
            Box(Modifier.size(140.dp).offset(x = (-50).dp, y = 230.dp).background(secondary.copy(alpha = 0.16f), CircleShape))
        }
        Column(Modifier.fillMaxSize().padding(18.dp), verticalArrangement = Arrangement.spacedBy(8.dp)) {
            Row(verticalAlignment = Alignment.CenterVertically, horizontalArrangement = Arrangement.spacedBy(8.dp)) {
                Box(Modifier.size(34.dp).background(paper, CircleShape).padding(5.dp), contentAlignment = Alignment.Center) {
                    RemoteImage(kit.event.mark ?: kit.event.logo, Modifier.size(24.dp)) { Text(kit.event.short.take(1), style = MaterialTheme.typography.titleMedium.copy(color = primary)) }
                }
                Text(kit.event.short.ifBlank { kit.event.name }, style = MaterialTheme.typography.titleMedium.copy(color = paper, fontSize = 14.sp), maxLines = 1, overflow = TextOverflow.Ellipsis)
            }
            Box(Modifier.weight(1f).fillMaxWidth()) { content() }
            Row(
                Modifier.fillMaxWidth().background(paper, RoundedCornerShape(10.dp)).padding(8.dp),
                verticalAlignment = Alignment.CenterVertically, horizontalArrangement = Arrangement.spacedBy(10.dp),
            ) {
                QrCodeView(qr, size = 58.dp, dark = ink)
                Column(Modifier.weight(1f)) {
                    Text("Open it in CampBuddy", style = MaterialTheme.typography.titleMedium.copy(color = ink, fontSize = 13.sp, lineHeight = 16.sp))
                    Text("Free · no sign-up · works offline", style = MaterialTheme.typography.bodySmall.copy(color = ink.copy(alpha = 0.7f), fontSize = 10.5.sp, lineHeight = 13.sp))
                    if (kit.event.hashtags.isNotBlank()) Text(kit.event.hashtags, style = MaterialTheme.typography.bodySmall.copy(color = primary, fontSize = 10.5.sp, lineHeight = 13.sp, fontWeight = FontWeight.SemiBold), maxLines = 1, overflow = TextOverflow.Ellipsis)
                }
            }
        }
    }
}

@OptIn(ExperimentalLayoutApi::class)
@Composable
private fun PostCard(kit: SocialKitData, post: SocialPost) {
    val paper = hex(kit.colors.paper, Color(0xFFFFF8EE))
    val secondary = hex(kit.colors.secondary, Color(0xFFE0A11B))
    val primary = hex(kit.colors.primary, Color(0xFF7A1F2B))
    CardFrame(kit, post.link.ifBlank { kit.event.qr }) {
        Column(Modifier.fillMaxSize(), verticalArrangement = Arrangement.spacedBy(6.dp, Alignment.CenterVertically)) {
            if (post.kicker.isNotBlank()) Text(post.kicker.uppercase(), style = MaterialTheme.typography.labelMedium.copy(color = secondary, fontSize = 12.sp, letterSpacing = 1.2.sp), maxLines = 2)
            if (post.body == "countdown" && post.days != null) {
                Text(post.days.toString(), style = MaterialTheme.typography.titleLarge.copy(color = secondary, fontSize = 72.sp, lineHeight = 74.sp, fontWeight = FontWeight.ExtraBold))
            }
            Text(post.headline, style = MaterialTheme.typography.titleLarge.copy(color = paper, fontSize = 27.sp, lineHeight = 31.sp, fontWeight = FontWeight.ExtraBold), maxLines = 3, overflow = TextOverflow.Ellipsis)
            if (post.line.isNotBlank()) Text(post.line, style = MaterialTheme.typography.bodyMedium.copy(color = paper.copy(alpha = 0.88f), fontSize = 14.sp), maxLines = 2)
            when (post.body) {
                "photos" -> FlowRow(horizontalArrangement = Arrangement.spacedBy((-8).dp), modifier = Modifier.padding(top = 4.dp)) {
                    kit.speakers.take(7).forEach { sp ->
                        Box(Modifier.size(40.dp).clip(CircleShape).background(secondary).border(2.dp, paper, CircleShape), contentAlignment = Alignment.Center) {
                            RemoteImage(sp.photo, Modifier.size(40.dp), contentScale = ContentScale.Crop) { Text(sp.name.take(1), style = MaterialTheme.typography.titleMedium.copy(color = primary)) }
                        }
                    }
                }
                "logos" -> FlowRow(horizontalArrangement = Arrangement.spacedBy(6.dp), verticalArrangement = Arrangement.spacedBy(6.dp), modifier = Modifier.padding(top = 4.dp)) {
                    kit.sponsors.take(6).forEach { sp ->
                        Box(Modifier.size(width = 70.dp, height = 34.dp).background(paper, RoundedCornerShape(6.dp)).padding(4.dp), contentAlignment = Alignment.Center) {
                            RemoteImage(sp.logo, Modifier.fillMaxSize()) { Text(sp.name, style = MaterialTheme.typography.bodySmall.copy(color = primary, fontSize = 9.sp), maxLines = 1) }
                        }
                    }
                }
                else -> Unit
            }
        }
    }
}

@Composable
private fun PersonCard(kit: SocialKitData, person: SocialPerson) {
    val paper = hex(kit.colors.paper, Color(0xFFFFF8EE))
    val secondary = hex(kit.colors.secondary, Color(0xFFE0A11B))
    val primary = hex(kit.colors.primary, Color(0xFF7A1F2B))
    CardFrame(kit, person.link ?: kit.event.qr) {
        Row(Modifier.fillMaxSize(), verticalAlignment = Alignment.CenterVertically, horizontalArrangement = Arrangement.spacedBy(14.dp)) {
            Box(Modifier.size(104.dp).clip(CircleShape).background(secondary).border(3.dp, paper, CircleShape), contentAlignment = Alignment.Center) {
                RemoteImage(person.photo, Modifier.size(104.dp), contentScale = ContentScale.Crop) {
                    Text(person.name.split(' ').mapNotNull { it.firstOrNull()?.uppercase() }.take(2).joinToString(""), style = MaterialTheme.typography.titleLarge.copy(color = primary, fontSize = 34.sp))
                }
            }
            Column(Modifier.weight(1f), verticalArrangement = Arrangement.spacedBy(4.dp)) {
                Text((person.roles.firstOrNull() ?: "Attendee").uppercase(), style = MaterialTheme.typography.labelMedium.copy(color = secondary, fontSize = 12.sp, letterSpacing = 1.2.sp))
                Text(person.name, style = MaterialTheme.typography.titleLarge.copy(color = paper, fontSize = 24.sp, lineHeight = 28.sp, fontWeight = FontWeight.ExtraBold), maxLines = 3)
                person.talk?.let { Text("“$it”", style = MaterialTheme.typography.bodyMedium.copy(color = paper.copy(alpha = 0.9f)), maxLines = 3, overflow = TextOverflow.Ellipsis) }
                if (person.talk == null) Text("Say hi at ${kit.event.short}!", style = MaterialTheme.typography.bodyMedium.copy(color = paper.copy(alpha = 0.9f)))
            }
        }
    }
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
