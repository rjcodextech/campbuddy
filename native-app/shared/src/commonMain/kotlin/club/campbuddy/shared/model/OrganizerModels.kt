package club.campbuddy.shared.model

import kotlinx.serialization.SerialName
import kotlinx.serialization.Serializable
import kotlinx.serialization.json.JsonElement

// The organizer side's own pages: every event tab of the web panel and the admin's platform pages
// (OrganizerManageController, OrganizerAdminController on the server).

/** "Happening now" / "In 5 days" / "Ended 3 days ago" (EventListing::timing); state is ongoing, upcoming or past. */
@Serializable
data class Timing(val state: String = "", val label: String = "")

@Serializable
data class PageMeta(
    val page: Int = 1,
    @SerialName("last_page") val lastPage: Int = 1,
    val total: Int = 0,
    @SerialName("per_page") val perPage: Int = 0,
    val roles: List<KeyLabel> = emptyList(),
)

@Serializable
data class Paged<T>(val data: List<T> = emptyList(), val meta: PageMeta = PageMeta())

/** A write's answer: the saved thing (when there is one) and the sentence to show. */
@Serializable
data class Reply<T>(val data: T? = null, val message: String? = null)

@Serializable
data class Msg(val message: String? = null)

/** A choice from the server: roles have a label, teams a name. */
@Serializable
data class KeyLabel(val key: String, val label: String = "", val name: String = "") {
    val text: String get() = label.ifBlank { name.ifBlank { key } }
}

@Serializable
data class IdName(val id: Long, val name: String = "")

// ---- event details ------------------------------------------------------------------------------

@Serializable
data class DataCounts(
    val sessions: Int? = null,
    val speakers: Int? = null,
    val sponsors: Int? = null,
    val organizers: Int? = null,
    val attendees: Int = 0,
)

@Serializable
data class FetchRow(val job: String = "", val status: String = "", val message: String? = null, val at: String? = null)

@Serializable
data class EventDetails(
    val id: Long,
    val slug: String,
    @SerialName("display_name") val displayName: String,
    @SerialName("short_name") val shortName: String? = null,
    @SerialName("source_site_url") val sourceSiteUrl: String = "",
    val status: String = "draft",
    @SerialName("is_visible") val isVisible: Boolean = true,
    @SerialName("starts_on") val startsOn: String? = null,
    @SerialName("ends_on") val endsOn: String? = null,
    val timezone: String? = null,
    @SerialName("timezone_used") val timezoneUsed: String = "UTC",
    @SerialName("timezone_locked") val timezoneLocked: Boolean = false,
    @SerialName("dates_locked") val datesLocked: Boolean = false,
    val timing: Timing? = null,
    @SerialName("public_url") val publicUrl: String? = null,
    @SerialName("data_counts") val dataCounts: DataCounts? = null,
    @SerialName("recent_fetches") val recentFetches: List<FetchRow> = emptyList(),
)

/** The details form. A manager's request carries the status too, and the server ignores it. */
@Serializable
data class DetailsForm(
    val slug: String,
    @SerialName("display_name") val displayName: String,
    @SerialName("short_name") val shortName: String?,
    @SerialName("source_site_url") val sourceSiteUrl: String,
    @SerialName("starts_on") val startsOn: String?,
    @SerialName("ends_on") val endsOn: String?,
    val timezone: String?,
    @SerialName("is_visible") val isVisible: Boolean,
    val status: String,
)

@Serializable
data class NewEventForm(
    val slug: String,
    @SerialName("display_name") val displayName: String,
    @SerialName("short_name") val shortName: String?,
    @SerialName("source_site_url") val sourceSiteUrl: String,
    @SerialName("starts_on") val startsOn: String?,
    @SerialName("ends_on") val endsOn: String?,
    val timezone: String?,
    @SerialName("is_visible") val isVisible: Boolean,
    val status: String,
)

@Serializable
data class ConfirmBody(val confirm: String)

@Serializable
data class FetchResult(val level: String = "ok", val message: String = "")

/** Every field of "Event information"; a blank one is removed on the server. */
@Serializable
data class EventInformation(
    val venue: String? = null,
    val wifi: String? = null,
    @SerialName("registration_info") val registrationInfo: String? = null,
    @SerialName("emergency_contact") val emergencyContact: String? = null,
    @SerialName("contributor_day_location") val contributorDayLocation: String? = null,
    @SerialName("social_event_info") val socialEventInfo: String? = null,
    @SerialName("nearby_venue_info") val nearbyVenueInfo: String? = null,
    @SerialName("important_links") val importantLinks: String? = null,
    @SerialName("code_of_conduct_url") val codeOfConductUrl: String? = null,
)

// ---- quests, tables ----------------------------------------------------------------------------

