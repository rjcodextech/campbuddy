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

/** A saved session; the title and start are kept so reminders and My schedule work offline. */
@Serializable
data class Bookmark(val sessionId: Long, val title: String = "", val startsAt: String? = null, val endsAt: String? = null)

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
        val next = now.map { b -> byId[b.sessionId]?.let { Bookmark(it.id, it.title, it.startsAt, it.endsAt) } ?: b }
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

    // ---- storage ----

    private fun <T> load(key: String, serializer: kotlinx.serialization.KSerializer<T>): T? =
        store.get("user:$key")?.let { runCatching { AppJson.decodeFromString(serializer, it) }.getOrNull() }

    private fun <T> save(key: String, serializer: kotlinx.serialization.KSerializer<T>, value: T) =
        store.put("user:$key", AppJson.encodeToString(serializer, value))

    /** Everything on this phone goes (Settings > delete my data). */
    fun eraseAll() {
        listOf("profile", "notifications", "bookmarks", "quests", "campcard").forEach { store.remove("user:$it") }
        profile = Profile()
        notifications = NotificationState()
        bookmarkMap = emptyMap()
        questMap = emptyMap()
        cardMap = emptyMap()
    }

    @Suppress("FunctionName")
    private fun Instant30(start: kotlinx.datetime.Instant) = kotlinx.datetime.Instant.fromEpochSeconds(start.epochSeconds + 30 * 60)
}
