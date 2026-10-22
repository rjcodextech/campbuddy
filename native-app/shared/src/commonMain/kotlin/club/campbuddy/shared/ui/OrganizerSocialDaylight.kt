package club.campbuddy.shared.ui

import androidx.compose.foundation.Canvas
import androidx.compose.foundation.Image
import androidx.compose.foundation.background
import androidx.compose.foundation.border
import androidx.compose.foundation.layout.Arrangement
import androidx.compose.foundation.layout.Box
import androidx.compose.foundation.layout.BoxScope
import androidx.compose.foundation.layout.Column
import androidx.compose.foundation.layout.ExperimentalLayoutApi
import androidx.compose.foundation.layout.FlowRow
import androidx.compose.foundation.layout.Row
import androidx.compose.foundation.layout.fillMaxSize
import androidx.compose.foundation.layout.fillMaxWidth
import androidx.compose.foundation.layout.height
import androidx.compose.foundation.layout.padding
import androidx.compose.foundation.layout.size
import androidx.compose.foundation.shape.CircleShape
import androidx.compose.foundation.shape.RoundedCornerShape
import androidx.compose.foundation.text.BasicText
import androidx.compose.foundation.text.TextAutoSize
import androidx.compose.material3.Text
import androidx.compose.runtime.Composable
import androidx.compose.ui.Alignment
import androidx.compose.ui.Modifier
import androidx.compose.ui.draw.clip
import androidx.compose.ui.draw.rotate
import androidx.compose.ui.geometry.Offset
import androidx.compose.ui.geometry.Size
import androidx.compose.ui.graphics.Brush
import androidx.compose.ui.graphics.Color
import androidx.compose.ui.graphics.Path
import androidx.compose.ui.graphics.StrokeCap
import androidx.compose.ui.graphics.drawscope.Stroke
import androidx.compose.ui.graphics.lerp
import androidx.compose.ui.graphics.luminance
import androidx.compose.ui.layout.ContentScale
import androidx.compose.ui.text.AnnotatedString
import androidx.compose.ui.text.SpanStyle
import androidx.compose.ui.text.TextStyle
import androidx.compose.ui.text.buildAnnotatedString
import androidx.compose.ui.text.font.FontWeight
import androidx.compose.ui.text.style.TextAlign
import androidx.compose.ui.unit.Dp
import androidx.compose.ui.unit.TextUnit
import androidx.compose.ui.unit.dp
import androidx.compose.ui.unit.em
import androidx.compose.ui.unit.sp
import campbuddy_native.shared.generated.resources.Res
import campbuddy_native.shared.generated.resources.wordmark
import club.campbuddy.shared.model.SocialKitData
import club.campbuddy.shared.model.SocialPerson
import org.jetbrains.compose.resources.painterResource
import kotlin.math.PI
import kotlin.math.sin

// Daylight (21 Oct 2026), the default design of the Social media tab, as on the website (social-kit.js →
// drawDaylightPost / drawDaylightPerson, docs spec/25): a Paper, Ink or Gradient base in the event's colours,
// a faint dot grid, two soft glows and thin waves above the foot; a tracked kicker after a small wave mark;
// the headline with its last word (or its *marked* words) highlighted; people as ringed circles with their
// whole name on one line; and the same foot on every card — the QR card, then the brand bar.

// Brand fonts (guideline §8): ExtraBold headlines, names and numbers in Baloo 2, every other line in Poppins.
@Composable
private fun ts(size: TextUnit, color: Color, weight: FontWeight = FontWeight.Normal, line: TextUnit = size * 1.18f, spacing: TextUnit = 0.sp) =
    TextStyle(
        fontFamily = if (weight >= FontWeight.ExtraBold) balooFamily() else poppinsFamily(),
        fontSize = size, color = color, fontWeight = weight, lineHeight = line, letterSpacing = spacing,
    )

private fun contrastOf(a: Color, b: Color): Float {
    val x = a.luminance() + 0.05f
    val y = b.luminance() + 0.05f
    return maxOf(x, y) / minOf(x, y)
}

