package club.campbuddy.shared.domain

import club.campbuddy.shared.model.Session

/** One notification the phone should show (a local reminder: no server, works offline). */
data class Reminder(val id: Int, val title: String, val body: String, val atEpochMs: Long, val path: String)

/**
 * Session reminders, ported from the website bridge (native-bridge.js): ten minutes before the
 * start, never for the past, and only the soonest few because iOS keeps just 64 pending.
 * RemindersTest holds the same cases as tests/js/native-bridge.test.mjs.
 */
object Reminders {
    const val LEAD_MS = 10 * 60 * 1000L
    const val MAX_SCHEDULED = 60

    /** Stable positive 31-bit id for an (event, session) pair; the same numbers the website makes. */
    fun id(eventKey: Any, sessionId: Long): Int {
        var hash = 2166136261L.toInt()
        for (ch in "$eventKey:$sessionId") {
            hash = (hash xor ch.code) * 16777619
        }

        return hash and 0x7fffffff
    }

    fun forSession(eventSlug: String, eventKey: Any, session: Session, nowMs: Long): Reminder? {
        val start = EventClock.startOf(session)?.toEpochMilliseconds() ?: return null
        val at = start - LEAD_MS
        if (at <= nowMs) return null

        return Reminder(
            id = id(eventKey, session.id),
            title = if (session.title.isNotBlank()) "Starting soon: ${session.title}" else "A session you saved starts soon",
            body = "Starts in 10 minutes. Open My Day for the room.",
            atEpochMs = at,
            path = "/event/$eventSlug/my-day",
        )
    }

    fun soonest(reminders: List<Reminder?>, limit: Int = MAX_SCHEDULED): List<Reminder> =
        reminders.filterNotNull().sortedBy { it.atEpochMs }.take(limit)
}
