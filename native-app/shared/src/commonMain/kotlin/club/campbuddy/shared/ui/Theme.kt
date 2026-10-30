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
import androidx.compose.ui.graphics.luminance
import androidx.compose.ui.text.TextStyle
import org.jetbrains.compose.resources.Font
import androidx.compose.ui.text.font.FontFamily
import androidx.compose.ui.text.font.FontWeight
import androidx.compose.ui.unit.dp
import androidx.compose.ui.unit.sp
import club.campbuddy.shared.model.AppConfig
import club.campbuddy.shared.model.ThemeConfig
import campbuddy_native.shared.generated.resources.Res
import campbuddy_native.shared.generated.resources.baloo2_bold
import campbuddy_native.shared.generated.resources.baloo2_extrabold
import campbuddy_native.shared.generated.resources.baloo2_semibold
import campbuddy_native.shared.generated.resources.poppins_bold
import campbuddy_native.shared.generated.resources.poppins_medium
import campbuddy_native.shared.generated.resources.poppins_regular
import campbuddy_native.shared.generated.resources.poppins_semibold

/** The config and the phone language every screen reads its words from. */
class Words(val config: AppConfig, val language: String) {
    operator fun invoke(key: String): String = config.text(key, language)
}

val LocalWords = compositionLocalOf<Words> { error("LocalWords not provided") }

val LocalBrand = compositionLocalOf { Brand(ThemeConfig()) }

/** Colours from the server-driven theme (a bad value falls back to the brand colour, never a crash). */
class Brand(theme: ThemeConfig) {
    val primary = color(theme.primary, 0xFFFF4D2D)
    val accent = color(theme.accent, 0xFFD97706)
    val background = color(theme.background, 0xFFFFF7ED)
    val surface = color(theme.surface, 0xFFFAFAFD)
    val peach = color(theme.peach, 0xFFFFEDD5)
    val line = color(theme.line, 0xFFE2E8F0)
    val ink = color(theme.ink, 0xFF0F172A)
    val muted = color(theme.muted, 0xFF475569)
    val danger = color(theme.danger, 0xFFEF4444)
    val success = color(theme.success, 0xFF10B981)
    val radius = theme.radius.coerceIn(0, 28)

    /**
     * The primary colour for small text (labels, links, the active tab): the same hue, deepened just enough
     * for WCAG AA 4.5:1 on the page (the website's --brand-text, #D33417 for the default coral). Fills,
     * buttons and icons keep [primary].
     */
    val primaryText: Color = readable(primary, surface)

    private fun readable(c: Color, ground: Color): Color {
        var x = c
        repeat(24) {
            if (contrast(x, ground) >= 4.6f) return x
            x = androidx.compose.ui.graphics.lerp(x, Color.Black, 0.04f)
        }
        return x
    }

    private fun contrast(a: Color, b: Color): Float {
        val la = a.luminance() + 0.05f
        val lb = b.luminance() + 0.05f
        return if (la > lb) la / lb else lb / la
    }

    private fun color(hex: String, fallback: Long): Color {
        val value = hex.removePrefix("#").takeIf { it.length == 6 }?.toLongOrNull(16)

        return Color(0xFF000000 or (value ?: (fallback and 0xFFFFFF)))
    }
}

/**
 * Corners grow with the surface (CAMPBUDDY-BRAND-GUIDELINES.md §5): chips 6 · inputs and small boxes 10 · rows 14 ·
 * cards 20 (the server theme's `radius`) · sheets 28 · buttons and pills round.
 */
object Radii {
    val chip = RoundedCornerShape(6.dp)
    val input = RoundedCornerShape(10.dp)
    val row = RoundedCornerShape(14.dp)
    val sheet = RoundedCornerShape(28.dp)
    val pill = RoundedCornerShape(percent = 50)
}

/**
 * Typography is fixed in the app (design, not content), the website's brand fonts bundled so the app reads the same
 * on every phone (the system font can be anything, even a serif a person picked): Poppins for text and UI, Baloo 2
 * for headings (guideline §4). Both carry Latin, Latin Extended and Devanagari.
 */
@Composable
fun poppinsFamily(): FontFamily = FontFamily(
    Font(Res.font.poppins_regular, FontWeight.Normal),
    Font(Res.font.poppins_medium, FontWeight.Medium),
    Font(Res.font.poppins_semibold, FontWeight.SemiBold),
    Font(Res.font.poppins_bold, FontWeight.Bold),
)

@Composable
fun balooFamily(): FontFamily = FontFamily(
    Font(Res.font.baloo2_semibold, FontWeight.SemiBold),
    Font(Res.font.baloo2_bold, FontWeight.Bold),
    Font(Res.font.baloo2_extrabold, FontWeight.ExtraBold),
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
    val family = poppinsFamily()
    val display = balooFamily()
    // The website's own sizes, measured on the PWA at phone width (Daylight 2.0, 30 Oct 2026): section/page
    // title Baloo 2 28 w800 (line 32.2, -0.02em), its line under it 15.2; card title Baloo 18 w800; body 16 / 14,
    // small 13.12, badge 12 w700; buttons 14.4 w600 (.btn), chips 14 w600.
    val typography = Typography(
        headlineSmall = style(display, 28, FontWeight.ExtraBold, 32, brand.ink).copy(lineHeight = 32.2.sp, letterSpacing = (-0.56).sp),
        titleLarge = style(display, 18, FontWeight.ExtraBold, 24, brand.ink).copy(lineHeight = 24.3.sp),
        titleMedium = style(family, 16, FontWeight.Bold, 24, brand.ink),
        bodyLarge = style(family, 16, FontWeight.Normal, 24, brand.ink),
        bodyMedium = style(family, 14, FontWeight.Normal, 21, brand.ink),
        bodySmall = style(family, 13, FontWeight.Normal, 20, brand.muted).copy(fontSize = 13.12.sp, lineHeight = 19.68.sp),
        labelLarge = style(family, 14, FontWeight.SemiBold, 18, Color.Unspecified).copy(fontSize = 14.4.sp),
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
