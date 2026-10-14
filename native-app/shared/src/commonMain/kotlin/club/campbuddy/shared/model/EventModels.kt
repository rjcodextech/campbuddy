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
data class ReportsData(val open: List<OpenReport> = emptyList())

@Serializable
data class DelayInfo(val minutes: Int = 0, val text: String? = null, val track: String? = null)

@Serializable
data class DelayData(val active: List<DelayInfo> = emptyList(), val tracks: List<String> = emptyList(), val timezone: String = "UTC")

@Serializable
data class DelayRequest(val minutes: Int, val track: String, val from: String, val note: String? = null)
