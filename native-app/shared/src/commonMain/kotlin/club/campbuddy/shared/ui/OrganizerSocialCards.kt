package club.campbuddy.shared.ui

import androidx.compose.foundation.background
import androidx.compose.foundation.border
import androidx.compose.foundation.layout.Arrangement
import androidx.compose.foundation.layout.Box
import androidx.compose.foundation.layout.BoxScope
import androidx.compose.foundation.layout.Column
import androidx.compose.foundation.layout.ColumnScope
import androidx.compose.foundation.layout.ExperimentalLayoutApi
import androidx.compose.foundation.layout.FlowRow
import androidx.compose.foundation.layout.Row
import androidx.compose.foundation.layout.aspectRatio
import androidx.compose.foundation.layout.fillMaxHeight
import androidx.compose.foundation.layout.fillMaxSize
import androidx.compose.foundation.layout.fillMaxWidth
import androidx.compose.foundation.layout.height
import androidx.compose.foundation.layout.offset
import androidx.compose.foundation.layout.padding
import androidx.compose.foundation.layout.size
import androidx.compose.foundation.layout.width
import androidx.compose.foundation.shape.CircleShape
import androidx.compose.foundation.shape.RoundedCornerShape
import androidx.compose.material3.MaterialTheme
import androidx.compose.material3.Text
import androidx.compose.runtime.Composable
import androidx.compose.runtime.CompositionLocalProvider
import androidx.compose.ui.Alignment
import androidx.compose.ui.Modifier
import androidx.compose.ui.draw.clip
import androidx.compose.ui.draw.drawWithContent
import androidx.compose.ui.draw.rotate
import androidx.compose.ui.geometry.Offset
import androidx.compose.ui.graphics.Brush
import androidx.compose.ui.graphics.Color
import androidx.compose.ui.graphics.drawscope.scale
import androidx.compose.ui.graphics.layer.GraphicsLayer
import androidx.compose.ui.graphics.luminance
import androidx.compose.ui.layout.ContentScale
import androidx.compose.ui.platform.LocalDensity
import androidx.compose.ui.text.TextStyle
import androidx.compose.ui.text.font.FontWeight
import androidx.compose.ui.text.style.TextAlign
import androidx.compose.ui.text.style.TextOverflow
import androidx.compose.ui.unit.Density
import androidx.compose.ui.unit.IntSize
import androidx.compose.ui.unit.TextUnit
import androidx.compose.ui.unit.dp
import androidx.compose.ui.unit.sp
import club.campbuddy.shared.model.SocialFace
import club.campbuddy.shared.model.SocialKitData
import club.campbuddy.shared.model.SocialPerson
import club.campbuddy.shared.model.SocialSession
import club.campbuddy.shared.model.SocialTableCard
import kotlin.math.roundToInt

// The pictures of the Social media tab, drawn on the phone: the three sizes of the website's kit (portrait feed,
// square, story), its seven post designs and seven people-card designs, in the event's brand colours with the
// site's artwork and a QR to the event. Words, captions and links come from the server (SocialKit).

/** The website's sizes: width × height in pixels at "social" quality. */
internal enum class CardSize(val key: String, val label: String, val w: Int, val h: Int) {
    Portrait("1080x1350", "Portrait 4:5 (feed)", 1080, 1350),
    Square("1080x1080", "Square 1:1", 1080, 1080),
    Story("1080x1920", "Story 9:16", 1080, 1920);

    val ratio: Float get() = w.toFloat() / h
    val tall: Boolean get() = this == Story
}

internal val POST_DESIGNS = listOf("daylight" to "Daylight", "classic" to "Classic", "poster" to "Poster", "frame" to "Frame", "ticket" to "Ticket", "magazine" to "Magazine", "split" to "Split", "minimal" to "Minimal")
internal val PEOPLE_DESIGNS = listOf("daylight" to "Daylight", "classic" to "Classic", "polaroid" to "Polaroid", "badge" to "Badge", "split" to "Split", "minimal" to "Minimal", "spotlight" to "Spotlight", "arch" to "Arch")

