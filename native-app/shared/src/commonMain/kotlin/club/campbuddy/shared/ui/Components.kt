package club.campbuddy.shared.ui

import androidx.compose.foundation.BorderStroke
import androidx.compose.foundation.Canvas
import androidx.compose.foundation.background
import androidx.compose.foundation.border
import androidx.compose.foundation.clickable
import androidx.compose.foundation.layout.Arrangement
import androidx.compose.foundation.layout.Box
import androidx.compose.foundation.layout.Column
import androidx.compose.foundation.layout.Row
import androidx.compose.ui.geometry.Offset
import androidx.compose.runtime.setValue
import androidx.compose.runtime.remember
import androidx.compose.runtime.mutableStateOf
import androidx.compose.runtime.getValue
import androidx.compose.foundation.layout.fillMaxWidth
import androidx.compose.foundation.layout.heightIn
import androidx.compose.foundation.layout.padding
import androidx.compose.foundation.layout.Spacer
import androidx.compose.foundation.layout.requiredSize
import androidx.compose.foundation.layout.size
import androidx.compose.foundation.layout.width
import androidx.compose.foundation.shape.CircleShape
import androidx.compose.ui.draw.clip
import androidx.compose.ui.unit.sp
import androidx.compose.foundation.shape.RoundedCornerShape
import androidx.compose.material3.Button
import androidx.compose.material3.ButtonDefaults
import androidx.compose.material3.CircularProgressIndicator
import androidx.compose.material3.FilterChip
import androidx.compose.material3.FilterChipDefaults
import androidx.compose.material3.TextButton
import androidx.compose.material3.MaterialTheme
import androidx.compose.material3.OutlinedButton
import androidx.compose.material3.Text
import androidx.compose.runtime.Composable
import androidx.compose.ui.Alignment
import androidx.compose.ui.Modifier
import androidx.compose.ui.draw.shadow
import androidx.compose.ui.draw.drawBehind
import androidx.compose.ui.graphics.Color
import androidx.compose.ui.graphics.StrokeCap
import androidx.compose.ui.graphics.StrokeJoin
import androidx.compose.ui.graphics.drawscope.Stroke
import androidx.compose.ui.graphics.vector.PathParser
import androidx.compose.ui.semantics.Role
import androidx.compose.ui.semantics.contentDescription
import androidx.compose.ui.semantics.semantics
import androidx.compose.ui.text.style.TextAlign
import androidx.compose.ui.unit.Dp
import androidx.compose.ui.semantics.selected
import androidx.compose.ui.text.font.FontWeight
import androidx.compose.ui.unit.dp
import club.campbuddy.shared.model.BannerConfig

