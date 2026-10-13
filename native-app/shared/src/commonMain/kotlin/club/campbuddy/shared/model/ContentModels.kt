package club.campbuddy.shared.model

import kotlinx.serialization.SerialName
import kotlinx.serialization.Serializable

@Serializable
data class Sponsor(
    val id: Long,
    val name: String,
    val website: String? = null,
    @SerialName("logo_url") val logoUrl: String? = null,
    @SerialName("tier_names") val tierNames: List<String> = emptyList(),
    val description: String? = null,
)

@Serializable
data class Deal(
    val id: Long,
    val brand: String = "",
    val title: String,
    val highlight: String? = null,
    val description: String? = null,
    val terms: String? = null,
    @SerialName("coupon_code") val couponCode: String? = null,
    val url: String? = null,
    @SerialName("cta_label") val ctaLabel: String? = null,
    val logo: String? = null,
)

@Serializable
data class FreeSteal(
    val id: Long,
    val name: String,
    val description: String? = null,
    val maker: String? = null,
    val category: String? = null,
    val url: String? = null,
    @SerialName("cta_label") val ctaLabel: String? = null,
    val logo: String? = null,
    val featured: Boolean = false,
)

@Serializable
data class Quest(val id: Long, val title: String, val description: String? = null, val source: String = "event")

@Serializable
data class ContributorTable(
    val team: String? = null,
    val name: String = "",
    val place: String = "",
    val leads: List<String> = emptyList(),
    val note: String? = null,
)

@Serializable
data class RosterLink(val url: String, val type: String? = null)

@Serializable
data class RosterEntry(
    val id: Long,
    val name: String,
    @SerialName("open_to_meet") val openToMeet: Boolean = false,
    @SerialName("gravatar_url") val avatarUrl: String? = null,
    val links: List<RosterLink> = emptyList(),
    val roles: List<String> = emptyList(),
    val talks: List<String> = emptyList(),
)

@Serializable
data class RosterPage(val data: List<RosterEntry> = emptyList(), @SerialName("last_page") val lastPage: Int = 1, @SerialName("current_page") val currentPage: Int = 1)

// ---- the first-timer guide ----

@Serializable
data class GuideDay(val icon: String = "star", val title: String, val what: String, val tip: String = "")

@Serializable
data class GuideTerm(val term: String, val meaning: String)

@Serializable
data class GuideTip(val icon: String = "star", val title: String, val text: String)

@Serializable
data class GuideQuestion(val q: String, val a: String)

@Serializable
data class Guide(
    val day: List<GuideDay> = emptyList(),
    val glossary: List<GuideTerm> = emptyList(),
    val tips: List<GuideTip> = emptyList(),
    val bring: List<String> = emptyList(),
    val faq: List<GuideQuestion> = emptyList(),
)

/** Photo album and WordPress.tv links once an organizer adds them. */
@Serializable
data class Memories(val photos: String? = null, val videos: String? = null)
