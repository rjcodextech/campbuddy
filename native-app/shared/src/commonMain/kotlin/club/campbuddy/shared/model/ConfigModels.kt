package club.campbuddy.shared.model

import kotlinx.serialization.SerialName
import kotlinx.serialization.Serializable

/**
 * GET /api/v1/app/config (schema/app-config.schema.json). The server edits these values
 * (Admin > Mobile app); the app only ever draws components it already has.
 */
@Serializable
data class AppConfig(
    val schema: Int = 1,
    val version: Int = 0,
    @SerialName("min_app") val minApp: MinApp = MinApp(),
    val flags: Map<String, Boolean> = emptyMap(),
    val theme: ThemeConfig = ThemeConfig(),
    val banner: BannerConfig? = null,
    val tabs: List<TabConfig> = emptyList(),
    val home: HomeConfig = HomeConfig(),
    val organizer: OrganizerConfig = OrganizerConfig(),
    val onboarding: OnboardingConfig = OnboardingConfig(),
    val strings: Map<String, Map<String, String>> = emptyMap(),
) {
    fun flag(name: String): Boolean = flags[name] ?: true

    /** A text in the phone language, else English, else the key itself (never blank, never a crash). */
    fun text(key: String, language: String): String =
        strings[language]?.get(key)?.takeIf { it.isNotBlank() } ?: strings["en"]?.get(key) ?: key

    fun enabledTabs(): List<TabConfig> = tabs.filter { it.enabled }.sortedBy { it.order }

    fun enabledSections(): List<SectionConfig> = home.sections.filter { it.enabled }.sortedBy { it.order }
}

@Serializable
data class MinApp(val android: String = "0.0.1", val ios: String = "0.0.1")

@Serializable
data class ThemeConfig(
    val primary: String = "#FF4D2D",
    /** The main colour as small text (links, the active tab): WCAG AA. The website's --brand-text. */
    @SerialName("primary_text") val primaryText: String = "#D33417",
    val accent: String = "#d97706",
    val background: String = "#FFF7ED",
    val surface: String = "#FAFAFD",
    val peach: String = "#FFEDD5",
    val line: String = "#E2E8F0",
    val ink: String = "#0F172A",
    val muted: String = "#475569",
    val danger: String = "#EF4444",
    val success: String = "#10B981",
    val radius: Int = 20,
    val logo: String? = null,
    @SerialName("splash_text") val splashText: String = "",
)

@Serializable
data class BannerConfig(
    val id: String = "",
    val text: Map<String, String> = emptyMap(),
    val style: String = "info",
    val action: BannerAction? = null,
    val until: String? = null,
) {
    val dismissKey: String get() = id.ifEmpty { (text["en"] ?: text.values.firstOrNull()).orEmpty() }
}

@Serializable
data class BannerAction(val type: String, val url: String? = null, val slug: String? = null, val tab: String? = null)

@Serializable
data class TabConfig(
    val key: String,
    val icon: String = "star",
    val title: String,
    val enabled: Boolean = true,
    val order: Int = 0,
)

@Serializable
data class HomeConfig(val sections: List<SectionConfig> = emptyList())

@Serializable
data class SectionConfig(
    val type: String,
    val title: String? = null,
    val enabled: Boolean = true,
    val order: Int = 0,
    @SerialName("visible_if") val visibleIf: String? = null,
)

@Serializable
data class OrganizerConfig(val tabs: List<TabConfig> = emptyList())

@Serializable
data class OnboardingConfig(val enabled: Boolean = true)