/** Line icons (24x24, round 1.8 strokes) - the same drawings the website uses; unknown names draw a star. */
private val ICON_PATHS: Map<String, String> = WEBSITE_ICON_PATHS + mapOf(
    "home" to "M3 11l9-8 9 8M5 10v10h5v-6h4v6h5V10",
    "dashboard" to "M3 11l9-8 9 8M5 10v10h5v-6h4v6h5V10",
    "calendar" to "M7 3v3M17 3v3M4 8h16M5 5h14a1 1 0 0 1 1 1v14a1 1 0 0 1-1 1H5a1 1 0 0 1-1-1V6a1 1 0 0 1 1-1z",
    "search" to "M11 4a7 7 0 1 0 0 14 7 7 0 0 0 0-14zM21 21l-5-5",
    "filter" to "M4 6h16M7 12h10M10 18h4",
    "chevron" to "M6 9l6 6 6-6",
    "bell" to "M6 8a6 6 0 0 1 12 0c0 7 3 9 3 9H3s3-2 3-9M10.3 21a1.94 1.94 0 0 0 3.4 0",
    "hand" to "M18 11V6a2 2 0 0 0-4 0v5M14 10V4a2 2 0 0 0-4 0v2M10 10.5V6a2 2 0 0 0-4 0v8M18 8a2 2 0 1 1 4 0v6a8 8 0 0 1-8 8h-2c-2.8 0-4.5-.86-5.99-2.34l-3.6-3.6a2 2 0 0 1 2.83-2.82L7 15",
    "globe" to "M22 12a10 10 0 1 1-20 0 10 10 0 0 1 20 0zM12 2a14.5 14.5 0 0 0 0 20 14.5 14.5 0 0 0 0-20M2 12h20",
    "mic" to "M12 2a3 3 0 0 0-3 3v6a3 3 0 0 0 6 0V5a3 3 0 0 0-3-3zM19 10v1a7 7 0 0 1-14 0v-1M12 18v4M8 22h8",
    "tag" to "M12.59 2.59A2 2 0 0 0 11.17 2H4a2 2 0 0 0-2 2v7.17a2 2 0 0 0 .59 1.42l8.7 8.7a2.43 2.43 0 0 0 3.42 0l6.58-6.58a2.43 2.43 0 0 0 0-3.42zM7.5 7.5h.01",
    "help-circle" to "M22 12a10 10 0 1 1-20 0 10 10 0 0 1 20 0zM9.09 9a3 3 0 0 1 5.83 1c0 2-3 3-3 3M12 17h.01",
    "briefcase" to "M4 7h16a2 2 0 0 1 2 2v10a2 2 0 0 1-2 2H4a2 2 0 0 1-2-2V9a2 2 0 0 1 2-2zM16 21V5a2 2 0 0 0-2-2h-4a2 2 0 0 0-2 2v16",
    "sparkles" to "M12 3l1.9 5.8L20 11l-6.1 2.2L12 19l-1.9-5.8L4 11l6.1-2.2zM19 3v4M17 5h4",
    "heart" to "M20.8 4.6a5.5 5.5 0 0 0-7.8 0L12 5.7l-1-1.1a5.5 5.5 0 0 0-7.8 7.8l1 1L12 21l7.8-7.6 1-1a5.5 5.5 0 0 0 0-7.8z",
    "ticket" to "M3 9a3 3 0 0 0 0 6v3a1 1 0 0 0 1 1h16a1 1 0 0 0 1-1v-3a3 3 0 0 0 0-6V6a1 1 0 0 0-1-1H4a1 1 0 0 0-1 1zM13 5v2M13 11v2M13 17v2",
    "star" to "M12 3l2.9 5.9 6.5.9-4.7 4.6 1.1 6.5L12 17.8 6.2 20.9l1.1-6.5L2.6 9.8l6.5-.9z",
    "wrench" to "M14.7 6.3a1 1 0 0 0 0 1.4l1.6 1.6a1 1 0 0 0 1.4 0l3.77-3.77a6 6 0 0 1-7.94 7.94l-6.91 6.91a2.12 2.12 0 0 1-3-3l6.91-6.91a6 6 0 0 1 7.94-7.94l-3.76 3.76z",
    "compass" to "M12 3a9 9 0 1 0 0 18 9 9 0 0 0 0-18zM15.5 8.5l-2 5-5 2 2-5z",
    "id-card" to "M4 5h16a1 1 0 0 1 1 1v12a1 1 0 0 1-1 1H4a1 1 0 0 1-1-1V6a1 1 0 0 1 1-1zM5.5 16a3 3 0 0 1 5 0M8 12a1.5 1.5 0 1 0 0-.01M14 10h4M14 14h4",
    "camera" to "M4 8h3l1.5-2h7L17 8h3a1 1 0 0 1 1 1v9a1 1 0 0 1-1 1H4a1 1 0 0 1-1-1V9a1 1 0 0 1 1-1zM12 17a3.5 3.5 0 1 0 0-7 3.5 3.5 0 0 0 0 7z",
    "siren" to "M7 18v-6a5 5 0 1 1 10 0v6M5 21a1 1 0 0 0 1 1h12a1 1 0 0 0 1-1v-1a2 2 0 0 0-2-2H7a2 2 0 0 0-2 2z",
    "clock" to "M12 3a9 9 0 1 0 0 18 9 9 0 0 0 0-18zM12 7v5l3 2",
    "columns" to "M4 5h16v14H4zM12 5v14",
    "users" to "M9 11a3 3 0 1 0 0-6 3 3 0 0 0 0 6zM3 20a6 6 0 0 1 12 0M16 11a2.5 2.5 0 1 0 0-5M17 14a5 5 0 0 1 4 6",
    "refresh" to "M3 12a9 9 0 0 1 9-9 9.75 9.75 0 0 1 6.74 2.74L21 8M21 3v5h-5M21 12a9 9 0 0 1-9 9 9.75 9.75 0 0 1-6.74-2.74L3 16M8 16H3v5",
    "lock" to "M6 11h12v9H6zM8 11V8a4 4 0 0 1 8 0v3",
    // The organizer screens' tools.
    "check" to "M20 6 9 17l-5-5",
    "plus" to "M12 5v14M5 12h14",
    "trash" to "M3 6h18M8 6V4h8v2M19 6l-1 14H6L5 6M10 11v6M14 11v6",
    "pencil" to "M17 3a2.85 2.85 0 0 1 4 4L7.5 20.5 2 22l1.5-5.5z",
    "eye" to "M2 12s3.5-7 10-7 10 7 10 7-3.5 7-10 7S2 12 2 12zM12 9a3 3 0 1 0 0 6 3 3 0 0 0 0-6z",
    "eye-off" to "M3 3l18 18M10.6 5.1A10 10 0 0 1 12 5c6.5 0 10 7 10 7a17 17 0 0 1-2.2 3.2M6.6 6.6C3.8 8.4 2 12 2 12s3.5 7 10 7a9.7 9.7 0 0 0 5.4-1.6M9.9 9.9a3 3 0 0 0 4.2 4.2",
    "settings" to "M12 9a3 3 0 1 0 0 6 3 3 0 0 0 0-6zM19.4 15a1.7 1.7 0 0 0 .3 1.8l.1.1a2 2 0 1 1-2.8 2.8l-.1-.1a1.7 1.7 0 0 0-1.8-.3 1.7 1.7 0 0 0-1 1.5V21a2 2 0 1 1-4 0v-.1a1.7 1.7 0 0 0-1.1-1.5 1.7 1.7 0 0 0-1.8.3l-.1.1a2 2 0 1 1-2.8-2.8l.1-.1a1.7 1.7 0 0 0 .3-1.8 1.7 1.7 0 0 0-1.5-1H3a2 2 0 1 1 0-4h.1a1.7 1.7 0 0 0 1.5-1.1 1.7 1.7 0 0 0-.3-1.8l-.1-.1a2 2 0 1 1 2.8-2.8l.1.1a1.7 1.7 0 0 0 1.8.3H9a1.7 1.7 0 0 0 1-1.5V3a2 2 0 1 1 4 0v.1a1.7 1.7 0 0 0 1 1.5 1.7 1.7 0 0 0 1.8-.3l.1-.1a2 2 0 1 1 2.8 2.8l-.1.1a1.7 1.7 0 0 0-.3 1.8V9a1.7 1.7 0 0 0 1.5 1H21a2 2 0 1 1 0 4h-.1a1.7 1.7 0 0 0-1.5 1z",
    "activity" to "M22 12h-4l-3 9L9 3l-3 9H2",
    "alert" to "M12 3 2 21h20zM12 10v4M12 17.5h.01",
    "mail" to "M4 5h16a1 1 0 0 1 1 1v12a1 1 0 0 1-1 1H4a1 1 0 0 1-1-1V6a1 1 0 0 1 1-1zM3 7l9 6 9-6",
    "phone" to "M22 16.9v3a2 2 0 0 1-2.2 2 19.8 19.8 0 0 1-8.6-3.1 19.5 19.5 0 0 1-6-6A19.8 19.8 0 0 1 2.1 4.2 2 2 0 0 1 4.1 2h3a2 2 0 0 1 2 1.7c.1.9.4 1.8.7 2.7a2 2 0 0 1-.5 2.1L8 9.8a16 16 0 0 0 6 6l1.3-1.3a2 2 0 0 1 2.1-.4c.9.3 1.8.6 2.7.7a2 2 0 0 1 1.7 2z",
    "inbox" to "M22 12h-6l-2 3h-4l-2-3H2M5.5 5h13l3.5 7v6a2 2 0 0 1-2 2H4a2 2 0 0 1-2-2v-6z",
    "list" to "M8 6h13M8 12h13M8 18h13M3 6h.01M3 12h.01M3 18h.01",
    "database" to "M12 3c4.4 0 8 1.3 8 3s-3.6 3-8 3-8-1.3-8-3 3.6-3 8-3zM4 6v6c0 1.7 3.6 3 8 3s8-1.3 8-3V6M4 12v6c0 1.7 3.6 3 8 3s8-1.3 8-3v-6",
)

