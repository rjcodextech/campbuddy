package club.campbuddy.shared.model

import kotlinx.serialization.SerialName
import kotlinx.serialization.Serializable

/** { "data": ... } */
@Serializable
data class Data<T>(val data: T)

@Serializable
data class EventCard(
    val slug: String,
    val name: String,
    @SerialName("short_name") val shortName: String? = null,
    val logo: String? = null,
    val mark: String? = null,
    @SerialName("starts_on") val startsOn: String? = null,
    @SerialName("ends_on") val endsOn: String? = null,
    @SerialName("first_day") val firstDay: String? = null,
    @SerialName("last_day") val lastDay: String? = null,
    val timezone: String = "UTC",
    val country: String? = null,
    val completed: Boolean = false,
    val website: String? = null,
    val venue: String? = null,
    val memories: Memories? = null,
)

@Serializable
data class EventDetail(
    val slug: String,
    val name: String,
    @SerialName("short_name") val shortName: String? = null,
    val logo: String? = null,
    val mark: String? = null,
    @SerialName("starts_on") val startsOn: String? = null,
    @SerialName("ends_on") val endsOn: String? = null,
    @SerialName("first_day") val firstDay: String? = null,
    @SerialName("last_day") val lastDay: String? = null,
    val timezone: String = "UTC",
    val country: String? = null,
    val completed: Boolean = false,
    val website: String? = null,
    val tagline: String? = null,
    @SerialName("data_version") val dataVersion: String? = null,
)

@Serializable
data class Session(
    val id: Long,
    val title: String,
    @SerialName("starts_at") val startsAt: String? = null,
    @SerialName("ends_at") val endsAt: String? = null,
    @SerialName("duration_seconds") val durationSeconds: Long? = null,
    @SerialName("track_names") val trackNames: List<String> = emptyList(),
    @SerialName("speaker_ids") val speakerIds: List<Long> = emptyList(),
    val description: String? = null,
    @SerialName("session_type") val sessionType: String? = null,
    @SerialName("category_names") val categoryNames: List<String> = emptyList(),
)

@Serializable
data class Speaker(
    val id: Long,
    val name: String,
    @SerialName("bio_text") val bioText: String? = null,
    @SerialName("avatar_url") val avatarUrl: String? = null,
)

// ---- organizer ----------------------------------------------------------------------------------

@Serializable
data class LoginRequest(val email: String, val password: String, val role: String, @SerialName("device_name") val deviceName: String)

@Serializable
data class Person(val id: Long, val name: String, val email: String)

@Serializable
data class LoginResponse(val token: String, @SerialName("expires_at") val expiresAt: String, val role: String, val user: Person)

@Serializable
data class OrganizerEvent(
    val id: Long,
    val slug: String,
    val name: String,
    val status: String,
    @SerialName("is_visible") val isVisible: Boolean = true,
    @SerialName("starts_on") val startsOn: String? = null,
    val timezone: String = "UTC",
    @SerialName("open_reports") val openReports: Int = 0,
    @SerialName("ends_on") val endsOn: String? = null,
    @SerialName("short_name") val shortName: String? = null,
    @SerialName("roster_count") val rosterCount: Int = 0,
    val timing: Timing? = null,
)

@Serializable
data class ReportEntry(val reason: String, val label: String, val note: String? = null, val at: String)

@Serializable
data class ReportedProfile(val name: String? = null, val profession: String? = null)

@Serializable
data class OpenReport(
    @SerialName("discovery_id") val discoveryId: String,
    @SerialName("profile_still_there") val profileStillThere: Boolean = true,
    val profile: ReportedProfile? = null,
    val reports: List<ReportEntry> = emptyList(),
)

@Serializable
data class ReportsData(val open: List<OpenReport> = emptyList(), val handled: List<HandledReport> = emptyList())

@Serializable
data class HandledReport(val reason: String = "", val status: String = "", val profile: ReportedProfile? = null, val at: String? = null)

