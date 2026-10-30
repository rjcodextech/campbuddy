package club.campbuddy.shared.model

import kotlinx.serialization.SerialName
import kotlinx.serialization.Serializable

// Attendee discovery (people who chose to be found). The public card is what everyone sees; the owner
// token is shown once, on joining, and lives only on this phone.

@Serializable
data class DiscoveryFields(
    val tags: List<String> = emptyList(),
    val profession: String? = null,
    @SerialName("who_to_meet") val whoToMeet: String? = null,
)

@Serializable
data class DiscoveryCard(
    @SerialName("discovery_id") val discoveryId: String,
    val name: String? = null,
    @SerialName("on_attendee_list") val onAttendeeList: Boolean = false,
    @SerialName("roster_id") val rosterId: Long? = null,
    @SerialName("wporg_url") val wporgUrl: String? = null,
    val fields: DiscoveryFields = DiscoveryFields(),
)

@Serializable
data class DiscoveryJoined(
    @SerialName("discovery_id") val discoveryId: String,
    @SerialName("owner_token") val ownerToken: String,
    val name: String? = null,
)

@Serializable
data class DiscoveryForm(
    val tags: List<String>,
    val profession: String? = null,
    @SerialName("who_to_meet") val whoToMeet: String? = null,
    @SerialName("display_name") val displayName: String? = null,
    @SerialName("wporg_username") val wporgUsername: String? = null,
    @SerialName("attendee_roster_id") val attendeeRosterId: Long? = null,
    /** The consent tick, sent when joining (left out when editing: null is the default, so not sent). */
    val consent: Boolean? = null,
)

@Serializable
data class WaveMessage(val mine: Boolean = false, val body: String = "", val at: String? = null, val day: String? = null)

@Serializable
data class PendingWave(@SerialName("discovery_id") val discoveryId: String, val messages: List<WaveMessage> = emptyList())

@Serializable
data class MutualWave(
    @SerialName("discovery_id") val discoveryId: String,
    val name: String? = null,
    val messages: List<WaveMessage> = emptyList(),
    val mine: Int = 0,
    val theirs: Int = 0,
    @SerialName("can_send") val canSend: Boolean = false,
    val reason: String? = null,
)

@Serializable
data class WaveState(
    @SerialName("max_messages") val maxMessages: Int = 3,
    val sent: List<String> = emptyList(),
    val received: List<String> = emptyList(),
    @SerialName("received_with_message") val receivedWithMessage: List<String> = emptyList(),
    val pending: List<PendingWave> = emptyList(),
    val mutual: List<MutualWave> = emptyList(),
)

@Serializable
data class WaveRequest(val to: String, val name: String? = null, val message: String? = null)

@Serializable
data class MessageRequest(val to: String, val body: String)

@Serializable
data class ReportRequest(@SerialName("device_id") val deviceId: String, val reason: String, val note: String? = null)