internal fun hex(value: String, fallback: Color): Color {
    val v = value.removePrefix("#").takeIf { it.length == 6 }?.toLongOrNull(16) ?: return fallback
    return Color(0xFF000000 or v)
}

/**
 * Draws on screen as usual, and records the same drawing into [layer] at [exportWidth] pixels wide (a vector
 * replay, so words stay sharp at print size); `layer.toImageBitmap()` then gives the full-size picture.
 */
internal fun Modifier.captureAt(layer: GraphicsLayer, exportWidth: Int): Modifier = this.drawWithContent {
    drawContent()
    if (size.width <= 0f) return@drawWithContent
    val factor = exportWidth / size.width
    layer.record(size = IntSize(exportWidth, (size.height * factor).roundToInt())) {
        scale(factor, factor, pivot = Offset.Zero) { this@drawWithContent.drawContent() }
    }
}

/** The four brand colours, and the readable text colour on any of them. */
internal class Pal(kit: SocialKitData) {
    val primary = hex(kit.colors.primary, Color(0xFF7A1F2B))
    val secondary = hex(kit.colors.secondary, Color(0xFFE0A11B))
    val ink = hex(kit.colors.ink, Color(0xFF231F20))
    val paper = hex(kit.colors.paper, Color(0xFFFFF8EE))
    val extras = kit.site.colors.map { hex(it, primary) }.filter { it.luminance() in 0.08f..0.6f }

    fun on(bg: Color): Color = if (bg.luminance() > 0.45f) ink else paper

    /** A person's tone by first role: organizers the main colour, speakers the accent, the rest the site's others. */
    fun role(roles: List<String>): Color = when (roles.firstOrNull()) {
        "organizer", null -> primary
        "speaker" -> secondary
        "volunteer", "table_lead" -> extras.getOrNull(0) ?: Color(0xFF1F7A6D)
        "sponsor" -> extras.getOrNull(1) ?: Color(0xFF3B5BA9)
        "media_partner" -> extras.getOrNull(2) ?: Color(0xFF6D3FA0)
        else -> extras.getOrNull(3) ?: primary
    }
}

/** The card's words at fixed sizes: the phone's own text-size setting must not reflow a designed picture. */
@Composable
internal fun FixedText(content: @Composable () -> Unit) {
    val d = LocalDensity.current
    CompositionLocalProvider(LocalDensity provides Density(d.density, 1f), content = content)
}

private fun st(size: TextUnit, color: Color, weight: FontWeight = FontWeight.Normal, line: TextUnit = size * 1.18f, spacing: TextUnit = 0.sp) =
    TextStyle(fontSize = size, color = color, fontWeight = weight, lineHeight = line, letterSpacing = spacing)

// ---- shared parts ------------------------------------------------------------------------------------------

@Composable
private fun EventMark(kit: SocialKitData, bg: Color, fg: Color, text: Color) {
    Row(verticalAlignment = Alignment.CenterVertically, horizontalArrangement = Arrangement.spacedBy(8.dp)) {
        Box(Modifier.size(32.dp).background(bg, CircleShape).padding(5.dp), contentAlignment = Alignment.Center) {
            RemoteImage(kit.event.mark ?: kit.event.logo, Modifier.size(22.dp)) { Text(kit.event.short.take(1), style = st(15.sp, fg, FontWeight.Bold)) }
        }
        Text(kit.event.short.ifBlank { kit.event.name }, style = st(13.sp, text, FontWeight.Bold), maxLines = 1, overflow = TextOverflow.Ellipsis)
    }
}

