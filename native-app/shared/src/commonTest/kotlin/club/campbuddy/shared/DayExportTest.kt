package club.campbuddy.shared

import club.campbuddy.shared.data.Bookmark
import club.campbuddy.shared.data.Meeting
import club.campbuddy.shared.domain.DayExport
import kotlinx.datetime.TimeZone
import kotlin.test.Test
import kotlin.test.assertEquals
import kotlin.test.assertTrue

class DayExportTest {
    private val zone = TimeZone.of("Asia/Kolkata")

    @Test
    fun theDayHasTheSameSectionsAsTheWebsite() {
        val pdf = DayExport.build(
            "WordCamp Rajasthan 2026", zone,
            listOf(Bookmark(2, "Second talk", "2026-10-03T06:00:00Z", status = "attended"), Bookmark(1, "First talk", "2026-10-03T04:30:00Z")),
            listOf(Meeting(5, "Asha Rao", sub = "Speaker", note = "Ask about RAG", at = "2026-10-03T14:30", status = "met"), Meeting(6, "Hidden", status = "skipped")),
            mapOf(9L to "Say hi to a speaker"), setOf(9L, 10L),
            mapOf("name" to "Asha", "role" to "Engineer", "layout" to "ticket", "city" to ""),
        )
        assertEquals("My WordCamp Rajasthan 2026", pdf.title)
        assertEquals(listOf("Sessions I saved (2)", "People I planned to meet (1)", "Quests I completed (2)", "My Camp Card"), pdf.sections.map { it.heading })
        assertTrue(pdf.sections[0].rows[0].startsWith("Sat 3 Oct, 10:00 AM  ·  First talk"))
        assertTrue(pdf.sections[0].rows[1].endsWith("Second talk  ·  Attended"))
        assertTrue(pdf.sections[1].rows[0].contains("Note: Ask about RAG"))
        assertEquals(listOf("Say hi to a speaker", "Quest #10"), pdf.sections[2].rows)
        assertEquals(listOf("Name: Asha", "Role: Engineer"), pdf.sections[3].rows)
    }

    @Test
    fun nothingSavedMeansNothingToExport() {
        assertTrue(DayExport.build("X", zone, emptyList(), emptyList(), emptyMap(), emptySet(), emptyMap()).sections.isEmpty())
        assertEquals("wordcamp-rajasthan-2026-my-day.pdf", DayExport.fileName("WordCamp Rajasthan 2026"))
    }
}
