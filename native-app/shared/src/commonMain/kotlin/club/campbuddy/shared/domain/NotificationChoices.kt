package club.campbuddy.shared.domain

import club.campbuddy.shared.data.NotificationState
import kotlinx.datetime.LocalDate

/**
 * Notifications that were turned down, and the way back (owner, 31 Oct 2026; the website's
 * notification-choices.js does the same). Someone who said no — "No thanks" to reminders, "Not now" on the
 * live-updates card, the phone's own "Don't allow", or allowing and later switching it off in the phone's
 * settings — is told what that turns off and that everything else still works, with one button back.
 * In the app one switch covers talk reminders and the organizers' updates: both need the phone's
 * permission and [NotificationState.enabled].
 */
object NotificationChoices {
    /** "Not now" hides the notice for a day. */
    const val HIDE_FOR_MS = 24 * 60 * 60 * 1000L

    val OFF_ITEMS = listOf("A reminder before the talks you save", "Live updates from the organizers (a session starting, lunch served)")
    const val STILL_WORKS = "Everything else still works: the schedule, your saved talks, People, your Camp Card, even offline."
    const val BLOCKED = "CampBuddy isn't allowed to show notifications on this phone. In the phone's settings for CampBuddy: Notifications → Allow. Then come back: CampBuddy turns them on by itself."
    const val TURNED_ON = "Notifications are on. You'll get a reminder before your saved talks and the organizers' updates."

    /**
     * [on]: reminders and updates reach the phone. [turnedDown]: the person said no (or took a yes back) and
     * notifications are off. [blocked]: the phone said no, so only its settings can turn them on.
     */
    data class Status(val on: Boolean, val turnedDown: Boolean, val blocked: Boolean)

    fun status(state: NotificationState, permitted: Boolean, liveAnswer: String?): Status {
        val on = state.enabled && permitted
        val saidNo = state.declined || state.denied || liveAnswer == "no" || (state.enabled && !permitted)
        return Status(on = on, turnedDown = saidNo && !on, blocked = state.denied && !permitted)
    }

    /** Whether to show "Notifications are off": after a no, until the event is over, and not for a day after "Not now" (unless they just said no). */
    fun showOffNotice(status: Status, today: LocalDate, lastDay: LocalDate?, hiddenAtMs: Long?, nowMs: Long, justNow: Boolean = false): Boolean {
        if (!status.turnedDown) return false
        if (lastDay != null && today > lastDay) return false
        if (justNow) return true
        return hiddenAtMs == null || nowMs - hiddenAtMs >= HIDE_FOR_MS
    }

    /** One row of "Your choices". */
    fun label(status: Status): String = when {
        status.on -> "On"
        status.blocked -> "Blocked in the phone's settings"
        else -> "Off"
    }
}
