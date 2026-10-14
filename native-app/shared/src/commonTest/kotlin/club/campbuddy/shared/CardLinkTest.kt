package club.campbuddy.shared

import club.campbuddy.shared.ui.cardLink
import club.campbuddy.shared.ui.qrModules
import kotlin.test.Test
import kotlin.test.assertEquals
import kotlin.test.assertNull
import kotlin.test.assertTrue

class CardLinkTest {
    @Test
    fun theWordPressProfileComesFirstThenLinkedInThenTheWebsite() {
        assertEquals("https://profiles.wordpress.org/asha/", cardLink(mapOf("wordpressOrg" to "@Asha", "linkedin" to "linkedin.com/in/asha")))
        assertEquals("https://linkedin.com/in/asha", cardLink(mapOf("linkedin" to "linkedin.com/in/asha", "website" to "asha.dev")))
        assertEquals("https://asha.dev", cardLink(mapOf("website" to "asha.dev")))
    }

    @Test
    fun nothingUsableMeansNoQr() {
        assertNull(cardLink(emptyMap()))
        assertNull(cardLink(mapOf("website" to "not a link", "wordpressOrg" to "bad name!")))
    }

    @Test
    fun aQrGridIsSquareAndHasDarkModules() {
        val grid = qrModules("https://profiles.wordpress.org/asha/")
        assertTrue(grid.size >= 21)
        assertTrue(grid.all { it.size == grid.size })
        assertTrue(grid.any { row -> row.any { it } })
    }
}