/** The foot of every card: the QR (with this card's utm_content) and why to scan it. */
@Composable
private fun QrFoot(kit: SocialKitData, pal: Pal, qr: String, size: CardSize, line: String = "Open it in CampBuddy") {
    Row(
        Modifier.fillMaxWidth().background(pal.paper, RoundedCornerShape(10.dp)).border(1.dp, pal.ink.copy(alpha = 0.08f), RoundedCornerShape(10.dp)).padding(8.dp),
        verticalAlignment = Alignment.CenterVertically, horizontalArrangement = Arrangement.spacedBy(10.dp),
    ) {
        QrCodeView(qr, size = if (size.tall) 72.dp else 56.dp, dark = pal.ink)
        Column(Modifier.weight(1f)) {
            Text(line, style = st(13.sp, pal.ink, FontWeight.Bold), maxLines = 2)
            if (kit.event.hashtags.isNotBlank()) Text(kit.event.hashtags, style = st(10.sp, pal.primary, FontWeight.SemiBold), maxLines = 1, overflow = TextOverflow.Ellipsis)
        }
    }
}

/** The site's pattern or illustration, faint, behind a coloured card; else two soft circles. */
@Composable
private fun BoxScope.Backdrop(kit: SocialKitData, bg: Color, shape: Color, artIndex: Int) {
    val pieces = kit.site.art.filter { it.kind == "pattern" || it.kind == "illustration" }
    val art = pieces.getOrNull(artIndex % maxOf(1, pieces.size))
    Box(Modifier.matchParentSize().background(bg))
    if (art != null) {
        RemoteImage(art.url, Modifier.matchParentSize(), contentScale = ContentScale.Crop) {}
        Box(Modifier.matchParentSize().background(bg.copy(alpha = 0.84f)))
    } else {
        Box(Modifier.size(220.dp).align(Alignment.TopEnd).offset(x = 70.dp, y = (-70).dp).background(shape.copy(alpha = 0.22f), CircleShape))
        Box(Modifier.size(150.dp).align(Alignment.BottomStart).offset(x = (-50).dp, y = (-60).dp).background(shape.copy(alpha = 0.15f), CircleShape))
    }
}

private fun initials(name: String) = name.split(' ').mapNotNull { it.firstOrNull()?.uppercase() }.take(2).joinToString("")

@Composable
private fun Photo(url: String?, name: String, size: androidx.compose.ui.unit.Dp, bg: Color, fg: Color, shape: androidx.compose.ui.graphics.Shape = CircleShape, ring: Color? = null) {
    Box(
        Modifier.size(size).clip(shape).background(bg).let { if (ring != null) it.border(3.dp, ring, shape) else it },
        contentAlignment = Alignment.Center,
    ) {
        RemoteImage(url, Modifier.size(size), contentScale = ContentScale.Crop) { Text(initials(name), style = st((size.value / 3).sp, fg, FontWeight.ExtraBold)) }
    }
}

// ---- posts -----------------------------------------------------------------------------------------------

/** What a post shows, worked out by the tab (one page of it when the list needs several cards). */
internal data class PostContent(
    val key: String,
    val kicker: String,
    val headline: String,
    val line: String,
    val body: String,
    val qr: String,
    val days: Int? = null,
    val faces: List<SocialFace> = emptyList(),
    val sessions: List<SocialSession> = emptyList(),
    val tables: List<SocialTableCard> = emptyList(),
    val session: SocialSession? = null,
    val page: Int = 1,
    val pages: Int = 1,
)

private val FEATURES = listOf("The full schedule, with reminders", "People who share your interests", "Your Contributor Day team", "Deals and free tools for you")

/** The colours a design gives the words: on its background, and on the paper panel some designs use. */
private class Tones(val head: Color, val sub: Color, val accent: Color, val panel: Color? = null)

