package club.campbuddy.shared.ui

import androidx.compose.foundation.Image
import androidx.compose.foundation.background
import androidx.compose.foundation.border
import androidx.compose.foundation.layout.Arrangement
import androidx.compose.foundation.layout.Box
import androidx.compose.foundation.layout.BoxWithConstraints
import androidx.compose.foundation.layout.Column
import androidx.compose.foundation.layout.ExperimentalLayoutApi
import androidx.compose.foundation.layout.FlowRow
import androidx.compose.foundation.layout.Row
import androidx.compose.foundation.layout.Spacer
import androidx.compose.foundation.layout.aspectRatio
import androidx.compose.foundation.layout.fillMaxSize
import androidx.compose.foundation.layout.fillMaxWidth
import androidx.compose.foundation.layout.height
import androidx.compose.foundation.layout.padding
import androidx.compose.foundation.layout.size
import androidx.compose.foundation.layout.width
import androidx.compose.foundation.shape.CircleShape
import androidx.compose.foundation.shape.RoundedCornerShape
import androidx.compose.material3.Text
import androidx.compose.runtime.Composable
import androidx.compose.runtime.remember
import androidx.compose.ui.Alignment
import androidx.compose.ui.Modifier
import androidx.compose.ui.draw.clip
import androidx.compose.ui.draw.drawBehind
import androidx.compose.ui.draw.drawWithContent
import androidx.compose.ui.geometry.Offset
import androidx.compose.ui.graphics.Color
import androidx.compose.ui.graphics.Path
import androidx.compose.ui.graphics.PathEffect
import androidx.compose.ui.graphics.drawscope.Stroke
import androidx.compose.ui.text.TextStyle
import androidx.compose.ui.text.font.FontFamily
import androidx.compose.ui.text.font.FontStyle
import androidx.compose.ui.text.font.FontVariation
import androidx.compose.ui.text.font.FontWeight
import androidx.compose.ui.text.style.TextAlign
import androidx.compose.ui.unit.Dp
import androidx.compose.ui.unit.dp
import androidx.compose.ui.unit.sp
import campbuddy_native.shared.generated.resources.Res
import campbuddy_native.shared.generated.resources.app_icon
import campbuddy_native.shared.generated.resources.inter_bold
import campbuddy_native.shared.generated.resources.inter_extrabold
import campbuddy_native.shared.generated.resources.inter_medium
import campbuddy_native.shared.generated.resources.jetbrains_mono
import campbuddy_native.shared.generated.resources.pattern_icons
import campbuddy_native.shared.generated.resources.playfair
import campbuddy_native.shared.generated.resources.playfair_italic
import campbuddy_native.shared.generated.resources.wordmark
import club.campbuddy.shared.domain.CampCard
import org.jetbrains.compose.resources.Font
import org.jetbrains.compose.resources.painterResource

val CAMP_CARD_LAYOUTS = listOf("ticket" to "Ticket", "classic" to "Classic", "minimal" to "Minimal", "bold" to "Bold", "split" to "Split", "badge" to "Badge", "pass" to "Pass")

/** Everything a face needs to draw: the event, the person and the link the QR opens. */
class CardFaceData(val event: String, val eventLogo: String?, val eventMark: String?, val card: CampCard, val sample: Boolean)

private val WINE = Color(0xFF721313)
private val CHARCOAL = Color(0xFF1E1E1E)
private val OCHRE = Color(0xFFD77B06)
private val ANTIQUE = Color(0xFFFBECD8)
private val PAPER = Color(0xFFFFFAF4)

@Composable
private fun serif(): FontFamily = FontFamily(Font(Res.font.playfair, FontWeight.Bold, variationSettings = FontVariation.Settings(FontVariation.weight(700))))

@Composable
private fun serifItalic(): FontFamily = FontFamily(Font(Res.font.playfair_italic, FontWeight.Bold, FontStyle.Italic, variationSettings = FontVariation.Settings(FontVariation.weight(700))))

@Composable
private fun mono(): FontFamily = FontFamily(Font(Res.font.jetbrains_mono, FontWeight.Normal, variationSettings = FontVariation.Settings(FontVariation.weight(400))))

@Composable
private fun sans(): FontFamily = FontFamily(Font(Res.font.inter_medium, FontWeight.Medium), Font(Res.font.inter_bold, FontWeight.Bold), Font(Res.font.inter_extrabold, FontWeight.ExtraBold))

