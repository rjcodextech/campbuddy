package club.campbuddy.shared.data

import androidx.compose.runtime.getValue
import androidx.compose.runtime.mutableStateOf
import androidx.compose.runtime.setValue
import club.campbuddy.shared.domain.EventClock
import club.campbuddy.shared.model.Session
import kotlinx.serialization.Serializable
import kotlinx.serialization.builtins.ListSerializer
import kotlinx.serialization.builtins.MapSerializer
import kotlinx.serialization.builtins.serializer
import kotlinx.serialization.encodeToString

/** The five "Make CampBuddy yours" answers (same record the website keeps, same keys). */
@Serializable
data class Profile(
    val firstWordCamp: String? = null,
    val interests: List<String> = emptyList(),
    val profession: String? = null,
    val whoToMeet: String? = null,
    val wporg: String? = null,
    val attendingContributorDay: String? = null,
    val completedAt: Long = 0,
)

/** This phone's own discovery profile for one event: its public id and the secret that proves it is ours. */
@Serializable
data class MyDiscovery(val discoveryId: String, val ownerToken: String)

/** The shared Camp Card: its id, the secret that proves it is ours, and whose name it is shown under. */
@Serializable
data class MyShare(val shareId: String, val ownerToken: String, val rosterId: Long, val name: String)

/** A saved session; the title and start are kept so reminders and My schedule work offline. */
@Serializable
data class Bookmark(val sessionId: Long, val title: String = "", val startsAt: String? = null, val endsAt: String? = null, val status: String? = null)

/** Someone this person plans to meet at an event (kept on this phone only; they are not told). [at] is a local "YYYY-MM-DDTHH:MM" or null for any time. */
@Serializable
data class Meeting(val rosterId: Long, val name: String, val avatarUrl: String? = null, val sub: String = "", val note: String = "", val at: String? = null, val status: String? = null)

/**
 * What the person chose to happen to one finished WordCamp's data on this phone: [keep] it, or have it deleted
 * automatically at [deleteAt] (epoch milliseconds, 90 days after the event, as on our servers).
 */
@Serializable
data class DataChoice(val keep: Boolean, val deleteAt: Long = 0)

/** The reminder question: asked once after the first save, a "no thanks" is final, a denial is final. */
@Serializable
data class NotificationState(val enabled: Boolean = false, val declined: Boolean = false, val denied: Boolean = false)

/** Everything a person keeps on their own phone. Nothing here is ever sent to the server. */
class UserData(private val store: KeyValueStore) {
    var profile by mutableStateOf(load("profile", Profile.serializer()) ?: Profile())
        private set

    var notifications by mutableStateOf(load("notifications", NotificationState.serializer()) ?: NotificationState())
        private set

    private var bookmarkMap by mutableStateOf(load("bookmarks", MapSerializer(String.serializer(), ListSerializer(Bookmark.serializer()))) ?: emptyMap())

    private var questMap by mutableStateOf(load("quests", MapSerializer(String.serializer(), ListSerializer(Long.serializer()))) ?: emptyMap())

    private var cardMap by mutableStateOf(load("campcard", MapSerializer(String.serializer(), String.serializer())) ?: emptyMap())

    private var discoveryMap by mutableStateOf(load("discovery", MapSerializer(String.serializer(), MyDiscovery.serializer())) ?: emptyMap())

    private var metMap by mutableStateOf(load("meetings", MapSerializer(String.serializer(), ListSerializer(Meeting.serializer()))) ?: emptyMap())

    private var shareMap by mutableStateOf(load("cardshare", MapSerializer(String.serializer(), MyShare.serializer())) ?: emptyMap())

    private var choiceMap by mutableStateOf(load("retention", MapSerializer(String.serializer(), DataChoice.serializer())) ?: emptyMap())

    private var blockedMap by mutableStateOf(load("blocked", MapSerializer(String.serializer(), ListSerializer(String.serializer()))) ?: emptyMap())