/** The first colour that reads (4.5:1) on every ground, else the last. */
private fun readableOn(list: List<Color>, grounds: List<Color>): Color = list.firstOrNull { c -> grounds.all { contrastOf(c, it) >= 4.5f } } ?: list.last()

private class DayColors(
    val dark: Boolean, val brush: Brush, val ink: Color, val muted: Color, val accent: Color, val hlTo: Color,
    val dot: Color, val glowA: Color, val glowB: Color, val card: Color, val white: Color,
)

private val NIGHT = Color(0xFF050B09)

private fun dayColors(base: String, pal: Pal, glow: Color = pal.secondary): DayColors {
    val white = lerp(pal.paper, Color.White, 0.85f)
    if (base == "paper") {
        val accent = readableOn(listOf(pal.primary, lerp(pal.primary, pal.ink, 0.3f), pal.ink), listOf(white, pal.paper))
        return DayColors(
            dark = false, brush = Brush.verticalGradient(listOf(lerp(pal.paper, Color.White, 0.25f), white)), ink = pal.ink,
            muted = readableOn(listOf(lerp(pal.ink, white, 0.25f), pal.ink), listOf(white)), accent = accent,
            hlTo = readableOn(listOf(lerp(pal.primary, pal.ink, 0.5f), pal.ink), listOf(white)),
            dot = pal.primary, glowA = glow, glowB = pal.primary, card = white, white = white,
        )
    }
    val from = if (base == "gradient") pal.primary else lerp(pal.primary, NIGHT, 0.8f)
    val to = if (base == "gradient") lerp(pal.primary, pal.ink, 0.55f) else lerp(pal.primary, NIGHT, 0.9f)
    val light = readableOn(listOf(pal.secondary, lerp(pal.secondary, white, 0.45f), lerp(pal.primary, white, 0.7f), white), listOf(from, to))
    val brush = if (base == "gradient") Brush.linearGradient(listOf(from, to), start = Offset.Zero, end = Offset.Infinite) else Brush.verticalGradient(listOf(from, to))
    return DayColors(
        dark = true, brush = brush, ink = white, muted = readableOn(listOf(lerp(white, from, 0.2f), white), listOf(from, to)),
        accent = light, hlTo = light, dot = white, glowA = glow, glowB = light, card = white.copy(alpha = 0.1f), white = white,
    )
}

/** Paper for type-led posts, Ink for people, Gradient for the big date and the countdown. */
private fun dayBase(c: PostContent, pal: Pal): String = when {
    c.body == "photos" || c.body == "session" -> "ink"
    (c.key == "save-the-date" || c.key == "countdown") && contrastOf(pal.primary, pal.paper) >= 4.5f -> "gradient"
    else -> "paper"
}

/** The ground: base, glows, the dot grid (right half on Paper), four waves above the foot (at [wavesAt] of the height). */
@Composable
private fun BoxScope.DayGround(d: DayColors, wavesAt: Float) {
    Box(Modifier.matchParentSize().background(d.brush))
    Canvas(Modifier.matchParentSize()) {
        drawRect(Brush.radialGradient(listOf(d.glowA.copy(alpha = if (d.dark) 0.3f else 0.22f), Color.Transparent), center = Offset(size.width * 0.94f, size.height * 0.02f), radius = size.width * 0.75f))
        drawRect(Brush.radialGradient(listOf(d.glowB.copy(alpha = if (d.dark) 0.16f else 0.1f), Color.Transparent), center = Offset(size.width * 0.02f, size.height * wavesAt), radius = size.width * 0.65f))
        // 2 px dots every 26 px (at 1080 px wide).
        val step = size.width / 41.5f
        val dot = size.width / 1080f
        var y = step / 2
        while (y < size.height * 0.9f) {
            var x = if (d.dark) step / 2 else size.width / 2 + step / 2
            while (x < size.width) {
                drawRect(d.dot.copy(alpha = 0.12f), Offset(x - dot, y - dot), Size(dot * 2, dot * 2))
                x += step
            }
            y += step
        }
        for (i in 0 until 4) {
            val path = Path()
            val y0 = size.height * wavesAt - i * size.width * 0.02f
            val amp = size.width * (0.011f + i * 0.003f)
            var x = 0f
            while (x <= size.width) {
                val yy = y0 + sin((x / (size.width / 1.4f)) * PI.toFloat() * 2 + i * 0.9f) * amp
                if (x == 0f) path.moveTo(x, yy) else path.lineTo(x, yy)
                x += size.width / 100f
            }
            drawPath(path, d.accent.copy(alpha = (if (d.dark) 0.34f else 0.26f) * (1 - i * 0.22f)), style = Stroke(width = size.width / 360f, cap = StrokeCap.Round))
        }
    }
}

