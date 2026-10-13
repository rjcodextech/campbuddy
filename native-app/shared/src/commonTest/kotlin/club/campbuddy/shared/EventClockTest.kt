package club.campbuddy.shared

import club.campbuddy.shared.domain.EventClock
import club.campbuddy.shared.domain.Phase
import club.campbuddy.shared.model.Session
import kotlinx.datetime.Instant
import kotlinx.datetime.TimeZone
import kotlin.test.Test
import kotlin.test.assertEquals
import kotlin.test.assertNull

class EventClockTest {
    private val kolkata = TimeZone.of("Asia/Kolkata")

    private fun session(id: Long, start: String, minutes: Long = 60) = Session(
        id = id,
        title = "Talk $id",
        startsAt = start,
        endsAt = Instant.parse(start).let { Instant.fromEpochSeconds(it.epochSeconds + minutes * 60).toString() },
    )

    private val sessions = listOf(
        session(1, "2026-10-10T10:00:00+05:30"),
        session(2, "2026-10-10T10:00:00+05:30"),
        session(3, "2026-10-10T12:00:00+05:30"),
    )

    @Test
    fun beforeTheFirstDayItIsBeforeAndTheDaysAreCounted() {
        val now = Instant.parse("2026-10-07T08:00:00+05:30")

        assertEquals(Phase.Before, EventClock.phase(sessions, "2026-10-10", "2026-10-10", kolkata, now))
        assertEquals(3, EventClock.daysUntil("2026-10-10", kolkata, now))
    }

    @Test
    fun onTheDayBeforeTheFirstSessionItIsStillBefore() {
        val now = Instant.parse("2026-10-10T08:00:00+05:30")

        assertEquals(Phase.Before, EventClock.phase(sessions, "2026-10-10", "2026-10-10", kolkata, now))
        assertEquals(0, EventClock.daysUntil("2026-10-10", kolkata, now))
    }

    @Test
    fun duringTheEventSessionsOnNowAreFoundAndTheNextOneIsKnown() {
        val now = Instant.parse("2026-10-10T10:30:00+05:30")

        assertEquals(Phase.During, EventClock.phase(sessions, "2026-10-10", "2026-10-10", kolkata, now))
        assertEquals(listOf(1L, 2L), EventClock.current(sessions, now).map { it.id })
        assertEquals(3L, EventClock.upNext(sessions, now)?.id)
    }

    @Test
    fun afterTheLastSessionItIsOver() {
        val now = Instant.parse("2026-10-10T20:00:00+05:30")

        assertEquals(Phase.After, EventClock.phase(sessions, "2026-10-10", "2026-10-10", kolkata, now))
        assertNull(EventClock.upNext(sessions, now))
    }

    @Test
    fun theDayIsTheEventsOwnNotThePhones() {
        // 23:00 UTC on the 9th is already the 10th in Kolkata.
        val now = Instant.parse("2026-10-09T23:00:00Z")

        assertEquals(0, EventClock.daysUntil("2026-10-10", kolkata, now))
        assertEquals(1, EventClock.daysUntil("2026-10-10", TimeZone.UTC, now))
    }

    @Test
    fun aSessionWithNoLengthStillCountsAsOnForThirtyMinutes() {
        val open = Session(id = 9, title = "Open mic", startsAt = "2026-10-10T15:00:00+05:30")

        assertEquals(1, EventClock.current(listOf(open), Instant.parse("2026-10-10T15:20:00+05:30")).size)
        assertEquals(0, EventClock.current(listOf(open), Instant.parse("2026-10-10T15:40:00+05:30")).size)
    }

    @Test
    fun theWordsMatchTheWebsite() {
        val start = Instant.parse("2026-10-10T14:30:00+05:30")

        assertEquals("2:30 PM", EventClock.time(start, kolkata))
        assertEquals("12:05 AM", EventClock.time(Instant.parse("2026-10-10T00:05:00+05:30"), kolkata))
        assertEquals("in 12 min", EventClock.when_(start, Instant.parse("2026-10-10T14:18:00+05:30"), kolkata))
        assertEquals("at 2:30 PM", EventClock.when_(start, Instant.parse("2026-10-10T09:00:00+05:30"), kolkata))
        assertEquals("Sat 10 Oct, 2:30 PM", EventClock.when_(start, Instant.parse("2026-10-08T09:00:00+05:30"), kolkata))
    }

    @Test
    fun choicesAtTheSameMomentAreListed() {
        assertEquals(listOf(2L), EventClock.alternatives(sessions, sessions[0]).map { it.id })
    }
}
