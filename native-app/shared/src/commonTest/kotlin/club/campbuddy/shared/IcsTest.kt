package club.campbuddy.shared

import club.campbuddy.shared.domain.Ics
import club.campbuddy.shared.domain.IcsItem
import kotlin.test.Test
import kotlin.test.assertEquals
import kotlin.test.assertNull
import kotlin.test.assertTrue

class IcsTest {
    @Test
    fun aMeetingBecomesAnEventWithAReminder() {
        val file = Ics.build(listOf(IcsItem("meet-1", "Meet Asha, about RAG", "20261003T143000", "20261003T150000", "Ask; bring a laptop", "Hall B")))
        assertTrue(file.startsWith("BEGIN:VCALENDAR\r\n"))
        assertTrue(file.contains("SUMMARY:Meet Asha\\, about RAG"))
        assertTrue(file.contains("DESCRIPTION:Ask\\; bring a laptop"))
        assertTrue(file.contains("TRIGGER:-PT10M"))
        assertTrue(file.trimEnd().endsWith("END:VCALENDAR"))
    }

    @Test
    fun longLinesAreFolded() {
        val file = Ics.build(listOf(IcsItem("x", "t", "20261003T143000", "20261003T150000", "a".repeat(200))))
        assertTrue(file.split("\r\n").all { it.length <= 75 })
    }

    @Test
    fun localTimesComeFromTheSheetsDateAndTime() {
        assertEquals("20261003T143000", Ics.local("2026-10-03", "14:30"))
        assertEquals("20261003T150000", Ics.local("2026-10-03", "14:30", 30))
        assertNull(Ics.local("3 Oct", "14:30"))
    }
}
