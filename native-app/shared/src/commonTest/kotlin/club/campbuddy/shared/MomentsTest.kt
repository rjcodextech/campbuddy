package club.campbuddy.shared

import club.campbuddy.shared.domain.Moments
import club.campbuddy.shared.model.GuideMoment
import club.campbuddy.shared.model.Session
import kotlin.test.Test
import kotlin.test.assertEquals
import kotlin.test.assertFalse
import kotlin.test.assertNull
import kotlin.test.assertTrue

class MomentsTest {
    private fun s(title: String, type: String? = null, speakers: Int = 0) = Session(id = 1, title = title, sessionType = type, speakerIds = List(speakers) { it.toLong() })

    @Test
    fun wholeWordsOnlyPluralsAllowed() {
        assertTrue(Moments.titleHasWord("Coffee breaks", listOf("break")))
        assertFalse(Moments.titleHasWord("Breakfast", listOf("break")))
        assertTrue(Moments.titleHasWord("Registration Open and Networking", listOf("registration")))
    }

    @Test
    fun aBreakNeverMatchesATalk() {
        val lunch = GuideMoment(match = listOf("break", "lunch"), talk = false, now = "Sit with people you do not know.")
        assertNull(Moments.momentFor(s("Don't Break Your Site", type = "session"), listOf(lunch)))
        assertEquals(lunch, Moments.momentFor(s("Lunch", type = "custom"), listOf(lunch)))
    }

    @Test
    fun noTypeMeansTalksHaveSpeakers() {
        assertTrue(Moments.isTalk(s("Anything", speakers = 1)))
        assertFalse(Moments.isTalk(s("Anything", speakers = 0)))
    }

    @Test
    fun interestsScoreByWordsInTheTitleAndCategories() {
        val session = Session(id = 2, title = "Building blocks for the editor", categoryNames = listOf("Development"))
        assertEquals(1, Moments.interestScore(session, listOf("developer")))
        assertEquals(0, Moments.interestScore(session, listOf("translator")))
    }
}