@Composable
fun LineIcon(name: String, tint: Color, size: Dp = 24.dp, filled: Boolean = false) {
    val path = ICON_PATHS[name] ?: ICON_PATHS.getValue("star")
    Canvas(Modifier.requiredSize(size)) {
        val scale = this.size.width / 24f
        val parsed = PathParser().parsePathString(path).toPath()
        drawContext.canvas.save()
        drawContext.canvas.scale(scale, scale)
        if (filled) drawPath(parsed, tint)
        drawPath(parsed, tint, style = Stroke(width = 1.8f, cap = StrokeCap.Round, join = StrokeJoin.Round))
        drawContext.canvas.restore()
    }
}

/** The website's button: 50 high, 4px corners, 16 bold (the compact one, [ActionButton], is 44 high and 14). */
@Composable
fun PrimaryButton(text: String, onClick: () -> Unit, modifier: Modifier = Modifier, enabled: Boolean = true) {
    val brand = LocalBrand.current
    val shape = RoundedCornerShape(4.dp)
    Box(
        modifier.heightIn(min = 50.dp)
            .background(if (enabled) brand.primary else brand.primary.copy(alpha = 0.4f), shape)
            .clickable(enabled = enabled, role = Role.Button, onClick = onClick)
            .padding(horizontal = 20.dp, vertical = 12.dp),
        contentAlignment = Alignment.Center,
    ) { Text(text, style = MaterialTheme.typography.titleMedium.copy(color = Color.White), textAlign = TextAlign.Center) }
}

