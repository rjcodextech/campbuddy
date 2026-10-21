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
    val primary: String = "#C33A19",
    val accent: String = "#E39D1C",
    val background: String = "#FFF0DF",
    val surface: String = "#FFFAF4",
    val peach: String = "#FFE4C4",
    val line: String = "#EADFD6",
    val ink: String = "#231F20",
    val muted: String = "#6B625E",
    val danger: String = "#B3261E",
    val success: String = "#1F7A4D",
    val radius: Int = 5,
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