/** The small hand-drawn wave (~) before a kicker. */
@Composable
private fun WaveMark(color: Color, width: Dp) {
    Canvas(Modifier.size(width, width * 0.5f)) {
        val path = Path()
        for (i in 0..24) {
            val x = size.width * i / 24f
            val y = size.height / 2 + sin(i / 24f * PI.toFloat() * 2) * size.height * 0.32f
            if (i == 0) path.moveTo(x, y) else path.lineTo(x, y)
        }
        drawPath(path, color, style = Stroke(width = size.width / 9f, cap = StrokeCap.Round))
    }
}

@Composable
private fun DayKicker(text: String, color: Color, size: TextUnit, center: Boolean = false) {
    if (text.isBlank()) return
    Row(
        Modifier.fillMaxWidth(), verticalAlignment = Alignment.CenterVertically,
        horizontalArrangement = Arrangement.spacedBy(6.dp, if (center) Alignment.CenterHorizontally else Alignment.Start),
    ) {
        WaveMark(color, (size.value * 1.3f).dp)
        BasicText(text.uppercase(), style = ts(size, color, FontWeight.Bold, spacing = 0.16.em), maxLines = 1, autoSize = TextAutoSize.StepBased(minFontSize = 7.sp, maxFontSize = size))
    }
}

/** The headline with 1–3 words highlighted: *marked* words, else the last one (the website's headlineMarks). */
private fun dayHeadline(text: String, d: DayColors): AnnotatedString {
    val marked = Regex("\\*[^*\\s][^*]*\\*").containsMatchIn(text)
    var inside = false
    val words = text.split(Regex("\\s+")).filter { it.isNotEmpty() }.mapNotNull { t ->
        val clean = t.replace("*", "")
        val hl = marked && (inside || t.startsWith("*"))
        if (t.count { it == '*' } % 2 == 1) inside = !inside
        if (clean.isEmpty()) null else clean to hl
    }.toMutableList()
    if (!marked && words.isNotEmpty()) {
        words[words.lastIndex] = words.last().first to true
        if (words.size > 2 && words.last().first.count { it.isLetterOrDigit() } <= 3) words[words.lastIndex - 1] = words[words.lastIndex - 1].first to true
    }
    val hlStyle = if (d.dark) SpanStyle(color = d.accent) else SpanStyle(brush = Brush.linearGradient(listOf(d.accent, d.hlTo)))
    return buildAnnotatedString {
        words.forEachIndexed { i, (w, hl) ->
            if (i > 0) append(' ')
            if (hl) {
                pushStyle(hlStyle)
                append(w)
                pop()
            } else append(w)
        }
    }
}

/** Why scan this card, by post (the website's CTA). */
private val DAY_CTA = mapOf(
    "save-the-date" to "Plan your WordCamp in CampBuddy", "countdown" to "Save your talks before the day", "schedule" to "Scan to save your schedule",
    "app" to "Scan to open CampBuddy", "speakers" to "Save their talks, get a reminder", "sponsors" to "See their deals for attendees",
    "today" to "Save your picks, get reminders", "spotlight" to "Get a reminder before it starts", "contributor" to "Find your table in 3 questions",
    "meet" to "Find people who share your interests", "thank-you" to "Keep your day as a PDF",
)