@Composable
fun QuietButton(text: String, onClick: () -> Unit, modifier: Modifier = Modifier) {
    val brand = LocalBrand.current
    val shape = RoundedCornerShape(4.dp)
    Box(
        modifier.heightIn(min = 50.dp)
            .border(1.dp, brand.line, shape)
            .clickable(role = Role.Button, onClick = onClick)
            .padding(horizontal = 20.dp, vertical = 12.dp),
        contentAlignment = Alignment.Center,
    ) { Text(text, style = MaterialTheme.typography.titleMedium.copy(color = brand.ink), textAlign = TextAlign.Center) }
}

@Composable
fun Card(modifier: Modifier = Modifier, onClick: (() -> Unit)? = null, content: @Composable () -> Unit) {
    val brand = LocalBrand.current
    val shape = RoundedCornerShape(brand.radius.dp)
    val base = modifier
        .fillMaxWidth()
        .shadow(3.dp, shape, clip = false, ambientColor = brand.primary.copy(alpha = 0.10f), spotColor = brand.primary.copy(alpha = 0.10f))
        .background(brand.surface, shape)
        .border(1.dp, brand.line, shape)
        .let { if (onClick != null) it.clickable(role = Role.Button, onClick = onClick) else it }
        .padding(16.dp)
    Box(base) { content() }
}

