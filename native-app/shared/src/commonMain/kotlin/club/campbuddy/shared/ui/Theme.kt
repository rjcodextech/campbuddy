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
import org.jetbrains.compose.resources.Font
import androidx.compose.ui.text.font.FontFamily
import androidx.compose.ui.text.font.FontWeight
import androidx.compose.ui.unit.dp
import androidx.compose.ui.unit.sp
import club.campbuddy.shared.model.AppConfig
import club.campbuddy.shared.model.ThemeConfig
import campbuddy_native.shared.generated.resources.Res
import campbuddy_native.shared.generated.resources.inter_bold
import campbuddy_native.shared.generated.resources.inter_extrabold
import campbuddy_native.shared.generated.resources.inter_medium
import campbuddy_native.shared.generated.resources.inter_regular
import campbuddy_native.shared.generated.resources.inter_semibold

/** The config and the phone language every screen reads its words from. */
class Words(val config: AppConfig, val language: String) {
    operator fun invoke(key: String): String = config.text(key, language)
}

val LocalWords = compositionLocalOf<Words> { error("LocalWords not provided") }

val LocalBrand = compositionLocalOf { Brand(ThemeConfig()) }

/** Colours from the server-driven theme (a bad value falls back to the brand colour, never a crash). */
class Brand(theme: ThemeConfig) {
    val primary = color(theme.primary, 0xFFC33A19)
    val accent = color(theme.accent, 0xFFE39D1C)
    val background = color(theme.background, 0xFFFFF0DF)
    val surface = color(theme.surface, 0xFFFFFAF4)
    val peach = color(theme.peach, 0xFFFFE4C4)
    val line = color(theme.line, 0xFFEADFD6)
    val ink = color(theme.ink, 0xFF231F20)
    val muted = color(theme.muted, 0xFF6B625E)
    val danger = color(theme.danger, 0xFFB3261E)
    val success = color(theme.success, 0xFF1F7A4D)
    val radius = theme.radius.coerceIn(0, 28)

    private fun color(hex: String, fallback: Long): Color {
        val value = hex.removePrefix("#").takeIf { it.length == 6 }?.toLongOrNull(16)

        return Color(0xFF000000 or (value ?: (fallback and 0xFFFFFF)))
    }
}

/**
 * Typography is fixed in the app (design, not content). Inter is the website's UI font, bundled so the app reads the
 * same on every phone (the system font can be anything, even a serif a person picked).
 */
@Composable
private fun interFamily(): FontFamily = FontFamily(
    Font(Res.font.inter_regular, FontWeight.Normal),
    Font(Res.font.inter_medium, FontWeight.Medium),
    Font(Res.font.inter_semibold, FontWeight.SemiBold),
    Font(Res.font.inter_bold, FontWeight.Bold),
    Font(Res.font.inter_extrabold, FontWeight.ExtraBold),
)

private fun style(family: FontFamily, size: Int, weight: FontWeight, line: Int, color: Color) =
    TextStyle(fontFamily = family, fontSize = size.sp, fontWeight = weight, lineHeight = line.sp, color = color)

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
    val family = interFamily()
    // The website's own sizes (measured on the live site): section/page title 16.8 w800, card title 18 w700,
    // body 16 / 14, small 13.12, badge 12 w700. Buttons are 16 w700 (compact 14 w700).
    val typography = Typography(
        headlineSmall = style(family, 17, FontWeight.ExtraBold, 22, brand.ink).copy(fontSize = 16.8.sp, lineHeight = 21.84.sp, letterSpacing = (-0.336).sp),
        titleLarge = style(family, 18, FontWeight.Bold, 24, brand.ink).copy(letterSpacing = (-0.36).sp),
        titleMedium = style(family, 16, FontWeight.Bold, 24, brand.ink),
        bodyLarge = style(family, 16, FontWeight.Normal, 24, brand.ink),
        bodyMedium = style(family, 14, FontWeight.Normal, 21, brand.ink),
        bodySmall = style(family, 13, FontWeight.Normal, 20, brand.muted).copy(fontSize = 13.12.sp, lineHeight = 19.68.sp),
        labelLarge = style(family, 14, FontWeight.Bold, 21, Color.Unspecified),
        labelMedium = style(family, 12, FontWeight.Bold, 18, brand.muted),
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