/** The foot of every Daylight card: the QR card, then the brand bar (two pills of one height, nothing else). */
@Composable
private fun DayFoot(kit: SocialKitData, pal: Pal, d: DayColors, qr: String, cta: String, size: CardSize) {
    val shape = RoundedCornerShape(16.dp)
    val small = size == CardSize.Square
    Column(verticalArrangement = Arrangement.spacedBy(if (small) 6.dp else 10.dp)) {
        Row(
            Modifier.fillMaxWidth().background(d.card, shape).border(1.dp, if (d.dark) d.white.copy(alpha = 0.3f) else pal.ink.copy(alpha = 0.06f), shape).padding(10.dp),
            verticalAlignment = Alignment.CenterVertically, horizontalArrangement = Arrangement.spacedBy(10.dp),
        ) {
            Column(Modifier.weight(1f), verticalArrangement = Arrangement.spacedBy(2.dp)) {
                Text(cta, style = ts(if (size.tall) 15.sp else 13.sp, d.ink, FontWeight.Bold), maxLines = 2)
                BasicText("Free · no sign-up · works offline", style = ts(10.sp, d.muted), maxLines = 1, autoSize = TextAutoSize.StepBased(minFontSize = 6.sp, maxFontSize = 10.sp))
                BasicText(
                    listOf(kit.event.name, kit.event.dates).filter { it.isNotBlank() }.joinToString(" · "), style = ts(10.sp, d.ink, FontWeight.Bold), maxLines = 1,
                    autoSize = TextAutoSize.StepBased(minFontSize = 6.sp, maxFontSize = 10.sp),
                )
            }
            Box(Modifier.background(d.white, RoundedCornerShape(6.dp)).padding(3.dp)) { QrCodeView(qr, size = if (size.tall) 68.dp else if (small) 48.dp else 54.dp, dark = pal.ink) }
        }
        val pill = Modifier.height(if (small) 28.dp else 32.dp).background(d.white.copy(alpha = 0.94f), RoundedCornerShape(50)).border(1.dp, pal.ink.copy(alpha = 0.06f), RoundedCornerShape(50))
        Row(Modifier.fillMaxWidth(), horizontalArrangement = Arrangement.SpaceBetween, verticalAlignment = Alignment.CenterVertically) {
            Box(pill.padding(horizontal = 12.dp), contentAlignment = Alignment.Center) {
                Image(painterResource(Res.drawable.wordmark), "CampBuddy", Modifier.height(if (small) 14.dp else 16.dp))
            }
            Row(pill.padding(horizontal = 12.dp), verticalAlignment = Alignment.CenterVertically, horizontalArrangement = Arrangement.spacedBy(5.dp)) {
                LineIcon("globe", pal.ink, size = 12.dp)
                Text("campbuddy.club", style = ts(11.sp, pal.ink, FontWeight.SemiBold))
            }
        }
    }
}

/** The event's logo tile (left) and a chip (right). */
@Composable
private fun DayHeader(kit: SocialKitData, pal: Pal, d: DayColors, right: @Composable () -> Unit) {
    Row(Modifier.fillMaxWidth(), verticalAlignment = Alignment.CenterVertically) {
        Box(Modifier.size(width = 64.dp, height = 36.dp).background(d.white, RoundedCornerShape(10.dp)).padding(4.dp), contentAlignment = Alignment.Center) {
            RemoteImage(kit.event.logo ?: kit.event.mark, Modifier.fillMaxSize()) { Text(kit.event.short.take(6), style = ts(11.sp, pal.primary, FontWeight.Bold)) }
        }
        Box(Modifier.weight(1f))
        right()
    }
}

/** A ringed circle portrait; initials on the main-to-deep gradient when there is no photo. */
@Composable
private fun DayPortrait(url: String?, name: String, size: Dp, ring: Color, pal: Pal) {
    Box(
        Modifier.size(size).background(ring, CircleShape).padding((size.value * 0.05f).coerceAtLeast(2f).dp).clip(CircleShape)
            .background(Brush.linearGradient(listOf(pal.primary, lerp(pal.primary, pal.ink, 0.5f)))),
        contentAlignment = Alignment.Center,
    ) {
        val initials = name.split(' ').mapNotNull { it.firstOrNull()?.uppercase() }.take(2).joinToString("")
        RemoteImage(url, Modifier.fillMaxSize(), contentScale = ContentScale.Crop) { Text(initials, style = ts((size.value / 3).sp, Color.White, FontWeight.ExtraBold)) }
    }
}

