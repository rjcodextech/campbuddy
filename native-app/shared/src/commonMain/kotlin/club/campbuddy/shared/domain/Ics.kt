package club.campbuddy.shared.domain

/** One calendar entry. [start] and [end] are either an instant ("20261003T093000Z") or a local time with no zone ("20261003T143000"). */
class IcsItem(val uid: String, val title: String, val start: String, val end: String, val description: String = "", val location: String = "", val alarmMinutes: Int? = 10)

/** A calendar file (.ics) for a meeting or the whole plan: any calendar app imports it. */
object Ics {
    private fun escape(text: String) = text.replace("\\", "\\\\").replace(";", "\\;").replace(",", "\\,").replace("\r\n", "\\n").replace("\n", "\\n")

    /** Lines longer than 75 characters continue on the next line, starting with a space (RFC 5545). */
    private fun fold(line: String): String {
        if (line.length <= 75) return line
        val out = StringBuilder(line.take(75))
        var i = 75
        while (i < line.length) { out.append("\r\n ").append(line.substring(i, minOf(i + 74, line.length))); i += 74 }

        return out.toString()
    }

    fun build(items: List<IcsItem>, stamp: String = "20260101T000000Z"): String {
        val lines = mutableListOf("BEGIN:VCALENDAR", "VERSION:2.0", "PRODID:-//CampBuddy//EN", "CALSCALE:GREGORIAN")
        items.forEach { i ->
            lines += "BEGIN:VEVENT"
            lines += "UID:${i.uid}@campbuddy.club"
            lines += "DTSTAMP:$stamp"
            lines += "DTSTART:${i.start}"
            lines += "DTEND:${i.end}"
            lines += "SUMMARY:${escape(i.title)}"
            if (i.description.isNotBlank()) lines += "DESCRIPTION:${escape(i.description)}"
            if (i.location.isNotBlank()) lines += "LOCATION:${escape(i.location)}"
            i.alarmMinutes?.let { lines += listOf("BEGIN:VALARM", "ACTION:DISPLAY", "DESCRIPTION:${escape(i.title)}", "TRIGGER:-PT${it}M", "END:VALARM") }
            lines += "END:VEVENT"
        }
        lines += "END:VCALENDAR"

        return lines.joinToString("\r\n") { fold(it) } + "\r\n"
    }

    /** "2026-10-03" + "14:30" -> "20261003T143000" (30 minutes later for the end). */
    fun local(date: String, time: String, addMinutes: Int = 0): String? {
        val d = Regex("(\\d{4})-(\\d{2})-(\\d{2})").matchEntire(date) ?: return null
        val t = Regex("(\\d{2}):(\\d{2})").matchEntire(time) ?: return null
        val minutes = t.groupValues[1].toInt() * 60 + t.groupValues[2].toInt() + addMinutes
        if (minutes >= 24 * 60) return d.groupValues[1] + d.groupValues[2] + d.groupValues[3] + "T235900"

        return d.groupValues[1] + d.groupValues[2] + d.groupValues[3] + "T" + (minutes / 60).toString().padStart(2, '0') + (minutes % 60).toString().padStart(2, '0') + "00"
    }
}
