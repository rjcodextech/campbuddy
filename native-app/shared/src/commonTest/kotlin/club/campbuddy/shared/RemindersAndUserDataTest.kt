package club.campbuddy.shared

import club.campbuddy.shared.data.KeyValueStore
import club.campbuddy.shared.data.NotificationState
import club.campbuddy.shared.data.Profile
import club.campbuddy.shared.data.UserData
import club.campbuddy.shared.domain.Reminders
import club.campbuddy.shared.model.Session
import kotlin.test.Test
import kotlin.test.assertEquals
import kotlin.test.assertFalse
import kotlin.test.assertNotEquals
import kotlin.test.assertNull
import kotlin.test.assertTrue

private class MemoryStore : KeyValueStore {
    val map = mutableMapOf<String, String>()
    override fun get(key: String) = map[key]
    override fun put(key: String, value: String) { map[key] = value }
    override fun remove(key: String) { map.remove(key) }
}

class RemindersAndUserDataTest {
    private fun session(id: Long, start: String, minutes: Long = 60) = Session(
        id = id,
        title = "Talk $id",
        startsAt = start,
        endsAt = kotlinx.datetime.Instant.parse(start).let { kotlinx.datetime.Instant.fromEpochSeconds(it.epochSeconds + minutes * 60).toString() },
    )

    @Test
    fun theIdIsTheSameNumberTheWebsiteMakes() {
        // Reference values from the JavaScript in resources/js/attendee/native-bridge.js.
        assertEquals(17674752, Reminders.id(3, 101))
        assertEquals(68007609, Reminders.id(3, 102))
        assertEquals(1789752645, Reminders.id(12, 7))
        assertNotEquals(Reminders.id(3, 101), Reminders.id(4, 101))
    }

    @Test
    fun aReminderComesTenMinutesBeforeAndNeverForThePast() {
        val now = kotlinx.datetime.Instant.parse("2026-11-01T09:00:00Z").toEpochMilliseconds()
        val talk = session(7, "2026-11-01T09:30:00Z")

        val reminder = Reminders.forSession("wordcamp-x", 3, talk, now)!!
        assertEquals(talk.startsAt!!.let { kotlinx.datetime.Instant.parse(it).toEpochMilliseconds() } - 600_000, reminder.atEpochMs)
        assertTrue(reminder.title.contains("Talk 7"))
        assertEquals("/event/wordcamp-x/my-day", reminder.path)

        assertNull(Reminders.forSession("x", 3, session(8, "2026-11-01T09:05:00Z"), now), "starts in 5 minutes: too late")
        assertNull(Reminders.forSession("x", 3, session(9, "2026-11-01T08:00:00Z"), now))
        assertNull(Reminders.forSession("x", 3, Session(id = 10, title = "TBA"), now))
    }

    @Test
    fun onlyTheSoonestRemindersAreKept() {
        val list = (1..100).map { Reminders.forSession("x", 1, session(it.toLong(), "2026-11-02T10:00:00Z").copy(startsAt = kotlinx.datetime.Instant.fromEpochSeconds(2_000_000_000L + it * 3600).toString()), 0)!! }

        val picked = Reminders.soonest(list.reversed())
        assertEquals(60, picked.size)
        assertEquals(list.first().atEpochMs, picked.first().atEpochMs)
    }

    @Test
    fun savedSessionsSurviveARestartAndOverlapsAreFound() {
        val store = MemoryStore()
        val data = UserData(store)
        val a = session(1, "2026-11-01T10:00:00Z")
        val b = session(2, "2026-11-01T10:30:00Z")
        val c = session(3, "2026-11-01T12:00:00Z")

        assertTrue(data.toggle("wc", a))
        assertEquals(listOf(1L), data.overlapping("wc", b).map { it.sessionId }, "b starts while a is on")
        assertTrue(data.overlapping("wc", c).isEmpty())

        val again = UserData(store)
        assertTrue(again.isSaved("wc", 1))
        assertFalse(again.toggle("wc", a), "tapping again un-saves")
        assertFalse(UserData(store).isSaved("wc", 1))
    }

    @Test
    fun aMovedSessionKeepsItsSavedTimeFresh() {
        val data = UserData(MemoryStore())
        data.toggle("wc", session(1, "2026-11-01T10:00:00Z"))

        data.refreshBookmarks("wc", listOf(session(1, "2026-11-01T10:20:00Z")))

        assertEquals("2026-11-01T10:20:00Z", data.bookmarks("wc").single().startsAt)
    }

    @Test
    fun theProfileTheReminderChoiceAndQuestsAreKept() {
        val store = MemoryStore()
        val data = UserData(store)
        data.saveProfile(Profile(firstWordCamp = "yes", interests = listOf("developer"), completedAt = 5))
        data.saveNotifications(NotificationState(declined = true))
        data.toggleQuest("wc", 4)

        val again = UserData(store)
        assertTrue(again.onboardingDone)
        assertEquals(listOf("developer"), again.profile.interests)
        assertTrue(again.notifications.declined)
        assertEquals(setOf(4L), again.completedQuests("wc"))

        again.eraseAll()
        assertFalse(UserData(store).onboardingDone)
        assertTrue(UserData(store).completedQuests("wc").isEmpty())
    }
}