@Composable
fun SectionTitle(text: String) {
    Text(text, style = MaterialTheme.typography.headlineSmall, modifier = Modifier.padding(top = 8.dp, bottom = 4.dp))
}

@Composable
fun Loading() {
    Box(Modifier.fillMaxWidth().padding(32.dp), contentAlignment = Alignment.Center) {
        CircularProgressIndicator(color = LocalBrand.current.primary)
    }
}

@Composable
fun ErrorState(message: String, onRetry: () -> Unit) {
    val words = LocalWords.current
    Column(Modifier.fillMaxWidth().padding(24.dp), horizontalAlignment = Alignment.CenterHorizontally, verticalArrangement = Arrangement.spacedBy(12.dp)) {
        Text(message, textAlign = TextAlign.Center, style = MaterialTheme.typography.bodyLarge)
        PrimaryButton(words("common.retry"), onRetry)
    }
}

/** "You are offline. Showing what is saved on this phone." */
@Composable
fun OfflineNote() {
    val brand = LocalBrand.current
    Text(
        LocalWords.current("common.offline"),
        style = MaterialTheme.typography.bodySmall,
        modifier = Modifier.fillMaxWidth().background(brand.line, RoundedCornerShape(8.dp)).padding(10.dp),
    )
}

private data class BannerStyleTokens(
    val bg: Color,
    val border: Color,
    val accent: Color,
    val iconTint: Color,
    val badgeBg: Color,
    val icon: String,
    val kicker: String,
)

