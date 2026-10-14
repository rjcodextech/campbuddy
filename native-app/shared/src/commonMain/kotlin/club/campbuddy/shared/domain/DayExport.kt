package club.campbuddy.shared.domain

import club.campbuddy.shared.data.Bookmark
import club.campbuddy.shared.data.Meeting
import kotlinx.datetime.Instant
import kotlinx.datetime.TimeZone

class PdfSection(val heading: String, val rows: List<String>)

class DayPdf(val title: String, val sections: List<PdfSection>)

/**
 * "Save my day as PDF": everything this phone holds for one WordCamp (saved sessions, people to meet, quests, the Camp
 * Card) as pages the attendee keeps. Same sections and wording as the website's export. Nothing leaves the phone.
 */
object DayExport {
    private val STATUS = mapOf("attended" to "Attended", "missed" to "Couldn't make it", "met" to "Met")

    // Camp Card fields that are choices or pictures, not for reading.
    private val CARD_SKIP = setOf("visibleFields", "primaryLink", "interests", "layout", "photo", "avatar", "avatarUrl", "qrField", "visible", "askMeAbout")

    private val CARD_LABELS = mapOf(
        "name" to "Name", "role" to "Role", "company" to "Company", "city" to "City", "linkedin" to "Linkedin",
        "website" to "Website", "wordpressOrg" to "WordPress.org", "twitter" to "X / Twitter",
    )

    fun build(
        eventName: String,
        zone: TimeZone,
        bookmarks: List<Bookmark>,
        meetings: List<Meeting>,
        questTitles: Map<Long, String>,
        completedQuests: Set<Long>,
        card: Map<String, String>,
    ): DayPdf {
        fun whenText(iso: String): String? = runCatching { Instant.parse(iso) }.getOrNull()?.let {
            EventClock.dayLabel(EventClock.day(it, zone)) + ", " + EventClock.time(it, zone)
        }

        val sections = mutableListOf<PdfSection>()

        val sessions = bookmarks.filter { it.title.isNotBlank() }
            .sortedBy { runCatching { Instant.parse(it.startsAt ?: "") }.getOrNull()?.epochSeconds ?: Long.MAX_VALUE }
            .map { b -> listOfNotNull(b.startsAt?.let { whenText(it) } ?: "Time TBA", b.title, STATUS[b.status]).joinToString("  ·  ") }
        if (sessions.isNotEmpty()) sections += PdfSection("Sessions I saved (${sessions.size})", sessions)

        val people = meetings.filter { it.name.isNotBlank() && it.status != "skipped" }.map { m ->
            listOfNotNull(
                listOfNotNull(m.name, m.sub.takeIf { it.isNotBlank() }, STATUS[m.status]).joinToString("  ·  "),
                m.note.takeIf { it.isNotBlank() }?.let { "Note: $it" },
                m.at?.let { at -> "Planned: ${at.substringBefore('T')} ${at.substringAfter('T', "").take(5)}".trim() },
            ).joinToString("\n")
        }
        if (people.isNotEmpty()) sections += PdfSection("People I planned to meet (${people.size})", people)

        val quests = completedQuests.map { questTitles[it] ?: "Quest #$it" }
        if (quests.isNotEmpty()) sections += PdfSection("Quests I completed (${quests.size})", quests)

        val rows = card.filter { (k, v) -> k !in CARD_SKIP && v.isNotBlank() && !v.startsWith("data:") && v.length < 300 }
            .map { (k, v) -> (CARD_LABELS[k] ?: k.replaceFirstChar { it.uppercase() }) + ": " + v.trim() }
        if (rows.isNotEmpty()) sections += PdfSection("My Camp Card", rows)

        return DayPdf("My $eventName", sections)
    }

    fun fileName(eventName: String): String =
        eventName.lowercase().map { if (it.isLetterOrDigit()) it else '-' }.joinToString("").replace(Regex("-+"), "-").trim('-') + "-my-day.pdf"

    /** Plain text, for a phone that cannot draw a PDF. */
    fun asText(pdf: DayPdf): String = buildString {
        appendLine(pdf.title)
        pdf.sections.forEach { s ->
            appendLine()
            appendLine(s.heading)
            s.rows.forEach { appendLine("- " + it.replace("\n", "\n  ")) }
        }
    }
}