/** One Camp Card face. The card is 3 x 5 and every size is in "design px" out of 300 across, as in the website's own stylesheet. */
@OptIn(ExperimentalLayoutApi::class)
@Composable
fun CampCardFace(layout: String, data: CardFaceData, modifier: Modifier = Modifier) {
    val brand = LocalBrand.current
    val card = data.card
    val serif = serif(); val serifItalic = serifItalic(); val mono = mono(); val sans = sans()

    BoxWithConstraints(modifier.fillMaxWidth().aspectRatio(3f / 5f)) {
        val u: Dp = maxWidth / 300f
        fun Number.d(): Dp = u * this.toFloat()
        fun Number.s() = (u * this.toFloat()).value.sp

        val shape = RoundedCornerShape(if (layout == "ticket") 16.d() else 8.d())
        val dark = layout == "classic" || layout == "bold"
        val bg = when (layout) { "ticket" -> PAPER; "classic", "bold" -> CHARCOAL; "minimal" -> ANTIQUE; "split" -> OCHRE; "badge" -> Color.White; else -> Color(0xFFFAEAD7) }
        val centered = layout == "minimal" || layout == "badge" || layout == "pass" || layout == "ticket"
        val name = card.name.ifBlank { "Your name" }
        val tags = (if ("interests" in card.visible) card.interests else emptyList()) + (if ("askMeAbout" in card.visible && card.askMeAbout.isNotBlank()) listOf(card.askMeAbout) else emptyList())
        val roleLine = listOfNotNull(
            card.role.takeIf { "role" in card.visible && it.isNotBlank() },
            card.company.takeIf { "company" in card.visible && it.isNotBlank() },
            card.city.takeIf { "city" in card.visible && it.isNotBlank() },
        ).joinToString(" · ")
        val ink = when { dark -> Color.White; layout == "split" -> CHARCOAL; else -> Color(0xFF231F20) }
        val soft = when { dark -> Color(0xFFD9CFC6); layout == "split" -> Color(0xFF3A2400); else -> Color(0xFF6B625E) }

        Box(
            Modifier.fillMaxSize().clip(shape).background(bg)
                .let { if (layout == "minimal" || layout == "badge" || layout == "pass" || layout == "split") it.border(2.d(), Color(0xFF231F20), shape) else it }
                .drawWithContent {
                    drawContent()
                    if (layout == "classic") {
                        // faint diagonal stripes
                        var x = -size.height
                        while (x < size.width) { drawLine(Color(0x1AC33A19), Offset(x, size.height), Offset(x + size.height, 0f), 1.d().toPx()); x += 22.d().toPx() }
                    }
                    if (layout == "pass") {
                        val corner = 60.d().toPx()
                        drawPath(Path().apply { moveTo(0f, 0f); lineTo(corner, 0f); lineTo(0f, corner); close() }, WINE)
                        drawPath(Path().apply { moveTo(size.width, 0f); lineTo(size.width - corner, 0f); lineTo(size.width, corner); close() }, OCHRE)
                    }
                },
        ) {
            // The lanyard hole on the hanging layouts.
            if (layout == "minimal" || layout == "badge" || layout == "pass") {
                Box(Modifier.align(Alignment.TopCenter).padding(top = 18.d()).size(18.d()).border(2.d(), Color(0xFF231F20).copy(alpha = 0.35f), CircleShape).background(if (layout == "badge") Color(0xFFFFE4C4) else Color.White.copy(alpha = 0.6f), CircleShape))
            }

            if (layout == "ticket") {
                // The wine band with its mark, the event name, rings and the tear line.
                Box(
                    Modifier.fillMaxWidth().height(136.d()).clip(androidx.compose.ui.graphics.RectangleShape).background(WINE).patternBackground(Res.drawable.pattern_icons, null, alpha = 0.45f)
                        .drawWithContent {
                            drawContent()
                            val c = Offset(size.width / 2, 48.d().toPx())
                            listOf(60f, 84f, 108f).forEach { r -> drawCircle(Color.White.copy(alpha = 0.10f), r.d().toPx(), c, style = Stroke(1.d().toPx())) }
                            val y = size.height - 1.d().toPx()
                            drawLine(Color.White.copy(alpha = 0.55f), Offset(14.d().toPx(), y), Offset(size.width - 14.d().toPx(), y), 1.d().toPx(), pathEffect = PathEffect.dashPathEffect(floatArrayOf(6.d().toPx(), 5.d().toPx())))
                            drawCircle(PAPER, 8.d().toPx(), Offset(0f, size.height))
                            drawCircle(PAPER, 8.d().toPx(), Offset(size.width, size.height))
                        },
                    contentAlignment = Alignment.TopCenter,
                ) {
                    Column(horizontalAlignment = Alignment.CenterHorizontally, modifier = Modifier.padding(top = 20.d())) {
                        Box(Modifier.size(58.d()).background(Color.White, RoundedCornerShape(14.d())).border(3.d(), brand.accent, RoundedCornerShape(14.d())), contentAlignment = Alignment.Center) {
                            RemoteImage(data.eventMark ?: data.eventLogo, Modifier.size(46.d())) { Image(painterResource(Res.drawable.app_icon), null, Modifier.size(44.d())) }
                        }
                        Text(
                            data.event.uppercase(), textAlign = TextAlign.Center, modifier = Modifier.padding(top = 10.d(), start = 20.d(), end = 20.d()),
                            style = TextStyle(fontFamily = sans, fontWeight = FontWeight.Bold, fontSize = 11.s(), letterSpacing = 2.5.s(), color = Color(0xFFF4E4C8), lineHeight = 15.s()),
                        )
                    }
                }
            } else if (layout == "pass") {
                // the dark red tier band along the bottom
                Box(Modifier.align(Alignment.BottomCenter).fillMaxWidth().height(40.d()).background(WINE), contentAlignment = Alignment.Center) {
                    Text("Code is Poetry", style = TextStyle(fontFamily = serifItalic, fontStyle = FontStyle.Italic, fontWeight = FontWeight.Bold, fontSize = 16.s(), color = Color.White))
                }
            }

            val top = when (layout) { "ticket" -> 136.d(); "minimal", "badge", "pass" -> 78.d(); else -> 92.d() }
            Column(
                Modifier.fillMaxSize().padding(top = top, start = 24.d(), end = 24.d(), bottom = if (layout == "pass") 48.d() else 18.d()),
                horizontalAlignment = if (centered) Alignment.CenterHorizontally else Alignment.Start,
            ) {
                // the event, small (every layout but the ticket, which has it on the band)
                if (layout != "ticket") {
                    val markColor = Color.White
                    if (layout == "minimal" || layout == "badge" || layout == "pass") {
                        Column(horizontalAlignment = Alignment.CenterHorizontally, verticalArrangement = Arrangement.spacedBy(6.d())) {
                            Box(Modifier.size(34.d()).background(markColor, CircleShape).border(1.d(), Color(0xFFEADFD6), CircleShape), contentAlignment = Alignment.Center) {
                                RemoteImage(data.eventMark ?: data.eventLogo, Modifier.size(26.d())) { Image(painterResource(Res.drawable.app_icon), null, Modifier.size(26.d())) }
                            }
                            Text(data.event.uppercase(), textAlign = TextAlign.Center, style = TextStyle(fontFamily = sans, fontWeight = FontWeight.Bold, fontSize = 11.s(), letterSpacing = 1.5.s(), color = Color(0xFF3A2F2C)))
                        }
                        if (layout == "pass") {
                            RemoteImage(data.eventLogo, Modifier.padding(top = 10.d()).height(44.d()).width(150.d())) { }
                        }
                    } else {
                        Row(horizontalArrangement = Arrangement.spacedBy(10.d()), verticalAlignment = Alignment.CenterVertically) {
                            Box(Modifier.size(34.d()).background(markColor, CircleShape), contentAlignment = Alignment.Center) {
                                RemoteImage(data.eventMark ?: data.eventLogo, Modifier.size(26.d())) { Image(painterResource(Res.drawable.app_icon), null, Modifier.size(26.d())) }
                            }
                            Text(data.event.uppercase(), style = TextStyle(fontFamily = sans, fontWeight = FontWeight.Bold, fontSize = 11.s(), letterSpacing = 1.5.s(), color = if (layout == "split") CHARCOAL else Color.White.copy(alpha = 0.92f)))
                        }
                    }
                }
                Spacer(Modifier.weight(1f))

                Text(
                    "HI, I'M", style = TextStyle(fontFamily = sans, fontWeight = FontWeight.Bold, fontSize = 11.s(), letterSpacing = 3.s(), color = if (layout == "ticket") brand.accent else soft),
                    modifier = Modifier.padding(bottom = 4.d()),
                )
                val nameStyle = when (layout) {
                    "ticket" -> TextStyle(fontFamily = sans, fontWeight = FontWeight.ExtraBold, fontSize = 23.s(), lineHeight = 27.s(), color = WINE)
                    "classic", "minimal", "split", "pass" -> TextStyle(fontFamily = serif, fontWeight = FontWeight.Bold, fontSize = 24.s(), lineHeight = 28.s(), color = ink)
                    "bold" -> TextStyle(fontFamily = sans, fontWeight = FontWeight.ExtraBold, fontSize = 27.s(), lineHeight = 29.s(), color = OCHRE)
                    else -> TextStyle(fontFamily = sans, fontWeight = FontWeight.ExtraBold, fontSize = 27.s(), lineHeight = 29.s(), color = Color(0xFF231F20))
                }
                Text(if (layout == "bold" || layout == "badge") name.uppercase() else name, style = nameStyle, textAlign = if (centered) TextAlign.Center else TextAlign.Start)
                if (layout == "ticket") Box(Modifier.padding(vertical = 10.d()).size(width = 30.d(), height = 3.d()).background(brand.accent, RoundedCornerShape(2.d())))
                if (roleLine.isNotBlank()) {
                    val roleStyle = when (layout) {
                        "classic" -> TextStyle(fontFamily = serifItalic, fontStyle = FontStyle.Italic, fontWeight = FontWeight.Bold, fontSize = 15.s(), color = OCHRE)
                        "minimal", "pass" -> TextStyle(fontFamily = mono, fontSize = 13.s(), color = soft)
                        else -> TextStyle(fontFamily = sans, fontSize = 15.s(), color = soft)
                    }
                    Text(roleLine, style = roleStyle, textAlign = if (centered) TextAlign.Center else TextAlign.Start, modifier = Modifier.padding(top = 6.d()))
                }
                if (tags.isNotEmpty()) {
                    if (layout == "bold") {
                        Row(Modifier.padding(top = 14.d()).fillMaxWidth().background(Color(0xFF2B2B2B)).drawBehind { drawRect(OCHRE, size = androidx.compose.ui.geometry.Size(2.d().toPx(), size.height)) }.padding(horizontal = 14.d(), vertical = 10.d()), horizontalArrangement = Arrangement.spacedBy(14.d())) {
                            tags.take(4).forEach { Text(it, style = TextStyle(fontFamily = sans, fontSize = 12.s(), color = Color.White)) }
                        }
                    } else {
                        FlowRow(Modifier.padding(top = 12.d()), horizontalArrangement = Arrangement.spacedBy(6.d(), if (centered) Alignment.CenterHorizontally else Alignment.Start), verticalArrangement = Arrangement.spacedBy(6.d())) {
                            tags.take(6).forEach { tag ->
                                val (pillBg, pillInk, pillBorder) = when (layout) {
                                    "classic" -> Triple(Color(0xFF383838), Color.White, Color.Transparent)
                                    "split" -> Triple(Color.White, CHARCOAL, Color.Transparent)
                                    "pass" -> Triple(Color(0xFFF0D9C6), WINE, Color.Transparent)
                                    else -> Triple(Color(0xFFFFEFDC), WINE, Color(0xFFEADFD6))
                                }
                                Text(tag, style = TextStyle(fontFamily = sans, fontWeight = FontWeight.Medium, fontSize = 12.s(), color = pillInk), modifier = Modifier.background(pillBg, RoundedCornerShape(50)).border(1.d(), pillBorder, RoundedCornerShape(50)).padding(horizontal = 10.d(), vertical = 4.d()))
                            }
                        }
                    }
                }
                Spacer(Modifier.weight(1f))

                // The QR footer, once there is a link for it to open.
                val link = card.qrLink
                if (link != null && !data.sample) {
                    Column(Modifier.fillMaxWidth(), horizontalAlignment = Alignment.CenterHorizontally, verticalArrangement = Arrangement.spacedBy(4.d())) {
                        Box(Modifier.background(Color.White, RoundedCornerShape(8.d())).border(1.d(), Color(0xFFEADFD6), RoundedCornerShape(8.d())).padding(5.d())) { QrCodeView(link, 74.d()) }
                        Text("Scan to connect", style = TextStyle(fontFamily = sans, fontSize = 10.s(), color = if (dark || layout == "split") Color.White.copy(alpha = 0.8f) else Color(0xFF6B625E)))
                    }
                }
            }
        }
    }
}