@Composable
fun BannerCard(
    banner: BannerConfig,
    language: String,
    onAction: () -> Unit,
    onDismiss: (() -> Unit)? = null,
) {
    val brand = LocalBrand.current
    val text = banner.text[language]?.takeIf { it.isNotBlank() } ?: banner.text["en"].orEmpty()
    if (text.isBlank()) return

    val tokens = when (banner.style) {
        "warning" -> BannerStyleTokens(
            bg = Color(0xFFFFFBEB),
            border = Color(0xFFFDE68A),
            accent = Color(0xFFD97706),
            iconTint = Color(0xFFB45309),
            badgeBg = Color(0xFFFEF3C7),
            icon = "clock",
            kicker = if (language == "hi") "महत्वपूर्ण सूचना" else "IMPORTANT NOTICE",
        )
        "success" -> BannerStyleTokens(
            bg = Color(0xFFF0FDF4),
            border = Color(0xFFBBF7D0),
            accent = Color(0xFF16A34A),
            iconTint = Color(0xFF15803D),
            badgeBg = Color(0xFFDCFCE7),
            icon = "sparkles",
            kicker = if (language == "hi") "ताज़ा अपडेट" else "NEW UPDATE",
        )
        else -> BannerStyleTokens(
            bg = Color(0xFFFFF5EE),
            border = Color(0xFFF7D5C1),
            accent = brand.primary,
            iconTint = brand.primary,
            badgeBg = brand.primary.copy(alpha = 0.12f),
            icon = "megaphone",
            kicker = if (language == "hi") "घोषणा" else "ANNOUNCEMENT",
        )
    }

    val shape = RoundedCornerShape(brand.radius.dp.coerceAtLeast(10.dp))

    Box(
        Modifier
            .fillMaxWidth()
            .clip(shape)
            .background(tokens.bg)
            .border(1.dp, tokens.border, shape)
            .let { if (banner.action != null) it.clickable(role = Role.Button, onClick = onAction) else it }
            .semantics { contentDescription = text }
    ) {
        // Left accent bar
        Box(
            Modifier
                .align(Alignment.CenterStart)
                .width(4.dp)
                .matchParentSize()
                .background(tokens.accent)
        )

        Column(
            Modifier
                .fillMaxWidth()
                .padding(start = 16.dp, end = 16.dp, top = 14.dp, bottom = 14.dp),
            verticalArrangement = Arrangement.spacedBy(10.dp),
        ) {
            // Top row: Icon badge + Kicker tag + (Spacer) + Close Button
            Row(
                Modifier.fillMaxWidth(),
                verticalAlignment = Alignment.CenterVertically,
            ) {
                Row(
                    verticalAlignment = Alignment.CenterVertically,
                    horizontalArrangement = Arrangement.spacedBy(10.dp),
                    modifier = Modifier.weight(1f, fill = false),
                ) {
                    Box(
                        Modifier
                            .size(32.dp)
                            .background(tokens.badgeBg, CircleShape),
                        contentAlignment = Alignment.Center,
                    ) {
                        LineIcon(tokens.icon, tokens.iconTint, size = 18.dp)
                    }

                    Row(
                        Modifier
                            .background(tokens.badgeBg, RoundedCornerShape(50))
                            .padding(horizontal = 10.dp, vertical = 4.dp),
                        verticalAlignment = Alignment.CenterVertically,
                        horizontalArrangement = Arrangement.spacedBy(5.dp),
                    ) {
                        Box(
                            Modifier
                                .size(6.dp)
                                .background(tokens.iconTint, CircleShape)
                        )
                        Text(
                            tokens.kicker,
                            style = MaterialTheme.typography.labelSmall.copy(
                                color = tokens.iconTint,
                                fontWeight = FontWeight.Bold,
                                letterSpacing = 0.8.sp,
                            ),
                        )
                    }
                }

                Spacer(Modifier.weight(1f))

                if (onDismiss != null) {
                    Box(
                        Modifier
                            .size(28.dp)
                            .clip(CircleShape)
                            .background(tokens.badgeBg)
                            .clickable(
                                role = Role.Button,
                                onClickLabel = if (language == "hi") "घोषणा बंद करें" else "Dismiss announcement",
                                onClick = onDismiss,
                            ),
                        contentAlignment = Alignment.Center,
                    ) {
                        LineIcon("x", tokens.iconTint, size = 14.dp)
                    }
                }
            }

            // Message text
            Text(
                text = text,
                style = MaterialTheme.typography.bodyMedium.copy(
                    fontWeight = FontWeight.Medium,
                    color = brand.ink,
                    lineHeight = 21.sp,
                ),
            )

            // Action button/pill when action is attached
            if (banner.action != null) {
                val actionLabel = when {
                    banner.action.type == "event" -> if (language == "hi") "इवेंट खोलें" else "View WordCamp"
                    banner.action.type == "url" -> if (language == "hi") "लिंक खोलें" else "Open Link"
                    else -> if (language == "hi") "अधिक जानें" else "Learn more"
                }
                val actionIcon = if (banner.action.type == "url") "external" else "arrow-right"

                Row(
                    Modifier
                        .align(Alignment.End)
                        .background(brand.surface, RoundedCornerShape(50))
                        .border(1.dp, tokens.border, RoundedCornerShape(50))
                        .padding(horizontal = 12.dp, vertical = 6.dp),
                    verticalAlignment = Alignment.CenterVertically,
                    horizontalArrangement = Arrangement.spacedBy(6.dp),
                ) {
                    Text(
                        actionLabel,
                        style = MaterialTheme.typography.labelMedium.copy(
                            color = tokens.iconTint,
                            fontWeight = FontWeight.Bold,
                        ),
                    )
                    LineIcon(actionIcon, tokens.iconTint, size = 14.dp)
                }
            }
        }
    }
}

/** A choice chip: dark text on white, filled maroon with white text when chosen (readable on the cream page). */
@Composable
fun CbChip(selected: Boolean, onClick: () -> Unit, label: @Composable () -> Unit) {
    val brand = LocalBrand.current
    FilterChip(
        selected = selected,
        onClick = onClick,
        label = label,
        colors = FilterChipDefaults.filterChipColors(
            containerColor = brand.surface,
            labelColor = brand.ink,
            selectedContainerColor = brand.primary,
            selectedLabelColor = Color.White,
        ),
        border = FilterChipDefaults.filterChipBorder(enabled = true, selected = selected, borderColor = brand.line, selectedBorderColor = brand.primary),
    )
}

