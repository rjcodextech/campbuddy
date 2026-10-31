package club.campbuddy.shared

import club.campbuddy.shared.data.NotificationState
import club.campbuddy.shared.domain.NotificationChoices
import kotlinx.datetime.LocalDate
import kotlin.test.Test
import kotlin.test.assertEquals
import kotlin.test.assertFalse
import kotlin.test.assertTrue

/** Notifications that were turned down (domain/NotificationChoices): when the notice shows, and what "Your choices" says. */
class NotificationChoicesTest {
    private val today = LocalDate.parse("2026-11-20")
    private val lastDay = LocalDate.parse("2026-11-22")
    private val now = 1_800_000_000_000L

    private fun show(status: NotificationChoices.Status, hiddenAt: Long? = null, justNow: Boolean = false, on: LocalDate = today) =
        NotificationChoices.showOffNotice(status, on, lastDay, hiddenAt, now, justNow)

    @Test
    fun onlyAfterANoNeverToSomeoneNotAskedOrWhoSaidYes() {
        assertFalse(show(NotificationChoices.status(NotificationState(), permitted = false, liveAnswer = null)), "never asked")
        assertFalse(show(NotificationChoices.status(NotificationState(enabled = true), permitted = true, liveAnswer = null)), "on")
        listOf(
            NotificationChoices.status(NotificationState(declined = true), false, null),
            NotificationChoices.status(NotificationState(denied = true), false, null),
            NotificationChoices.status(NotificationState(), false, "no"),
            NotificationChoices.status(NotificationState(enabled = true), permitted = false, liveAnswer = null),
        ).forEach { assertTrue(show(it), it.toString()) }
    }

    @Test
    fun notNowHidesItForADayAndNeverAfterTheEvent() {
        val off = NotificationChoices.status(NotificationState(declined = true), false, null)
        assertFalse(show(off, hiddenAt = now - 60_000))
        assertTrue(show(off, hiddenAt = now - NotificationChoices.HIDE_FOR_MS))
        assertTrue(show(off, hiddenAt = now - 60_000, justNow = true), "just said no")
        assertFalse(show(off, on = LocalDate.parse("2026-11-23")), "the event is over")
    }

    @Test
    fun aNoToThePhoneOnlyItsSettingsCanUndo() {
        val blocked = NotificationChoices.status(NotificationState(denied = true), permitted = false, liveAnswer = null)
        assertTrue(blocked.blocked)
        assertEquals("Blocked in the phone's settings", NotificationChoices.label(blocked))
        assertEquals("Off", NotificationChoices.label(NotificationChoices.status(NotificationState(declined = true), false, null)))
        assertEquals("On", NotificationChoices.label(NotificationChoices.status(NotificationState(enabled = true), true, null)))
        // Allowed in the settings since: no longer blocked, and on once the app switches it on.
        assertFalse(NotificationChoices.status(NotificationState(denied = true), permitted = true, liveAnswer = null).blocked)
    }
}
