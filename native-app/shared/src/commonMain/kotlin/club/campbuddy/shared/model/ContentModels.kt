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
    val link: String? = null,
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
    val website: String? = null,
    @SerialName("capture_leads") val captureLeads: Boolean = false,
    @SerialName("lead_form") val leadForm: LeadForm? = null,
)

/** The short form a deal asks for before it opens (the website's DealForm): which fields, which are needed, and the choices. */
@Serializable
data class LeadField(val mode: String = "off", val label: String = "", val hint: String? = null)

@Serializable
data class LeadChoices(val mode: String = "off", val label: String = "", val multiple: Boolean = false, val options: List<String> = emptyList())

@Serializable
data class LeadForm(
    val intro: String? = null,
    val fields: Map<String, LeadField> = emptyMap(),
    val choices: LeadChoices? = null,
)

@Serializable
data class LeadRequest(val name: String? = null, val company: String? = null, val email: String, val mobile: String? = null, val choices: List<String> = emptyList())

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
    @SerialName("made_here") val madeHere: Boolean = false,
    val icon: String = "sparkles",
    val host: String = "",
)

@Serializable
data class Quest(val id: Long, val title: String, val description: String? = null, val source: String = "event")

@Serializable
data class RosterLink(val url: String, val type: String? = null)

@Serializable
data class ContributorLeadDetail(
    val name: String,
    val avatar: String? = null,
    val designation: String? = null,
    val bio: String? = null,
    val url: String? = null,
    val links: List<RosterLink> = emptyList(),
    @SerialName("roster_id") val rosterId: Long? = null,
)

@Serializable
data class ContributorTable(
    val team: String? = null,
    val name: String = "",
    val place: String = "",
    val leads: List<String> = emptyList(),
    @SerialName("lead_details") val leadDetails: List<ContributorLeadDetail> = emptyList(),
    val note: String? = null,
)

@Serializable
data class RosterEntry(
    val id: Long,
    val name: String,
    @SerialName("open_to_meet") val openToMeet: Boolean = false,
    @SerialName("gravatar_url") val avatarUrl: String? = null,
    val links: List<RosterLink> = emptyList(),
    val roles: List<String> = emptyList(),
    val talks: List<String> = emptyList(),
    @SerialName("camp_card") val campCard: kotlinx.serialization.json.JsonElement? = null,
)

@Serializable
data class RosterPage(val data: List<RosterEntry> = emptyList(), @SerialName("last_page") val lastPage: Int = 1, @SerialName("current_page") val currentPage: Int = 1)

// ---- the first-timer guide ----

@Serializable
data class GuideDay(val icon: String = "star", val title: String, val what: String, val tip: String = "", val match: List<String> = emptyList(), val talk: Boolean = false)

@Serializable
data class GuideTerm(val term: String, val meaning: String)

@Serializable
data class GuideTip(val icon: String = "star", val title: String, val text: String)

@Serializable
data class GuideQuestion(val q: String, val a: String)

/** What to do right now while a session of this kind is on (the same lines the website shows on Home). */
@Serializable
data class GuideMoment(val match: List<String> = emptyList(), val now: String = "", val key: Boolean = false, val talk: Boolean = false)

@Serializable
data class Guide(
    val moments: List<GuideMoment> = emptyList(),
    val day: List<GuideDay> = emptyList(),
    val glossary: List<GuideTerm> = emptyList(),
    val tips: List<GuideTip> = emptyList(),
    val bring: List<String> = emptyList(),
    val faq: List<GuideQuestion> = emptyList(),
    val students: GuideStudents = GuideStudents(),
)

@Serializable
data class GuideStudents(val intro: String = "", val points: List<GuideTip> = emptyList())

/** Photo album and WordPress.tv links once an organizer adds them. */
@Serializable
data class Memories(val photos: String? = null, val videos: String? = null)

// ---- Explore -> Info (the server decides what shows and which rows are links) ----

@Serializable
data class InfoAction(val label: String = "", val type: String = "url", val value: String = "")

@Serializable
data class InfoRow(
    val icon: String = "link",
    val title: String,
    val text: String? = null,
    val action: InfoAction? = null,
    val alert: Boolean = false,
    val mono: Boolean = false,
)

@Serializable
data class InfoGroup(val title: String, val rows: List<InfoRow> = emptyList())

@Serializable
data class LegalDoc(
    val slug: String = "",
    val title: String = "",
    val updated: String = "",
    @SerialName("has_custom_content") val hasCustomContent: Boolean = false,
    val content: String? = null,
    val entity: String = "",
    @SerialName("grievance_email") val grievanceEmail: String = "",
    @SerialName("reply_days") val replyDays: Int = 30,
)
