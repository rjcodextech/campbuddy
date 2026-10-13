package club.campbuddy.shared.ui

import androidx.compose.foundation.shape.RoundedCornerShape
import androidx.compose.material3.MaterialTheme
import androidx.compose.material3.Shapes
import androidx.compose.material3.Typography
import androidx.compose.material3.lightColorScheme
import androidx.compose.runtime.Composable
import androidx.compose.runtime.CompositionLocalProvider
import androidx.compose.runtime.compositionLocalOf
import androidx.compose.ui.graphics.Color
import androidx.compose.ui.text.TextStyle
import androidx.compose.ui.text.font.FontFamily
import androidx.compose.ui.text.font.FontWeight
import androidx.compose.ui.unit.dp
import androidx.compose.ui.unit.sp
import club.campbuddy.shared.model.AppConfig
import club.campbuddy.shared.model.ThemeConfig

/** The config and the phone language every screen reads its words from. */
class Words(val config: AppConfig, val language: String) {
    operator fun invoke(key: String): String = config.text(key, language)
}

val LocalWords = compositionLocalOf<Words> { error("LocalWords not provided") }

val LocalBrand = compositionLocalOf { Brand(ThemeConfig()) }

/** Colours from the server-driven theme (a bad value falls back to the brand colour, never a crash). */
class Brand(theme: ThemeConfig) {
    val primary = color(theme.primary, 0xFF60121C)
    val accent = color(theme.accent, 0xFFDA7F01)
    val background = color(theme.background, 0xFFFCF1DB)
    val ink = color(theme.ink, 0xFF2B1014)
    val muted = color(theme.muted, 0xFF6E5A54)
    val surface = Color.White
    val line = Color(0xFFE6DCCB)
    val radius = theme.radius.coerceIn(0, 28)

    private fun color(hex: String, fallback: Long): Color {
        val value = hex.removePrefix("#").takeIf { it.length == 6 }?.toLongOrNull(16)

        return Color(0xFF000000 or (value ?: (fallback and 0xFFFFFF)))
    }
}

/**
 * Typography is fixed in the app (design, not content). Inter is the site text font; until it is
 * bundled as a resource the platform default sans-serif is used with the same sizes and weights.
 */
private val Family = FontFamily.Default

private fun style(size: Int, weight: FontWeight, line: Int, color: Color) =
    TextStyle(fontFamily = Family, fontSize = size.sp, fontWeight = weight, lineHeight = line.sp, color = color)

@Composable
fun CampBuddyTheme(words: Words, content: @Composable () -> Unit) {
    val brand = Brand(words.config.theme)
    val colors = lightColorScheme(
        primary = brand.primary,
        onPrimary = Color.White,
        secondary = brand.accent,
        background = brand.background,
        onBackground = brand.ink,
        surface = brand.surface,
        onSurface = brand.ink,
        outline = brand.line,
        onSurfaceVariant = brand.muted,
    )
    val shape = RoundedCornerShape(brand.radius.dp)
    val typography = Typography(
        headlineSmall = style(24, FontWeight.Bold, 30, brand.ink),
        titleLarge = style(20, FontWeight.Bold, 26, brand.ink),
        titleMedium = style(17, FontWeight.SemiBold, 24, brand.ink),
        bodyLarge = style(16, FontWeight.Normal, 24, brand.ink),
        bodyMedium = style(14, FontWeight.Normal, 21, brand.ink),
        bodySmall = style(12, FontWeight.Normal, 18, brand.muted),
        labelLarge = style(15, FontWeight.SemiBold, 20, Color.White),
        labelMedium = style(12, FontWeight.SemiBold, 16, brand.muted),
    )

    CompositionLocalProvider(LocalWords provides words, LocalBrand provides brand) {
        MaterialTheme(
            colorScheme = colors,
            typography = typography,
            shapes = Shapes(small = shape, medium = shape, large = shape),
            content = content,
        )
    }
}