@Serializable
data class OrgQuest(
    val id: Long,
    val title: String,
    val description: String? = null,
    @SerialName("sort_order") val sortOrder: Int = 0,
    @SerialName("is_active") val isActive: Boolean = true,
    val source: String = "event",
)

@Serializable
data class QuestForm(
    val title: String,
    val description: String?,
    @SerialName("sort_order") val sortOrder: Int?,
    @SerialName("is_active") val isActive: Boolean,
)

@Serializable
data class OrgTable(
    val id: Long,
    val team: String,
    val title: String? = null,
    val track: String? = null,
    val floor: String? = null,
    @SerialName("table_no") val tableNo: String? = null,
    val leads: String = "",
    val note: String? = null,
    @SerialName("sort_order") val sortOrder: Int = 0,
    val name: String = "",
    val place: String = "",
    /** "site": read from the WordCamp site (saving it here makes it "manual", kept as typed). */
    val source: String = "manual",
)

@Serializable
data class TablesData(
    val tables: List<OrgTable> = emptyList(),
    val teams: List<KeyLabel> = emptyList(),
    val names: List<String> = emptyList(),
)

@Serializable
data class TableForm(
    val team: String,
    val title: String?,
    val track: String?,
    val floor: String?,
    @SerialName("table_no") val tableNo: String?,
    val leads: String?,
    val note: String?,
    @SerialName("sort_order") val sortOrder: Int?,
)

// ---- notifications, activity ------------------------------------------------------------------

@Serializable
data class NotificationRow(
    val key: String = "",
    val title: String = "",
    val `when`: String = "",
    val text: String = "",
    val state: String = "",
    val status: String = "",
)

@Serializable
data class ActivityRow(
    val id: Long,
    val manager: String? = null,
    val event: String? = null,
    val section: String = "",
    val action: String = "",
    val summary: String = "",
    val at: String? = null,
)

@Serializable
data class ActivityPage(
    val data: List<ActivityRow> = emptyList(),
    val managers: List<IdName> = emptyList(),
    val events: List<IdName> = emptyList(),
    val meta: PageMeta = PageMeta(),
)

// ---- deals, leads -------------------------------------------------------------------------------

@Serializable
data class OrgDeal(
    val id: Long,
    val name: String = "",
    val brand: String? = null,
    val website: String? = null,
    val title: String = "",
    val highlight: String? = null,
    val description: String? = null,
    val terms: String? = null,
    @SerialName("coupon_code") val couponCode: String? = null,
    val url: String? = null,
    @SerialName("cta_label") val ctaLabel: String? = null,
    @SerialName("opens_in_app") val opensInApp: Boolean = true,
    val icon: String? = null,
    @SerialName("sort_order") val sortOrder: Int = 0,
    @SerialName("is_active") val isActive: Boolean = true,
    @SerialName("capture_leads") val captureLeads: Boolean = false,
    @SerialName("has_logo") val hasLogo: Boolean = false,
    @SerialName("leads_count") val leadsCount: Int = 0,
    @SerialName("hidden_here") val hiddenHere: Boolean = false,
    val countries: String? = null,
)

@Serializable
data class DealsData(val own: List<OrgDeal> = emptyList(), val defaults: List<OrgDeal> = emptyList())

@Serializable
data class DealForm(
    val brand: String?,
    val website: String?,
    val title: String,
    val highlight: String?,
    val description: String,
    val terms: String?,
    @SerialName("coupon_code") val couponCode: String?,
    val url: String,
    @SerialName("cta_label") val ctaLabel: String?,
    @SerialName("opens_in_app") val opensInApp: Boolean,
    val icon: String?,
    @SerialName("sort_order") val sortOrder: Int?,
    @SerialName("is_active") val isActive: Boolean,
    @SerialName("capture_leads") val captureLeads: Boolean,
)

@Serializable
data class HiddenBody(val hidden: Boolean)

@Serializable
data class OrgLead(
    val id: Long,
    val deal: String? = null,
    val name: String? = null,
    val company: String? = null,
    val email: String? = null,
    val mobile: String? = null,
    val choices: List<JsonElement> = emptyList(),
    val at: String? = null,
)

@Serializable
data class LeadsPage(val data: List<OrgLead> = emptyList(), val offers: List<IdName> = emptyList(), val meta: PageMeta = PageMeta())

// ---- the admin's platform pages -------------------------------------------------------------------

@Serializable
data class ErrorSummary(val system: Int = 0, val critical: Int = 0, val fetch: Int = 0, val total: Int = 0)

@Serializable
data class PurgeInfo(val at: String? = null, val by: String? = null)

@Serializable
data class LastFetch(val event: String? = null, val status: String = "", val message: String? = null, val at: String? = null)