@Composable
internal fun PostPicture(kit: SocialKitData, c: PostContent, design: String, size: CardSize, artIndex: Int) {
    val pal = Pal(kit)
    FixedText {
        Box(Modifier.fillMaxWidth().aspectRatio(size.ratio).clip(RoundedCornerShape(8.dp))) {
            when (design) {
                "daylight" -> DaylightPost(kit, pal, c, size) { ink, muted, accent -> PostBody(pal, c, size, Tones(ink, muted, accent), false) }
                "poster" -> {
                    Backdrop(kit, pal.secondary, pal.primary, artIndex)
                    PostColumn(kit, pal, c, size, Tones(pal.on(pal.secondary), pal.on(pal.secondary).copy(alpha = 0.85f), pal.primary), big = true, center = true)
                }
                "frame" -> {
                    Box(Modifier.matchParentSize().background(pal.paper))
                    Box(Modifier.matchParentSize().padding(10.dp).border(6.dp, pal.primary, RoundedCornerShape(6.dp)))
                    Box(Modifier.matchParentSize().padding(10.dp)) { PostColumn(kit, pal, c, size, Tones(pal.ink, pal.ink.copy(alpha = 0.75f), pal.primary)) }
                }
                "ticket" -> {
                    Backdrop(kit, pal.primary, pal.secondary, artIndex)
                    Box(Modifier.matchParentSize().padding(14.dp).background(pal.paper, RoundedCornerShape(14.dp))) {
                        // The ticket's two notches.
                        Box(Modifier.size(26.dp).align(Alignment.CenterStart).offset(x = (-13).dp).background(pal.primary, CircleShape))
                        Box(Modifier.size(26.dp).align(Alignment.CenterEnd).offset(x = 13.dp).background(pal.primary, CircleShape))
                        PostColumn(kit, pal, c, size, Tones(pal.ink, pal.ink.copy(alpha = 0.75f), pal.primary))
                    }
                }
                "magazine" -> {
                    Box(Modifier.matchParentSize().background(pal.paper))
                    Box(Modifier.fillMaxHeight().width(12.dp).background(pal.primary))
                    Box(Modifier.fillMaxWidth().height(10.dp).align(Alignment.TopCenter).background(pal.secondary))
                    Box(Modifier.matchParentSize().padding(start = 12.dp)) { PostColumn(kit, pal, c, size, Tones(pal.ink, pal.ink.copy(alpha = 0.75f), pal.primary), big = true) }
                }
                "split" -> {
                    Column(Modifier.matchParentSize()) {
                        Box(Modifier.fillMaxWidth().weight(0.52f).background(pal.primary))
                        Box(Modifier.fillMaxWidth().weight(0.48f).background(pal.paper))
                    }
                    PostColumn(kit, pal, c, size, Tones(pal.paper, pal.paper.copy(alpha = 0.88f), pal.secondary, panel = pal.ink), split = true)
                }
                "minimal" -> {
                    Box(Modifier.matchParentSize().background(pal.paper))
                    // A thin rule along the top edge, clear of the event's name.
                    Box(Modifier.fillMaxWidth().height(5.dp).background(pal.primary).align(Alignment.TopCenter))
                    PostColumn(kit, pal, c, size, Tones(pal.ink, pal.ink.copy(alpha = 0.7f), pal.primary))
                }
                else -> {
                    Backdrop(kit, pal.primary, pal.secondary, artIndex)
                    PostColumn(kit, pal, c, size, Tones(pal.paper, pal.paper.copy(alpha = 0.88f), pal.secondary))
                }
            }
            if (c.pages > 1 && design != "daylight") Text("${c.page}/${c.pages}", style = st(11.sp, pal.ink, FontWeight.Bold), modifier = Modifier.align(Alignment.TopEnd).padding(14.dp).background(pal.paper, RoundedCornerShape(50)).padding(horizontal = 8.dp, vertical = 2.dp))
        }
    }
}

/**
 * The words and the body, top to bottom, then the QR foot. With [split] the words sit on the top (coloured) half
 * and the body on the bottom (paper) half in [Tones.panel].
 */
