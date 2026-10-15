package club.campbuddy.shared

import club.campbuddy.shared.data.Bookmark
import club.campbuddy.shared.data.DataChoice
import club.campbuddy.shared.data.KeyValueStore
import club.campbuddy.shared.data.Meeting
import club.campbuddy.shared.data.UserData
import club.campbuddy.shared.ui.EVENT_DATA_DAYS
import club.campbuddy.shared.ui.deleteAtMillis
import club.campbuddy.shared.ui.deleteDay
import kotlinx.datetime.LocalDate
import kotlinx.datetime.TimeZone
import kotlin.test.Test
import kotlin.test.assertEquals
import kotlin.test.assertNotNull
import kotlin.test.assertNull
import kotlin.test.assertTrue

private class ChoiceStore : KeyValueStore {
    private val map = mutableMapOf<String, String>()
    override fun get(key: String) = map[key]
    override fun put(key: String, value: String) { map[key] = value }
    override fun remove(key: String) { map.remove(key) }
}

class AfterEventChoiceTest {
    private fun phoneWithTwoEvents(): UserData {
        val data = UserData(ChoiceStore())
        data.saveMeeting("wc-a", Meeting(1, "Asha", note = "hi"))
        data.saveMeeting("wc-b", Meeting(2, "Dev", note = "hello"))
        data.saveCard(mapOf("name" to "Me"))
        return data
    }

    @Test
    fun nothingIsAskedOrDeletedUntilThePersonAnswers() {
        val data = phoneWithTwoEvents()
        assertNull(data.dataChoice("wc-a"))
        assertTrue(data.hasEventData("wc-a"))
        assertEquals(emptyList(), data.dueForDeletion(Long.MAX_VALUE))
    }

    @Test
    fun keepingMeansItIsNeverDeleted() {
        val data = phoneWithTwoEvents()
        data.setDataChoice("wc-a", DataChoice(keep = true))
        assertEquals(emptyList(), data.dueForDeletion(Long.MAX_VALUE))
    }

    @Test
    fun deleteAfterTheTimeRemovesOnlyThatEventsData() {
        val data = phoneWithTwoEvents()
        val last = LocalDate(2026, 10, 4)
        val at = deleteAtMillis(last, TimeZone.of("Asia/Kolkata"))
        data.setDataChoice("wc-a", DataChoice(keep = false, deleteAt = at))

        assertEquals(emptyList(), data.dueForDeletion(at - 1), "not before the day")
        assertEquals(listOf("wc-a"), data.dueForDeletion(at))

        data.eraseEvent("wc-a")
        assertEquals(emptyList(), data.meetings("wc-a"))
        assertEquals(1, data.meetings("wc-b").size, "another WordCamp's data is untouched")
        assertEquals("Me", data.card()["name"], "the Camp Card belongs to no single WordCamp")
        assertEquals(emptyList(), data.dueForDeletion(Long.MAX_VALUE), "nothing left to delete")
    }

    @Test
    fun theDeleteDayIsNinetyDaysAfterTheLastDay() {
        assertEquals(90, EVENT_DATA_DAYS)
        assertEquals(LocalDate(2027, 1, 2), deleteDay(LocalDate(2026, 10, 4)))
    }

    @Test
    fun clearingEverythingAlsoForgetsTheChoices() {
        val data = phoneWithTwoEvents()
        data.saveBookmarkForTest()
        data.setDataChoice("wc-a", DataChoice(keep = true))
        data.eraseAll()
        assertNull(data.dataChoice("wc-a"))
        assertNotNull(data.exportJson())
    }
}

private fun UserData.saveBookmarkForTest() {
    toggle("wc-a", club.campbuddy.shared.model.Session(id = 1, title = "Talk", startsAt = "2026-10-03T04:30:00Z", endsAt = "2026-10-03T05:00:00Z"))
}