@Serializable
data class AdminDashboard(
    val errors: ErrorSummary = ErrorSummary(),
    @SerialName("draft_count") val draftCount: Int = 0,
    val drafts: List<OrganizerEvent> = emptyList(),
    @SerialName("upcoming_count") val upcomingCount: Int = 0,
    @SerialName("next_events") val nextEvents: List<OrganizerEvent> = emptyList(),
    @SerialName("events_total") val eventsTotal: Int = 0,
    @SerialName("roster_count") val rosterCount: Int = 0,
    @SerialName("lead_count") val leadCount: Int = 0,
    @SerialName("recent_lead_count") val recentLeadCount: Int = 0,
    @SerialName("manager_count") val managerCount: Int = 0,
    @SerialName("feedback_count") val feedbackCount: Int = 0,
    @SerialName("last_purge") val lastPurge: PurgeInfo? = null,
    @SerialName("last_data_fetch") val lastDataFetch: LastFetch? = null,
)

@Serializable
data class SystemProblem(val level: String = "warning", val title: String = "", val detail: String = "", val fix: String? = null)

@Serializable
data class FetchProblem(
    @SerialName("event_id") val eventId: Long? = null,
    val event: String? = null,
    val job: String = "",
    val status: String = "",
    val message: String? = null,
    val times: Int = 1,
    @SerialName("last_at") val lastAt: String? = null,
)

@Serializable
data class ErrorFilters(val period: String = "7", val event: Long? = null, val job: String? = null, val result: String? = null)

@Serializable
data class ErrorsData(
    val system: List<SystemProblem> = emptyList(),
    val fetch: List<FetchProblem> = emptyList(),
    val filters: ErrorFilters = ErrorFilters(),
    val periods: List<KeyLabel> = emptyList(),
    val jobs: List<KeyLabel> = emptyList(),
)

@Serializable
data class ErrorsPage(val data: ErrorsData = ErrorsData(), val meta: PageMeta = PageMeta())

@Serializable
data class FeedbackSummary(
    @SerialName("event_id") val eventId: Long = 0,
    val event: String? = null,
    val answers: Int = 0,
    val average: Double = 0.0,
    val comments: Int = 0,
    val stars: List<Int> = emptyList(),
)

@Serializable
data class FeedbackItem(val id: Long, val event: String? = null, val rating: Int = 0, val comment: String? = null, val at: String? = null)

@Serializable
data class FeedbackData(val summary: List<FeedbackSummary> = emptyList(), val items: List<FeedbackItem> = emptyList())

@Serializable
data class FeedbackPage(val data: FeedbackData = FeedbackData(), val meta: PageMeta = PageMeta())

@Serializable
data class OrgManager(
    val id: Long,
    val name: String = "",
    val email: String = "",
    val phone: String? = null,
    @SerialName("is_active") val isActive: Boolean = true,
    @SerialName("last_login_at") val lastLoginAt: String? = null,
    val events: List<IdName> = emptyList(),
)

@Serializable
data class ManagerForm(
    val name: String,
    val email: String,
    val phone: String,
    val password: String?,
    @SerialName("is_active") val isActive: Boolean,
    val events: List<Long>,
)

@Serializable
data class EventOption(val id: Long, val name: String = "", val status: String = "", @SerialName("starts_on") val startsOn: String? = null)

// ---- social media -----------------------------------------------------------------------------------------

@Serializable
data class SocialEvent(
    val name: String = "",
    val dates: String = "",
    val place: String = "",
    val app: String = "",
    val hashtags: String = "",
    val logo: String? = null,
    val mark: String? = null,
    val slug: String = "",
    val short: String = "",
    val qr: String = "",
)

@Serializable
data class SocialColors(val primary: String = "#7a1f2b", val secondary: String = "#e0a11b", val ink: String = "#231f20", val paper: String = "#fff8ee")

@Serializable
data class SocialPost(
    val key: String,
    val label: String = "",
    val kicker: String = "",
    val headline: String = "",
    val line: String = "",
    val body: String = "none",
    val caption: String = "",
    val days: Int? = null,
    val link: String = "",
    /** "day" (Today: pick a day) or "session" (Spotlight: pick a talk). */
    val picker: String? = null,
)

@Serializable
data class SocialPerson(
    val id: Long,
    val name: String = "",
    val photo: String? = null,
    val roles: List<String> = emptyList(),
    val talk: String? = null,
    val caption: String = "",
    val link: String? = null,
    @SerialName("role_keys") val roleKeys: List<String> = emptyList(),
    @SerialName("has_card") val hasCard: Boolean = false,
    @SerialName("card_role") val cardRole: String? = null,
)

@Serializable
data class SocialSession(
    val id: String,
    val title: String = "",
    val day: String = "",
    val time: String = "",
    val track: String? = null,
    val speakers: List<String> = emptyList(),
    val photos: List<String> = emptyList(),
)