/** A name on one line, whole (smaller rather than cut or split), and the role under it. */
@Composable
private fun DayName(name: String, role: String?, d: DayColors, size: TextUnit) {
    Column(Modifier.fillMaxWidth(), horizontalAlignment = Alignment.CenterHorizontally) {
        BasicText(name, style = ts(size, d.ink, FontWeight.Bold).copy(textAlign = TextAlign.Center), maxLines = 1, autoSize = TextAutoSize.StepBased(minFontSize = 6.sp, maxFontSize = size))
        if (!role.isNullOrBlank()) Text(role, style = ts(size * 0.75f, d.muted).copy(textAlign = TextAlign.Center), maxLines = 1)
    }
}

/** One to three people as cards tilted ±3°, more as an even grid with the name under each. */
@OptIn(ExperimentalLayoutApi::class)
@Composable
private fun DayPeople(people: List<Pair<String, String?>>, d: DayColors, pal: Pal, size: CardSize) {
    if (people.isEmpty()) return
    val ring = if (d.dark) d.accent else d.white
    if (people.size <= 3) {
        Row(Modifier.fillMaxWidth().padding(horizontal = 4.dp), horizontalArrangement = Arrangement.spacedBy(10.dp)) {
            people.forEachIndexed { i, (name, photo) ->
                Column(
                    Modifier.weight(1f).rotate(if (people.size == 1) 0f else if (i % 2 == 0) -3f else 3f)
                        .background(if (d.dark) d.white.copy(alpha = 0.08f) else d.white, RoundedCornerShape(16.dp)).padding(vertical = 12.dp, horizontal = 6.dp),
                    horizontalAlignment = Alignment.CenterHorizontally, verticalArrangement = Arrangement.spacedBy(6.dp),
                ) {
                    DayPortrait(photo, name, if (people.size == 1) 120.dp else if (size == CardSize.Square) 54.dp else 64.dp, ring, pal)
                    DayName(name, "Speaker", d, if (people.size == 1) 18.sp else 12.sp)
                }
            }
        }
        return
    }
    val cols = if (people.size <= 4) 2 else if (people.size <= 9) 3 else 4
    FlowRow(Modifier.fillMaxWidth(), horizontalArrangement = Arrangement.SpaceEvenly, verticalArrangement = Arrangement.spacedBy(8.dp), maxItemsInEachRow = cols) {
        people.forEach { (name, photo) ->
            Column(Modifier.fillMaxWidth(1f / cols - 0.02f), horizontalAlignment = Alignment.CenterHorizontally, verticalArrangement = Arrangement.spacedBy(4.dp)) {
                DayPortrait(photo, name, if (size.tall) 58.dp else 44.dp, ring, pal)
                DayName(name, "Speaker", d, 10.sp)
            }
        }
    }
}