@Composable
private fun BoxScope.PostColumn(kit: SocialKitData, pal: Pal, c: PostContent, size: CardSize, t: Tones, big: Boolean = false, center: Boolean = false, split: Boolean = false) {
    val headSize = when { size.tall -> 34.sp; big -> 30.sp; size == CardSize.Square -> 24.sp; else -> 27.sp }
    val align = if (center) Alignment.CenterHorizontally else Alignment.Start
    val textAlign = if (center) TextAlign.Center else TextAlign.Start
    Column(Modifier.matchParentSize().padding(18.dp), verticalArrangement = Arrangement.spacedBy(8.dp), horizontalAlignment = align) {
        EventMark(kit, pal.paper, pal.primary, if (t.head == pal.ink) pal.ink else t.head)
        Column(Modifier.weight(if (split) 0.9f else 1f).fillMaxWidth(), verticalArrangement = Arrangement.spacedBy(6.dp, Alignment.CenterVertically), horizontalAlignment = align) {
            if (c.kicker.isNotBlank()) Text(c.kicker.uppercase(), style = st(12.sp, t.accent, FontWeight.Bold, spacing = 1.2.sp), maxLines = 2, textAlign = textAlign)
            if (c.body == "countdown" && c.days != null) Text(c.days.toString(), style = st(if (size.tall) 110.sp else 76.sp, t.accent, FontWeight.ExtraBold, line = if (size.tall) 112.sp else 78.sp))
            Text(c.headline.replace("*", ""), style = st(headSize, t.head, FontWeight.ExtraBold), maxLines = if (size.tall) 4 else 3, overflow = TextOverflow.Ellipsis, textAlign = textAlign)
            if (c.line.isNotBlank()) Text(c.line, style = st(14.sp, t.sub), maxLines = 2, textAlign = textAlign)
            if (!split) PostBody(pal, c, size, t, center)
        }
        if (split) Column(Modifier.weight(1f).fillMaxWidth(), verticalArrangement = Arrangement.Center) { PostBody(pal, c, size, Tones(pal.ink, pal.ink.copy(alpha = 0.75f), pal.primary), center) }
        QrFoot(kit, pal, c.qr, size)
    }
}

@OptIn(ExperimentalLayoutApi::class)
@Composable
private fun PostBody(pal: Pal, c: PostContent, size: CardSize, t: Tones, center: Boolean) {
    val arrange = if (center) Arrangement.spacedBy(6.dp, Alignment.CenterHorizontally) else Arrangement.spacedBy(6.dp)
    when (c.body) {
        "photos" -> FlowRow(horizontalArrangement = arrange, verticalArrangement = Arrangement.spacedBy(6.dp), modifier = Modifier.padding(top = 4.dp)) {
            c.faces.forEach { f ->
                Column(Modifier.width(if (size.tall) 72.dp else 58.dp), horizontalAlignment = Alignment.CenterHorizontally) {
                    Photo(f.photo, f.name, if (size.tall) 58.dp else 46.dp, pal.secondary, pal.primary, ring = pal.paper)
                    Text(f.name, style = st(9.sp, t.head, FontWeight.SemiBold), maxLines = 2, textAlign = TextAlign.Center, overflow = TextOverflow.Ellipsis)
                }
            }
        }
        "logos" -> FlowRow(horizontalArrangement = arrange, verticalArrangement = Arrangement.spacedBy(6.dp), modifier = Modifier.padding(top = 4.dp)) {
            c.faces.forEach { f ->
                Box(Modifier.size(width = 78.dp, height = 38.dp).background(Color.White, RoundedCornerShape(6.dp)).padding(5.dp), contentAlignment = Alignment.Center) {
                    RemoteImage(f.logo, Modifier.fillMaxSize()) { Text(f.name, style = st(9.sp, pal.ink, FontWeight.SemiBold), maxLines = 2, textAlign = TextAlign.Center) }
                }
            }
        }
        "features" -> Column(verticalArrangement = Arrangement.spacedBy(4.dp)) {
            FEATURES.forEach { f -> Text("✓  $f", style = st(13.sp, t.head, FontWeight.SemiBold)) }
        }
        "list" -> Column(Modifier.fillMaxWidth(), verticalArrangement = Arrangement.spacedBy(5.dp)) {
            c.sessions.forEach { s ->
                Row(horizontalArrangement = Arrangement.spacedBy(8.dp)) {
                    Text(s.time, style = st(11.sp, t.accent, FontWeight.Bold), modifier = Modifier.width(58.dp))
                    Column(Modifier.weight(1f)) {
                        Text(s.title, style = st(12.sp, t.head, FontWeight.Bold), maxLines = 2, overflow = TextOverflow.Ellipsis)
                        val meta = listOfNotNull(s.speakers.joinToString(", ").ifBlank { null }, s.track).joinToString(" · ")
                        if (meta.isNotBlank()) Text(meta, style = st(10.sp, t.sub), maxLines = 1, overflow = TextOverflow.Ellipsis)
                    }
                }
            }
        }
        "session" -> c.session?.takeIf { it.speakers.isNotEmpty() }?.let { s ->
            Row(horizontalArrangement = Arrangement.spacedBy(8.dp), verticalAlignment = Alignment.CenterVertically) {
                s.speakers.take(3).forEachIndexed { i, name -> Photo(s.photos.getOrNull(i), name, 48.dp, pal.secondary, pal.primary, ring = pal.paper) }
                Text(s.speakers.joinToString(", "), style = st(12.sp, t.head, FontWeight.SemiBold), maxLines = 2)
            }
        }
        "tables" -> Column(Modifier.fillMaxWidth(), verticalArrangement = Arrangement.spacedBy(5.dp)) {
            c.tables.forEach { tb ->
                Column {
                    Text(tb.name, style = st(12.sp, t.head, FontWeight.Bold), maxLines = 1)
                    val meta = listOfNotNull(tb.place.ifBlank { null }, tb.leads.joinToString(", ").ifBlank { null }?.let { "Lead: $it" }).joinToString(" · ")
                    if (meta.isNotBlank()) Text(meta, style = st(10.sp, t.sub), maxLines = 1, overflow = TextOverflow.Ellipsis)
                }
            }
        }
        else -> Unit
    }
}