@Serializable
data class SocialSchedule(val days: List<KeyLabel> = emptyList(), val next: String? = null, val sessions: List<SocialSession> = emptyList())

@Serializable
data class SocialTableCard(val team: String = "", val name: String = "", val place: String = "", val leads: List<String> = emptyList(), val note: String? = null)

@Serializable
data class SocialFace(val name: String = "", val photo: String? = null, val logo: String? = null, val tier: String? = null)

@Serializable
data class ArtPiece(val id: String = "", val url: String = "", val kind: String = "", val name: String = "", val uploaded: Boolean = false, val hidden: Boolean = false)

@Serializable
data class SiteThemeData(
    val colors: List<String> = emptyList(),
    val tagline: String? = null,
    val art: List<ArtPiece> = emptyList(),
    val more: List<ArtPiece> = emptyList(),
    val error: String? = null,
)

@Serializable
data class QrCodeRow(val key: String = "", val label: String = "", val hint: String = "", val url: String = "")

@Serializable
data class WebhookState(val set: Boolean = false, val masked: String? = null)

@Serializable
data class SocialKitData(
    val event: SocialEvent = SocialEvent(),
    val colors: SocialColors = SocialColors(),
    @SerialName("colors_saved") val colorsSaved: Boolean = false,
    val posts: List<SocialPost> = emptyList(),
    val people: List<SocialPerson> = emptyList(),
    val speakers: List<SocialFace> = emptyList(),
    val sponsors: List<SocialFace> = emptyList(),
    val site: SiteThemeData = SiteThemeData(),
    val qr: List<QrCodeRow> = emptyList(),
    val webhook: WebhookState = WebhookState(),
    val schedule: SocialSchedule = SocialSchedule(),
    val tables: List<SocialTableCard> = emptyList(),
)

/** The colours as sent (no default values: the JSON setup leaves defaults out, and the server needs all four). */
@Serializable
data class ColorsBody(val primary: String, val secondary: String, val ink: String, val paper: String)

@Serializable
data class ArtBody(val piece: String, val kind: String? = null)

@Serializable
data class WebhookBody(val webhook: String? = null, val remove: Boolean = false)

// ---- analytics (admin) -----------------------------------------------------------------------------------------

@Serializable
data class AnalyticsFilters(
    val stream: String? = null,
    val period: String = "10",
    val from: String = "",
    val to: String = "",
    val bots: Boolean = true,
    val invalid: Boolean = false,
)

/** Admin → Analytics: GA4 reports by name, each a list of rows (dimension and metric names → values as text). */
@Serializable
data class AnalyticsData(
    val filters: AnalyticsFilters = AnalyticsFilters(),
    val periods: List<KeyLabel> = emptyList(),
    val streams: List<KeyLabel> = emptyList(),
    val error: String? = null,
    @SerialName("fetched_at") val fetchedAt: String? = null,
    val reports: Map<String, List<Map<String, String>>> = emptyMap(),
    val events: Map<String, String> = emptyMap(),
)

// ---- live announcements ---------------------------------------------------------------------------------

/** The announcement the organizers sent in the last half hour (the banner on Home). */
@Serializable
data class LiveNotice(val id: Long, val title: String = "", val body: String = "", val tab: String? = null, val at: String? = null)

@Serializable
data class BroadcastPreset(val key: String, val label: String = "", val icon: String = "megaphone", val title: String = "", val body: String = "")

@Serializable
data class BroadcastReach(val web: Int = 0, val app: Int = 0, val seen: Int = 0)

@Serializable
data class BroadcastSession(val id: String, val title: String = "", val time: String = "", val track: String? = null)

@Serializable
data class BroadcastSent(
    val id: Long,
    val title: String = "",
    val body: String = "",
    val tab: String? = null,
    val at: String? = null,
    @SerialName("sent_by") val sentBy: String = "",
    val recipients: Int = 0,
    val kind: String = "",
)

@Serializable
data class BroadcastData(
    val ready: Boolean = true,
    val presets: List<BroadcastPreset> = emptyList(),
    val reach: BroadcastReach = BroadcastReach(),
    @SerialName("window_from") val windowFrom: String? = null,
    val sessions: List<BroadcastSession> = emptyList(),
    val history: List<BroadcastSent> = emptyList(),
)

/** Empty fields are left out (the server then uses the ready-made words). */
@Serializable
data class BroadcastForm(
    val kind: String,
    val title: String? = null,
    val body: String? = null,
    val where: String? = null,
    @SerialName("session_id") val sessionId: String? = null,
)

@Serializable
internal data class SeenBody(@SerialName("device_id") val deviceId: String, val platform: String)