/** One Daylight card of a post. [body] draws the post's list, logos, tables or features in the given colours. */
@Composable
internal fun BoxScope.DaylightPost(kit: SocialKitData, pal: Pal, c: PostContent, size: CardSize, body: @Composable (ink: Color, muted: Color, accent: Color) -> Unit) {
    val d = dayColors(dayBase(c, pal), pal)
    DayGround(d, if (size == CardSize.Square) 0.66f else 0.72f)
    val headSize = when {
        size.tall -> 32.sp
        size == CardSize.Square -> 22.sp
        else -> 26.sp
    }
    Column(Modifier.matchParentSize().padding(18.dp), verticalArrangement = Arrangement.spacedBy(8.dp)) {
        DayHeader(kit, pal, d) {
            if (c.pages > 1) {
                Text(
                    if (c.page == 1) "${c.page}/${c.pages} · SWIPE →" else "${c.page}/${c.pages}", style = ts(10.sp, if (d.dark) d.white else d.accent, FontWeight.Bold),
                    modifier = Modifier.background(if (d.dark) d.white.copy(alpha = 0.14f) else pal.primary.copy(alpha = 0.1f), RoundedCornerShape(50)).padding(horizontal = 10.dp, vertical = 4.dp),
                )
            }
        }
        Column(Modifier.weight(1f).fillMaxWidth(), verticalArrangement = Arrangement.spacedBy(6.dp)) {
            DayKicker(c.kicker, d.accent, if (size.tall) 12.sp else 11.sp)
            if (c.body == "countdown" && c.days != null) {
                Text(c.days.toString(), style = ts(if (size.tall) 110.sp else 76.sp, d.accent, FontWeight.ExtraBold, line = if (size.tall) 112.sp else 78.sp))
                Text(if (c.days == 1) "DAY TO GO" else "DAYS TO GO", style = ts(16.sp, d.ink, FontWeight.Bold, spacing = 1.sp))
            }
            Text(dayHeadline(c.headline, d), style = ts(headSize, d.ink, FontWeight.ExtraBold, line = headSize * 1.04f), maxLines = if (size.tall) 5 else 3)
            if (c.line.isNotBlank()) Text(c.line, style = ts(13.sp, d.muted), maxLines = 2)
            when (c.body) {
                "photos" -> {
                    Box(Modifier.height(6.dp))
                    DayPeople(c.faces.map { it.name to it.photo }, d, pal, size)
                }
                "session" -> c.session?.takeIf { it.speakers.isNotEmpty() }?.let { s ->
                    Box(Modifier.height(6.dp))
                    DayPeople(s.speakers.mapIndexed { i, n -> n to s.photos.getOrNull(i) }, d, pal, size)
                }
                else -> body(d.ink, d.muted, d.accent)
            }
        }
        DayFoot(kit, pal, d, c.qr, DAY_CTA[c.key] ?: "Scan to open CampBuddy", size)
    }
}

/** A Daylight people card: the portrait, the role chip, the full name (first name highlighted), the talk, the foot. */
@Composable
internal fun BoxScope.DaylightPerson(kit: SocialKitData, pal: Pal, p: SocialPerson, size: CardSize, qr: String) {
    val tone = pal.role(p.roleKeys)
    val d = dayColors("paper", pal, glow = tone)
    DayGround(d, if (size == CardSize.Square) 0.66f else 0.72f)
    val first = p.name.trim().substringBefore(' ')
    val rest = p.name.trim().substringAfter(' ', "")
    val event = kit.event.short.ifBlank { kit.event.name }
    val roleLabel = p.roles.firstOrNull() ?: p.cardRole
    val nameSize = if (size.tall) 32.sp else 26.sp
    Column(Modifier.matchParentSize().padding(18.dp), verticalArrangement = Arrangement.spacedBy(8.dp)) {
        DayHeader(kit, pal, d) {
            if (roleLabel != null) {
                Text(
                    roleLabel.uppercase(), style = ts(10.sp, pal.on(tone), FontWeight.Bold, spacing = 1.sp),
                    modifier = Modifier.background(tone, RoundedCornerShape(50)).padding(horizontal = 10.dp, vertical = 4.dp),
                )
            }
        }
        Column(Modifier.weight(1f).fillMaxWidth(), horizontalAlignment = Alignment.CenterHorizontally, verticalArrangement = Arrangement.spacedBy(6.dp, Alignment.CenterVertically)) {
            DayPortrait(p.photo, p.name, if (size.tall) 170.dp else if (size == CardSize.Square) 80.dp else 112.dp, d.white, pal)
            DayKicker(if (roleLabel != null) "$roleLabel at $event" else "See you at $event", d.accent, 11.sp, center = true)
            BasicText(
                dayHeadline("*$first* $rest".trim(), d), style = ts(nameSize, d.ink, FontWeight.ExtraBold).copy(textAlign = TextAlign.Center), maxLines = 1,
                autoSize = TextAutoSize.StepBased(minFontSize = 10.sp, maxFontSize = nameSize),
            )
            // Two lines at most, smaller rather than cut (the QR card is right below).
            p.talk?.takeIf { it.isNotBlank() }?.let { BasicText("“$it”", style = ts(12.sp, d.muted).copy(textAlign = TextAlign.Center), maxLines = 2, autoSize = TextAutoSize.StepBased(minFontSize = 7.sp, maxFontSize = 12.sp)) }
        }
        DayFoot(kit, pal, d, qr, if (!p.talk.isNullOrBlank()) "Save $first's talk and get a reminder" else "Meet $first at $event", size)
    }
}
