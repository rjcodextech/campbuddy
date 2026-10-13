package club.campbuddy.shared.domain

import club.campbuddy.shared.model.Session
import kotlinx.datetime.DayOfWeek
import kotlinx.datetime.Instant
import kotlinx.datetime.LocalDate
import kotlinx.datetime.Month
import kotlinx.datetime.TimeZone
import kotlinx.datetime.toLocalDateTime

enum class Phase { Before, During, After }

/**
 * Where an event is in time, always in the EVENT time zone (never the phone's): "Day 2" and
 * "starts tomorrow" mean the same to everyone at the venue. Port of the website home.js rules;
 * EventClockTest holds the same cases.
 */
object EventClock {
    fun instant(value: String?): Instant? = value?.let { runCatching { Instant.parse(it) }.getOrNull() }

    fun zone(id: String): TimeZone = runCatching { TimeZone.of(id) }.getOrDefault(TimeZone.UTC)

    fun day(at: Instant, zone: TimeZone): LocalDate = at.toLocalDateTime(zone).date

    fun startOf(session: Session): Instant? = instant(session.startsAt)

    /** End of a session; without a length the first 30 minutes still count as "now". */
    fun endOf(session: Session): Instant? {
        val start = startOf(session) ?: return null
        val end = instant(session.endsAt)

        return if (end != null && end > start) end else Instant.fromEpochSeconds(start.epochSeconds + 30 * 60)
    }

    fun phase(sessions: List<Session>, firstDay: String?, lastDay: String?, zone: TimeZone, now: Instant): Phase {
        val timed = sessions.mapNotNull { s -> startOf(s)?.let { s to it } }.sortedBy { it.second }
        val today = day(now, zone)
        val startDay = firstDay?.let { runCatching { LocalDate.parse(it) }.getOrNull() } ?: timed.firstOrNull()?.second?.let { day(it, zone) }
        val endDay = lastDay?.let { runCatching { LocalDate.parse(it) }.getOrNull() } ?: timed.lastOrNull()?.second?.let { day(it, zone) } ?: startDay

        if (startDay != null && today < startDay) return Phase.Before

        val first = timed.firstOrNull()?.second
        if (first != null && now < first && today <= day(first, zone)) return Phase.Before

        val lastEnd = sessions.mapNotNull { endOf(it) }.maxOrNull()
        val anyAhead = timed.any { it.second > now }
        if ((lastEnd != null && now > lastEnd && !anyAhead) || (endDay != null && today > endDay)) return Phase.After

        return Phase.During
    }

    /** Sessions on right now, soonest start first. */
    fun current(sessions: List<Session>, now: Instant): List<Session> =
        sessions.filter { s ->
            val start = startOf(s)
            val end = endOf(s)
            start != null && end != null && start <= now && now < end
        }.sortedBy { startOf(it) }

    /** The soonest session that has not started. */
    fun upNext(sessions: List<Session>, now: Instant): Session? =
        sessions.filter { (startOf(it) ?: return@filter false) > now }.minByOrNull { startOf(it)!! }

    /** Everything starting at the same time as [session] (the choices at that moment). */
    fun alternatives(sessions: List<Session>, session: Session): List<Session> =
        sessions.filter { it.id != session.id && startOf(it) == startOf(session) }

    fun daysUntil(firstDay: String?, zone: TimeZone, now: Instant): Int? {
        val start = firstDay?.let { runCatching { LocalDate.parse(it) }.getOrNull() } ?: return null

        return start.toEpochDays() - day(now, zone).toEpochDays()
    }

    /** "10:00 AM" in the event time zone. */
    fun time(at: Instant, zone: TimeZone): String {
        val t = at.toLocalDateTime(zone)
        val hour12 = if (t.hour % 12 == 0) 12 else t.hour % 12

        return "$hour12:${t.minute.toString().padStart(2, '0')} ${if (t.hour < 12) "AM" else "PM"}"
    }

    /** "Sat 10 Oct" */
    fun dayLabel(date: LocalDate): String {
        val weekday = date.dayOfWeek.shortName()
        val month = date.month.shortName()

        return "$weekday ${date.dayOfMonth} $month"
    }

    /** "in 12 min" / "at 2:30 PM" / "Sat 10 Oct, 2:30 PM". */
    fun when_(start: Instant, now: Instant, zone: TimeZone): String {
        val minutes = ((start.epochSeconds - now.epochSeconds) / 60).toInt()
        if (minutes < 60) return "in ${maxOf(minutes, 1)} min"

        return if (day(start, zone) == day(now, zone)) "at ${time(start, zone)}" else "${dayLabel(day(start, zone))}, ${time(start, zone)}"
    }

    private fun DayOfWeek.shortName() = name.take(3).lowercase().replaceFirstChar { it.uppercase() }

    private fun Month.shortName() = name.take(3).lowercase().replaceFirstChar { it.uppercase() }
}