// ---- people ----------------------------------------------------------------------------------------------

@Composable
internal fun PersonPicture(kit: SocialKitData, p: SocialPerson, design: String, size: CardSize, qr: String) {
    val pal = Pal(kit)
    val tone = pal.role(p.roleKeys)
    val on = pal.on(tone)
    val role = (p.roles.firstOrNull() ?: p.cardRole ?: "Attendee").uppercase()
    val why = p.talk?.let { "“$it”" } ?: "Say hi at ${kit.event.short.ifBlank { kit.event.name }}!"
    val photo = if (size.tall) 168.dp else if (size == CardSize.Square) 104.dp else 132.dp
    FixedText {
        Box(Modifier.fillMaxWidth().aspectRatio(size.ratio).clip(RoundedCornerShape(8.dp))) {
            when (design) {
                "daylight" -> DaylightPerson(kit, pal, p, size, qr)
                "polaroid" -> {
                    Backdrop(kit, tone, pal.paper, 0)
                    PersonColumn(kit, pal, size, qr, on) {
                        Column(Modifier.rotate(-3f).background(Color.White).padding(start = 8.dp, end = 8.dp, top = 8.dp, bottom = 14.dp), horizontalAlignment = Alignment.CenterHorizontally) {
                            Photo(p.photo, p.name, photo, pal.secondary, pal.primary, RoundedCornerShape(2.dp))
                            Text(p.name, style = st(16.sp, pal.ink, FontWeight.ExtraBold), modifier = Modifier.padding(top = 8.dp), maxLines = 2, textAlign = TextAlign.Center)
                        }
                        Text(role, style = st(12.sp, on, FontWeight.Bold, spacing = 1.2.sp), modifier = Modifier.padding(top = 10.dp))
                        Text(why, style = st(13.sp, on.copy(alpha = 0.9f)), maxLines = 2, textAlign = TextAlign.Center, overflow = TextOverflow.Ellipsis)
                    }
                }
                "badge" -> {
                    Backdrop(kit, pal.primary, pal.secondary, 1)
                    PersonColumn(kit, pal, size, qr, pal.paper) {
                        Box(Modifier.width(18.dp).height(26.dp).background(pal.secondary))
                        Column(Modifier.fillMaxWidth(0.82f).background(pal.paper, RoundedCornerShape(14.dp)).padding(bottom = 0.dp), horizontalAlignment = Alignment.CenterHorizontally) {
                            Box(Modifier.padding(top = 12.dp).width(40.dp).height(6.dp).background(pal.ink.copy(alpha = 0.15f), RoundedCornerShape(3.dp)))
                            Box(Modifier.padding(top = 10.dp)) { Photo(p.photo, p.name, photo * 0.8f, pal.secondary, pal.primary, ring = tone) }
                            Text(p.name, style = st(19.sp, pal.ink, FontWeight.ExtraBold), modifier = Modifier.padding(horizontal = 10.dp, vertical = 6.dp), maxLines = 2, textAlign = TextAlign.Center)
                            Text(role, style = st(12.sp, on, FontWeight.Bold, spacing = 1.2.sp), modifier = Modifier.fillMaxWidth().background(tone, RoundedCornerShape(bottomStart = 14.dp, bottomEnd = 14.dp)).padding(vertical = 8.dp), textAlign = TextAlign.Center)
                        }
                    }
                }
                "split" -> {
                    Row(Modifier.matchParentSize()) {
                        Box(Modifier.weight(0.45f).fillMaxHeight().background(pal.secondary), contentAlignment = Alignment.Center) {
                            RemoteImage(p.photo, Modifier.fillMaxSize(), contentScale = ContentScale.Crop) { Text(initials(p.name), style = st(44.sp, pal.primary, FontWeight.ExtraBold)) }
                        }
                        Box(Modifier.weight(0.55f).fillMaxHeight().background(tone))
                    }
                    Column(Modifier.matchParentSize().padding(start = 0.dp)) {
                        Row(Modifier.weight(1f)) {
                            Box(Modifier.weight(0.45f))
                            Column(Modifier.weight(0.55f).fillMaxHeight().padding(14.dp), verticalArrangement = Arrangement.spacedBy(6.dp, Alignment.CenterVertically)) {
                                EventMark(kit, pal.paper, pal.primary, on)
                                Text(role, style = st(11.sp, on.copy(alpha = 0.85f), FontWeight.Bold, spacing = 1.2.sp))
                                Text(p.name, style = st(22.sp, on, FontWeight.ExtraBold), maxLines = 3)
                                Text(why, style = st(12.sp, on.copy(alpha = 0.9f)), maxLines = 4, overflow = TextOverflow.Ellipsis)
                            }
                        }
                        Box(Modifier.padding(14.dp)) { QrFoot(kit, pal, qr, size, "Find ${p.name.trim()} in CampBuddy") }
                    }
                }
                "minimal" -> {
                    Box(Modifier.matchParentSize().background(pal.paper))
                    Column(Modifier.matchParentSize().padding(18.dp), verticalArrangement = Arrangement.spacedBy(8.dp)) {
                        EventMark(kit, pal.primary.copy(alpha = 0.1f), pal.primary, pal.ink)
                        Column(Modifier.weight(1f), verticalArrangement = Arrangement.spacedBy(8.dp, Alignment.CenterVertically)) {
                            Photo(p.photo, p.name, photo * 0.7f, tone, pal.on(tone))
                            Text(role, style = st(12.sp, tone, FontWeight.Bold, spacing = 1.2.sp))
                            Text(p.name, style = st(28.sp, pal.ink, FontWeight.ExtraBold), maxLines = 3)
                            Text(why, style = st(13.sp, pal.ink.copy(alpha = 0.75f)), maxLines = 3, overflow = TextOverflow.Ellipsis)
                        }
                        QrFoot(kit, pal, qr, size, "Find ${p.name.trim()} in CampBuddy")
                    }
                }
                "spotlight" -> {
                    Box(Modifier.matchParentSize().background(tone))
                    Box(Modifier.fillMaxWidth().fillMaxHeight(0.62f).background(pal.secondary), contentAlignment = Alignment.Center) {
                        RemoteImage(p.photo, Modifier.fillMaxSize(), contentScale = ContentScale.Crop) { Text(initials(p.name), style = st(64.sp, pal.primary, FontWeight.ExtraBold)) }
                        Box(Modifier.fillMaxSize().background(Brush.verticalGradient(0.55f to Color.Transparent, 1f to tone)))
                    }
                    Column(Modifier.matchParentSize().padding(18.dp), verticalArrangement = Arrangement.spacedBy(6.dp)) {
                        EventMark(kit, pal.paper, pal.primary, Color.White)
                        Box(Modifier.weight(1f))
                        Text(role, style = st(12.sp, on.copy(alpha = 0.9f), FontWeight.Bold, spacing = 1.2.sp))
                        Text(p.name, style = st(28.sp, on, FontWeight.ExtraBold), maxLines = 2)
                        Text(why, style = st(13.sp, on.copy(alpha = 0.9f)), maxLines = 2, overflow = TextOverflow.Ellipsis)
                        QrFoot(kit, pal, qr, size, "Find ${p.name.trim()} in CampBuddy")
                    }
                }
                "arch" -> {
                    Box(Modifier.matchParentSize().background(pal.paper))
                    PersonColumn(kit, pal, size, qr, pal.ink, markBg = pal.primary.copy(alpha = 0.1f)) {
                        Photo(p.photo, p.name, photo, pal.secondary, pal.primary, RoundedCornerShape(topStartPercent = 50, topEndPercent = 50, bottomStartPercent = 6, bottomEndPercent = 6), ring = tone)
                        Text(role, style = st(12.sp, tone, FontWeight.Bold, spacing = 1.2.sp), modifier = Modifier.padding(top = 10.dp))
                        Text(p.name, style = st(24.sp, pal.ink, FontWeight.ExtraBold), maxLines = 2, textAlign = TextAlign.Center)
                        Text(why, style = st(13.sp, pal.ink.copy(alpha = 0.75f)), maxLines = 2, textAlign = TextAlign.Center, overflow = TextOverflow.Ellipsis)
                    }
                }
                else -> {
                    Backdrop(kit, tone, pal.paper, 0)
                    Column(Modifier.matchParentSize().padding(18.dp), verticalArrangement = Arrangement.spacedBy(8.dp)) {
                        EventMark(kit, pal.paper, pal.primary, on)
                        Row(Modifier.weight(1f).fillMaxWidth(), verticalAlignment = Alignment.CenterVertically, horizontalArrangement = Arrangement.spacedBy(14.dp)) {
                            Photo(p.photo, p.name, photo * 0.85f, pal.secondary, pal.primary, ring = pal.paper)
                            Column(Modifier.weight(1f), verticalArrangement = Arrangement.spacedBy(4.dp)) {
                                Text(role, style = st(12.sp, on.copy(alpha = 0.85f), FontWeight.Bold, spacing = 1.2.sp))
                                Text(p.name, style = st(24.sp, on, FontWeight.ExtraBold), maxLines = 3)
                                Text(why, style = st(13.sp, on.copy(alpha = 0.9f)), maxLines = 3, overflow = TextOverflow.Ellipsis)
                            }
                        }
                        QrFoot(kit, pal, qr, size, "Find ${p.name.trim()} in CampBuddy")
                    }
                }
            }
        }
    }
}

/** Mark on top, the design's middle centred, the QR foot at the bottom. */
@Composable
private fun BoxScope.PersonColumn(kit: SocialKitData, pal: Pal, size: CardSize, qr: String, text: Color, markBg: Color = pal.paper, middle: @Composable ColumnScope.() -> Unit) {
    Column(Modifier.matchParentSize().padding(18.dp), verticalArrangement = Arrangement.spacedBy(8.dp)) {
        EventMark(kit, markBg, pal.primary, text)
        Column(Modifier.weight(1f).fillMaxWidth(), horizontalAlignment = Alignment.CenterHorizontally, verticalArrangement = Arrangement.Center, content = middle)
        QrFoot(kit, pal, qr, size)
    }
}