@Serializable
data class DelayInfo(val minutes: Int = 0, val text: String? = null, val track: String? = null)

@Serializable
data class DelayData(val active: List<DelayInfo> = emptyList(), val tracks: List<String> = emptyList(), val timezone: String = "UTC")

@Serializable
data class DelayRequest(val minutes: Int, val track: String, val from: String, val note: String? = null)

@Serializable
data class OrganizerCounts(
    val roster: Int = 0,
    val discovery: Int = 0,
    val sessions: Int = 0,
    val speakers: Int = 0,
    val sponsors: Int = 0,
    val tables: Int = 0,
    @SerialName("open_reports") val openReports: Int = 0,
    val quests: Int = 0,
    val deals: Int = 0,
)

@Serializable
data class OrganizerInfo(
    val wifi: String? = null,
    val venue: String? = null,
    @SerialName("emergency_contact") val emergencyContact: String? = null,
    @SerialName("registration_info") val registrationInfo: String? = null,
    @SerialName("contributor_day_location") val contributorDayLocation: String? = null,
    @SerialName("social_event_info") val socialEventInfo: String? = null,
    @SerialName("nearby_venue_info") val nearbyVenueInfo: String? = null,
    @SerialName("important_links") val importantLinks: String? = null,
    @SerialName("code_of_conduct_url") val codeOfConductUrl: String? = null,
)

@Serializable
data class OrganizerMemoriesData(
    @SerialName("photo_album_url") val photoAlbumUrl: String? = null,
    @SerialName("photo_album_notified_at") val photoAlbumNotifiedAt: String? = null,
    @SerialName("wordpress_tv_url") val wordpressTvUrl: String? = null,
    @SerialName("wordpress_tv_notified_at") val wordpressTvNotifiedAt: String? = null,
    @SerialName("subscribers_count") val subscribersCount: Int = 0,
)

@Serializable
data class OrganizerOverview(
    val id: Long,
    val slug: String,
    val name: String,
    val status: String,
    @SerialName("is_visible") val isVisible: Boolean = true,
    @SerialName("starts_on") val startsOn: String? = null,
    val timezone: String = "UTC",
    val counts: OrganizerCounts = OrganizerCounts(),
    val delay: List<DelayInfo> = emptyList(),
    val info: OrganizerInfo = OrganizerInfo(),
    val memories: OrganizerMemoriesData = OrganizerMemoriesData(),
    @SerialName("ends_on") val endsOn: String? = null,
    val timing: Timing? = null,
    val role: String? = null,
)

@Serializable
data class UpdateInfoRequest(
    val wifi: String? = null,
    val venue: String? = null,
    @SerialName("emergency_contact") val emergencyContact: String? = null,
    @SerialName("registration_info") val registrationInfo: String? = null,
    @SerialName("contributor_day_location") val contributorDayLocation: String? = null,
)

@Serializable
data class UpdateMemoriesRequest(
    @SerialName("photo_album_url") val photoAlbumUrl: String? = null,
    @SerialName("wordpress_tv_url") val wordpressTvUrl: String? = null,
)

@Serializable
data class NotifyMemoriesResponse(
    val sent: Boolean = false,
    val message: String = "",
)

@Serializable
data class OrganizerAttendeeEntry(
    val id: Long,
    val name: String,
    @SerialName("gravatar_url") val avatarUrl: String? = null,
    val roles: List<String> = emptyList(),
    @SerialName("auto_roles") val autoRoles: List<String> = emptyList(),
    val talks: List<String> = emptyList(),
    @SerialName("is_suppressed") val isSuppressed: Boolean = false,
    @SerialName("in_discovery") val inDiscovery: Boolean = false,
    @SerialName("has_camp_card") val hasCampCard: Boolean = false,
)

@Serializable
data class UpdateAttendeeRolesRequest(
    val roles: List<String> = emptyList(),
)