/** A text button whose words are the brand colour (the default fades into the dialog). */
@Composable
fun LinkButton(text: String, onClick: () -> Unit, modifier: Modifier = Modifier) {
    TextButton(onClick = onClick, modifier = modifier.heightIn(min = 44.dp)) {
        Text(text, style = MaterialTheme.typography.labelLarge.copy(color = LocalBrand.current.primary))
    }
}

/** The website's rounded choice button: the chosen one is gold with a tick, the others have a thin border. */
@Composable
fun ChoicePill(label: String, selected: Boolean, onClick: () -> Unit) {
    val brand = LocalBrand.current
    val shape = RoundedCornerShape(50)
    Row(
        Modifier.heightIn(min = 44.dp)
            .background(if (selected) brand.accent else brand.surface, shape)
            .border(1.dp, if (selected) brand.accent else brand.line, shape)
            .clickable(role = Role.Button, onClick = onClick)
            .semantics { this.selected = selected }
            .padding(horizontal = 14.dp, vertical = 8.dp),
        horizontalArrangement = Arrangement.spacedBy(6.dp),
        verticalAlignment = Alignment.CenterVertically,
    ) {
        if (selected) Text("✓", style = MaterialTheme.typography.labelLarge.copy(color = brand.ink, fontWeight = FontWeight.SemiBold))
        Text(label, style = MaterialTheme.typography.labelLarge.copy(color = brand.ink, fontWeight = FontWeight.SemiBold))
    }
}

/**
 * The website's faint line-icon background: a 200dp tile repeated from the top-right corner, fading out
 * towards the text (the way .home-hero / .discovery-prompt do it).
 */
@Composable
fun Modifier.patternBackground(pattern: org.jetbrains.compose.resources.DrawableResource, fade: Color?, alpha: Float = 1f): Modifier {
    val image = org.jetbrains.compose.resources.imageResource(pattern)

    return this.drawBehind {
        val tile = 200.dp.toPx()
        val startX = size.width - tile + 30.dp.toPx()
        val startY = -40.dp.toPx()
        var y = startY
        while (y < size.height) {
            var x = startX
            while (x > -tile) {
                drawImage(
                    image, srcSize = androidx.compose.ui.unit.IntSize(image.width, image.height),
                    dstOffset = androidx.compose.ui.unit.IntOffset(x.toInt(), y.toInt()), dstSize = androidx.compose.ui.unit.IntSize(tile.toInt(), tile.toInt()), alpha = alpha,
                )
                x -= tile
            }
            y += tile
        }
        if (fade != null) {
            drawRect(androidx.compose.ui.graphics.Brush.horizontalGradient(0.3f to fade, 0.6f to fade.copy(alpha = 0.7f), 0.9f to fade.copy(alpha = 0f), startX = 0f, endX = size.width))
        }
    }
}

/** Text with the website's quiet underline: a thin line in a soft colour under every line (a normal underline is as dark as the text). */
@Composable
fun SoftUnderlineText(text: String, style: androidx.compose.ui.text.TextStyle, modifier: Modifier = Modifier, lineColor: Color = LocalBrand.current.muted.copy(alpha = 0.4f)) {
    var layout by remember { mutableStateOf<androidx.compose.ui.text.TextLayoutResult?>(null) }
    Text(
        text, style = style, onTextLayout = { layout = it },
        modifier = modifier.drawBehind {
            layout?.let { l ->
                for (i in 0 until l.lineCount) {
                    val y = l.getLineBottom(i) - 1.dp.toPx()
                    drawLine(lineColor, Offset(l.getLineLeft(i), y), Offset(l.getLineRight(i), y), 1.dp.toPx())
                }
            }
        },
    )
}