    /** Goes up on every change to the saved sessions (so reminders can be planned again). */
    var revision by mutableStateOf(0)
        private set

    val onboardingDone: Boolean get() = profile.completedAt > 0

    fun allBookmarks(): Map<String, List<Bookmark>> = bookmarkMap

    fun saveProfile(value: Profile) {
        profile = value
        save("profile", Profile.serializer(), value)
    }

    fun saveNotifications(value: NotificationState) {
        notifications = value
        save("notifications", NotificationState.serializer(), value)
    }

    // ---- saved sessions ----

    fun bookmarks(slug: String): List<Bookmark> = bookmarkMap[slug].orEmpty()

    fun isSaved(slug: String, sessionId: Long): Boolean = bookmarks(slug).any { it.sessionId == sessionId }

    /** Saves or un-saves; returns what changed. */
    fun toggle(slug: String, session: Session): Boolean {
        val now = bookmarks(slug)
        val saved = now.none { it.sessionId == session.id }
        val next = if (saved) now + Bookmark(session.id, session.title, session.startsAt, session.endsAt) else now.filterNot { it.sessionId == session.id }
        bookmarkMap = bookmarkMap + (slug to next)
        save("bookmarks", MapSerializer(String.serializer(), ListSerializer(Bookmark.serializer())), bookmarkMap)
        revision++

        return saved
    }

    /** Fresh titles and times (a "running late" change moves them) for what is already saved. */
    fun refreshBookmarks(slug: String, sessions: List<Session>) {
        val byId = sessions.associateBy { it.id }
        val now = bookmarks(slug)
        val next = now.map { b -> byId[b.sessionId]?.let { Bookmark(it.id, it.title, it.startsAt, it.endsAt, b.status) } ?: b }
        if (next != now) {
            bookmarkMap = bookmarkMap + (slug to next)
            save("bookmarks", MapSerializer(String.serializer(), ListSerializer(Bookmark.serializer())), bookmarkMap)
            revision++
        }
    }

    /** The other saved sessions that run at the same time as [session]. */
    fun overlapping(slug: String, session: Session): List<Bookmark> {
        val start = EventClock.startOf(session) ?: return emptyList()
        val end = EventClock.endOf(session) ?: return emptyList()

        return bookmarks(slug).filter { b ->
            val bs = EventClock.instant(b.startsAt) ?: return@filter false
            val be = EventClock.instant(b.endsAt)?.takeIf { it > bs } ?: Instant30(bs)
            b.sessionId != session.id && start < be && bs < end
        }
    }

    // ---- quests ----

    fun completedQuests(slug: String): Set<Long> = questMap[slug].orEmpty().toSet()

    fun toggleQuest(slug: String, questId: Long) {
        val now = completedQuests(slug)
        val next = if (questId in now) now - questId else now + questId
        questMap = questMap + (slug to next.toList())
        save("quests", MapSerializer(String.serializer(), ListSerializer(Long.serializer())), questMap)
    }

    // ---- Camp Card ----

    fun card(): Map<String, String> = cardMap

    fun saveCard(fields: Map<String, String>) {
        cardMap = fields.filterValues { it.isNotBlank() }
        save("campcard", MapSerializer(String.serializer(), String.serializer()), cardMap)
    }

    // ---- attendee discovery (this phone's own profile, its secret token and who it hid) ----

    fun discovery(slug: String): MyDiscovery? = discoveryMap[slug]

    fun saveDiscovery(slug: String, mine: MyDiscovery) {
        discoveryMap = discoveryMap + (slug to mine)
        save("discovery", MapSerializer(String.serializer(), MyDiscovery.serializer()), discoveryMap)
    }

    fun forgetDiscovery(slug: String) {
        discoveryMap = discoveryMap - slug
        save("discovery", MapSerializer(String.serializer(), MyDiscovery.serializer()), discoveryMap)
    }

    /** The people planned (or marked met / not met / hidden) at one event. */
    fun meetings(slug: String): List<Meeting> = metMap[slug].orEmpty()

