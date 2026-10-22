package club.campbuddy.shared

import club.campbuddy.shared.ui.fitShares
import club.campbuddy.shared.ui.heroDates
import kotlin.test.Test
import kotlin.test.assertEquals
import kotlin.test.assertNull
import kotlin.test.assertTrue

class TabBarAndDatesTest {
    @Test
    fun equalSharesWhenEveryLabelFits() {
        assertEquals(listOf(60, 60, 60, 60, 60, 60), fitShares(listOf(40, 45, 40, 50, 44, 55), 360))
    }

    @Test
    fun aLongLabelKeepsItsWidthAndTheOthersShareTheRest() {
        val widths = fitShares(listOf(52, 52, 52, 63, 52, 69), 360)
        assertEquals(360, widths.sum())
        assertEquals(69, widths[5])
        assertTrue(widths[3] >= 63)
        widths.forEachIndexed { i, w -> assertTrue(w >= listOf(52, 52, 52, 63, 52, 69)[i]) }
    }

    @Test
    fun tooLittleRoomSharesInProportionAndStillFillsTheRow() {
        val widths = fitShares(listOf(80, 80, 80, 120, 80, 130), 360)
        assertEquals(360, widths.sum())
        assertTrue(widths[5] > widths[0])
    }

    @Test
    fun shortDateRanges() {
        assertEquals("21–22 Nov 2026", heroDates("2026-11-21", "2026-11-22"))
        assertEquals("30 Nov – 2 Dec 2026", heroDates("2026-11-30", "2026-12-02"))
        assertEquals("30 Dec 2026 – 1 Jan 2027", heroDates("2026-12-30", "2027-01-01"))
        assertEquals("3 Oct 2026", heroDates("2026-10-03", "2026-10-03"))
        assertEquals("3 Oct 2026", heroDates("2026-10-03", null))
        assertNull(heroDates(null, null))
    }
}
