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
import androidx.compose.foundation.layout.fillMaxWidth
import androidx.compose.foundation.layout.heightIn
import androidx.compose.foundation.layout.padding
import androidx.compose.foundation.layout.size
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
import androidx.compose.ui.unit.dp
import club.campbuddy.shared.model.BannerConfig

/** Line icons (24x24, round 1.8 strokes) - the same drawings the website uses; unknown names draw a star. */
private val ICON_PATHS = mapOf(
    "home" to "M3 11l9-8 9 8M5 10v10h5v-6h4v6h5V10",
    "dashboard" to "M3 11l9-8 9 8M5 10v10h5v-6h4v6h5V10",
    "calendar" to "M7 3v3M17 3v3M4 8h16M5 5h14a1 1 0 0 1 1 1v14a1 1 0 0 1-1 1H5a1 1 0 0 1-1-1V6a1 1 0 0 1 1-1z",
    "star" to "M12 3l2.9 5.9 6.5.9-4.7 4.6 1.1 6.5L12 17.8 6.2 20.9l1.1-6.5L2.6 9.8l6.5-.9z",
    "wrench" to "M14.7 6.3a1 1 0 0 0 0 1.4l1.6 1.6a1 1 0 0 0 1.4 0l3.77-3.77a6 6 0 0 1-7.94 7.94l-6.91 6.91a2.12 2.12 0 0 1-3-3l6.91-6.91a6 6 0 0 1 7.94-7.94l-3.76 3.76z",
    "compass" to "M12 3a9 9 0 1 0 0 18 9 9 0 0 0 0-18zM15.5 8.5l-2 5-5 2 2-5z",
    "id-card" to "M4 5h16a1 1 0 0 1 1 1v12a1 1 0 0 1-1 1H4a1 1 0 0 1-1-1V6a1 1 0 0 1 1-1zM5.5 16a3 3 0 0 1 5 0M8 12a1.5 1.5 0 1 0 0-.01M14 10h4M14 14h4",
    "camera" to "M4 8h3l1.5-2h7L17 8h3a1 1 0 0 1 1 1v9a1 1 0 0 1-1 1H4a1 1 0 0 1-1-1V9a1 1 0 0 1 1-1zM12 17a3.5 3.5 0 1 0 0-7 3.5 3.5 0 0 0 0 7z",
    "siren" to "M7 18v-6a5 5 0 1 1 10 0v6M5 21a1 1 0 0 0 1 1h12a1 1 0 0 0 1-1v-1a2 2 0 0 0-2-2H7a2 2 0 0 0-2 2z",
    "clock" to "M12 3a9 9 0 1 0 0 18 9 9 0 0 0 0-18zM12 7v5l3 2",
    "columns" to "M4 5h16v14H4zM12 5v14",
    "users" to "M9 11a3 3 0 1 0 0-6 3 3 0 0 0 0 6zM3 20a6 6 0 0 1 12 0M16 11a2.5 2.5 0 1 0 0-5M17 14a5 5 0 0 1 4 6",
    "lock" to "M6 11h12v9H6zM8 11V8a4 4 0 0 1 8 0v3",
)

@Composable
fun LineIcon(name: String, tint: Color, size: Dp = 24.dp, filled: Boolean = false) {
    val path = ICON_PATHS[name] ?: ICON_PATHS.getValue("star")
    Canvas(Modifier.size(size)) {
        val scale = this.size.width / 24f
        val parsed = PathParser().parsePathString(path).toPath()
        drawContext.canvas.save()
        drawContext.canvas.scale(scale, scale)
        if (filled) drawPath(parsed, tint)
        drawPath(parsed, tint, style = Stroke(width = 1.8f, cap = StrokeCap.Round, join = StrokeJoin.Round))
        drawContext.canvas.restore()
    }
}

@Composable
fun PrimaryButton(text: String, onClick: () -> Unit, modifier: Modifier = Modifier, enabled: Boolean = true) {
    val brand = LocalBrand.current
    Button(
        onClick = onClick,
        enabled = enabled,
        modifier = modifier.heightIn(min = 48.dp),
        colors = ButtonDefaults.buttonColors(containerColor = brand.primary, contentColor = Color.White),
    ) { Text(text, style = MaterialTheme.typography.labelLarge.copy(color = Color.White)) }
}

@Composable
fun QuietButton(text: String, onClick: () -> Unit, modifier: Modifier = Modifier) {
    val brand = LocalBrand.current
    OutlinedButton(
        onClick = onClick,
        modifier = modifier.heightIn(min = 48.dp),
        border = BorderStroke(1.dp, brand.line),
        colors = ButtonDefaults.outlinedButtonColors(contentColor = brand.ink),
    ) { Text(text, style = MaterialTheme.typography.labelLarge.copy(color = brand.ink)) }
}

@Composable
fun Card(modifier: Modifier = Modifier, onClick: (() -> Unit)? = null, content: @Composable () -> Unit) {
    val brand = LocalBrand.current
    val shape = RoundedCornerShape(brand.radius.dp)
    val base = modifier
        .fillMaxWidth()
        .shadow(2.dp, shape, clip = false, ambientColor = brand.ink.copy(alpha = 0.12f), spotColor = brand.ink.copy(alpha = 0.12f))
        .background(brand.surface, shape)
        .border(1.dp, brand.line, shape)
        .let { if (onClick != null) it.clickable(role = Role.Button, onClick = onClick) else it }
        .padding(16.dp)
    Box(base) { content() }
}

@Composable
fun SectionTitle(text: String) {
    Text(text, style = MaterialTheme.typography.titleMedium, modifier = Modifier.padding(top = 8.dp, bottom = 4.dp))
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

@Composable
fun BannerCard(banner: BannerConfig, language: String, onAction: () -> Unit) {
    val brand = LocalBrand.current
    val text = banner.text[language]?.takeIf { it.isNotBlank() } ?: banner.text["en"].orEmpty()
    val tint = when (banner.style) {
        "warning" -> Color(0xFFFFE9B8)
        "success" -> Color(0xFFD9F1DD)
        else -> Color(0xFFF7E3D6)
    }
    Row(
        Modifier.fillMaxWidth().background(tint, RoundedCornerShape(brand.radius.dp))
            .let { if (banner.action != null) it.clickable(role = Role.Button, onClick = onAction) else it }
            .padding(14.dp)
            .semantics { contentDescription = text },
        verticalAlignment = Alignment.CenterVertically,
    ) {
        Text(text, style = MaterialTheme.typography.bodyMedium, modifier = Modifier.weight(1f, fill = true))
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
    TextButton(onClick = onClick, modifier = modifier.heightIn(min = 48.dp)) {
        Text(text, style = MaterialTheme.typography.labelLarge.copy(color = LocalBrand.current.primary))
    }
}