    fun meeting(slug: String, rosterId: Long): Meeting? = meetings(slug).firstOrNull { it.rosterId == rosterId }

    fun saveMeeting(slug: String, meeting: Meeting) {
        metMap = metMap + (slug to (meetings(slug).filterNot { it.rosterId == meeting.rosterId } + meeting))
        save("meetings", MapSerializer(String.serializer(), ListSerializer(Meeting.serializer())), metMap)
        revision++
    }

    fun removeMeeting(slug: String, rosterId: Long) {
        metMap = metMap + (slug to meetings(slug).filterNot { it.rosterId == rosterId })
        save("meetings", MapSerializer(String.serializer(), ListSerializer(Meeting.serializer())), metMap)
        revision++
    }

    /** Attended / Missed on a saved session (pressing the same one again clears it). */
    fun setSessionStatus(slug: String, sessionId: Long, status: String?) {
        val now = bookmarks(slug)
        val next = now.map { if (it.sessionId == sessionId) it.copy(status = if (it.status == status) null else status) else it }
        bookmarkMap = bookmarkMap + (slug to next)
        save("bookmarks", MapSerializer(String.serializer(), ListSerializer(Bookmark.serializer())), bookmarkMap)
    }

    /** This phone's Camp Card on one event's attendee list, if it was turned on. */
    fun share(slug: String): MyShare? = shareMap[slug]

    fun saveShare(slug: String, mine: MyShare) {
        shareMap = shareMap + (slug to mine)
        save("cardshare", MapSerializer(String.serializer(), MyShare.serializer()), shareMap)
    }

    fun forgetShare(slug: String) {
        shareMap = shareMap - slug
        save("cardshare", MapSerializer(String.serializer(), MyShare.serializer()), shareMap)
    }

    fun blocked(slug: String): Set<String> = blockedMap[slug].orEmpty().toSet()

    fun block(slug: String, discoveryId: String) {
        blockedMap = blockedMap + (slug to (blocked(slug) + discoveryId).toList())
        save("blocked", MapSerializer(String.serializer(), ListSerializer(String.serializer())), blockedMap)
    }

    /** The person's answer to "keep this WordCamp's data or delete it later?", or null while they have not answered. */
    fun dataChoice(slug: String): DataChoice? = choiceMap[slug]

    fun setDataChoice(slug: String, choice: DataChoice) {
        choiceMap = choiceMap + (slug to choice)
        save("retention", MapSerializer(String.serializer(), DataChoice.serializer()), choiceMap)
    }

    /** Whether this phone holds anything of the person's for one WordCamp (so it is worth asking what to do with it). */
    fun hasEventData(slug: String): Boolean =
        bookmarks(slug).isNotEmpty() || completedQuests(slug).isNotEmpty() || meetings(slug).isNotEmpty() || discovery(slug) != null || share(slug) != null

    /** WordCamps whose data the person asked to have deleted and whose time has come. */
    fun dueForDeletion(nowMillis: Long): List<String> = choiceMap.filter { (slug, c) -> !c.keep && c.deleteAt in 1..nowMillis && hasEventData(slug) }.keys.toList()

    /** Deletes what this phone holds for one WordCamp (saved sessions, quests, people to meet, discovery and card-sharing codes). Not the profile or Camp Card, which belong to no single event. */
    fun eraseEvent(slug: String) {
        bookmarkMap = bookmarkMap - slug
        questMap = questMap - slug
        metMap = metMap - slug
        discoveryMap = discoveryMap - slug
        shareMap = shareMap - slug
        blockedMap = blockedMap - slug
        save("bookmarks", MapSerializer(String.serializer(), ListSerializer(Bookmark.serializer())), bookmarkMap)
        save("quests", MapSerializer(String.serializer(), ListSerializer(Long.serializer())), questMap)
        save("meetings", MapSerializer(String.serializer(), ListSerializer(Meeting.serializer())), metMap)
        save("discovery", MapSerializer(String.serializer(), MyDiscovery.serializer()), discoveryMap)
        save("cardshare", MapSerializer(String.serializer(), MyShare.serializer()), shareMap)
        save("blocked", MapSerializer(String.serializer(), ListSerializer(String.serializer())), blockedMap)
        revision++
    }

