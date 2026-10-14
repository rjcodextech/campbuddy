package club.campbuddy.shared

import club.campbuddy.shared.domain.CampCard
import club.campbuddy.shared.ui.qrModules
import kotlin.test.Test
import kotlin.test.assertEquals
import kotlin.test.assertFalse
import kotlin.test.assertNull
import kotlin.test.assertTrue

class CardLinkTest {
    @Test
    fun theChosenLinkOrTheFirstFilledInOpensFromTheQr() {
        assertEquals("https://linkedin.com/in/asha", CampCard(linkedin = "linkedin.com/in/asha", website = "asha.dev").qrLink)
        assertEquals("https://asha.dev", CampCard(website = "asha.dev").qrLink)
        assertEquals("https://asha.dev", CampCard(linkedin = "linkedin.com/in/asha", website = "asha.dev", qrField = "website").qrLink)
    }

    @Test
    fun handlesAndProfilesBecomeAddresses() {
        assertEquals("https://x.com/asha_r", CampCard(twitter = "@asha_r").qrLink)
        assertEquals("https://x.com/asha_r", CampCard(twitter = "https://twitter.com/asha_r").qrLink)
        assertEquals("https://profiles.wordpress.org/asha/", CampCard(wordpressOrg = "@Asha").qrLink)
    }

    @Test
    fun nothingUsableMeansNoQrAndNoCard() {
        assertNull(CampCard(website = "not a link", twitter = "bad handle!").qrLink)
        assertFalse(CampCard(name = "Asha").complete)
        assertTrue(CampCard(name = "Asha", website = "asha.dev").complete)
    }

    @Test
    fun aSavedCardComesBackAsItWasAndOldInterestsAreSplit() {
        val card = CampCard(name = "Asha", interests = listOf("Gutenberg", "SEO"), website = "https://asha.dev", visible = setOf("role"))
        val back = CampCard.fromMap(card.toMap())
        assertEquals(listOf("Gutenberg", "SEO"), back.interests)
        assertEquals(setOf("role"), back.visible)
        assertEquals(listOf("Block themes", "Plugins"), CampCard.fromMap(mapOf("interests" to "Block themes, Plugins")).interests)
    }

    @Test
    fun aQrGridIsSquareAndHasDarkModules() {
        val grid = qrModules("https://profiles.wordpress.org/asha/")
        assertTrue(grid.size >= 21)
        assertTrue(grid.all { it.size == grid.size })
        assertTrue(grid.any { row -> row.any { it } })
    }
}