    /** A random id for this phone; reports carry it so one phone reports a profile once. */
    fun deviceId(): String = store.get("user:device") ?: List(32) { "0123456789abcdef".random() }.joinToString("").also { store.put("user:device", it) }

    // ---- storage ----

    private fun <T> load(key: String, serializer: kotlinx.serialization.KSerializer<T>): T? =
        store.get("user:$key")?.let { runCatching { AppJson.decodeFromString(serializer, it) }.getOrNull() }

    private fun <T> save(key: String, serializer: kotlinx.serialization.KSerializer<T>, value: T) =
        store.put("user:$key", AppJson.encodeToString(serializer, value))

    /** Everything on this phone goes (Settings > delete my data). */
    fun eraseAll() {
        listOf("profile", "notifications", "bookmarks", "quests", "campcard", "discovery", "blocked", "device", "meetings", "cardshare", "retention").forEach { store.remove("user:$it") }
        profile = Profile()
        notifications = NotificationState()
        bookmarkMap = emptyMap()
        questMap = emptyMap()
        cardMap = emptyMap()
        discoveryMap = emptyMap()
        blockedMap = emptyMap()
        metMap = emptyMap()
        shareMap = emptyMap()
        choiceMap = emptyMap()
    }

    /**
     * Everything this phone keeps for the person, as readable JSON (the website's "Download the raw data"): their right
     * to a copy of their data. Secret codes that prove ownership of a discovery profile or a shared card are left out.
     */
    fun exportJson(): String {
        val data = kotlinx.serialization.json.buildJsonObject {
            put("exportedAt", kotlinx.serialization.json.JsonPrimitive(kotlinx.datetime.Clock.System.now().toString()))
            put("about", kotlinx.serialization.json.JsonPrimitive("Everything CampBuddy keeps on this phone. Secret codes are left out."))
            put("profile", AppJson.encodeToJsonElement(Profile.serializer(), profile))
            put("notifications", AppJson.encodeToJsonElement(NotificationState.serializer(), notifications))
            put("savedSessions", AppJson.encodeToJsonElement(MapSerializer(String.serializer(), ListSerializer(Bookmark.serializer())), bookmarkMap))
            put("questsDone", AppJson.encodeToJsonElement(MapSerializer(String.serializer(), ListSerializer(Long.serializer())), questMap))
            put("campCard", AppJson.encodeToJsonElement(MapSerializer(String.serializer(), String.serializer()), cardMap))
            put("peopleToMeet", AppJson.encodeToJsonElement(MapSerializer(String.serializer(), ListSerializer(Meeting.serializer())), metMap))
            put("inAttendeeDiscovery", kotlinx.serialization.json.JsonArray(discoveryMap.keys.map { kotlinx.serialization.json.JsonPrimitive(it) }))
            put("campCardOnAttendeeList", kotlinx.serialization.json.JsonArray(shareMap.keys.map { kotlinx.serialization.json.JsonPrimitive(it) }))
            put("afterEventChoice", AppJson.encodeToJsonElement(MapSerializer(String.serializer(), DataChoice.serializer()), choiceMap))
            put("hiddenProfiles", AppJson.encodeToJsonElement(MapSerializer(String.serializer(), ListSerializer(String.serializer())), blockedMap))
        }

        return kotlinx.serialization.json.Json { prettyPrint = true }.encodeToString(kotlinx.serialization.json.JsonObject.serializer(), data)
    }

    @Suppress("FunctionName")
    private fun Instant30(start: kotlinx.datetime.Instant) = kotlinx.datetime.Instant.fromEpochSeconds(start.epochSeconds + 30 * 60)
}
