package club.campbuddy.shared

import club.campbuddy.shared.domain.DeepLink
import kotlin.test.Test
import kotlin.test.assertEquals
import kotlin.test.assertNotNull
import kotlin.test.assertNull

class DeepLinkTest {
    @Test
    fun websiteLinksOpenTheEventAndItsTab() {
        val link = assertNotNull(DeepLink.parse("https", "campbuddy.club", "/event/wordcamp-demo-2026/my-day"))
        assertEquals("wordcamp-demo-2026", link.slug)
        assertEquals("my-day", link.tab)
        assertNull(DeepLink.parse("https", "campbuddy.club", "/event/wordcamp-demo-2026")?.tab)
    }

    @Test
    fun theAppSchemeWorksToo() {
        val link = assertNotNull(DeepLink.parse("campbuddy", "event", "/wordcamp-demo-2026/quest"))
        assertEquals("quest", link.tab)
    }

    @Test
    fun otherHostsPathsAndOddSlugsAreIgnored() {
        assertNull(DeepLink.parse("https", "example.com", "/event/wordcamp-demo-2026"))
        assertNull(DeepLink.parse("https", "campbuddy.club", "/admin/events"))
        assertNull(DeepLink.parse("https", "campbuddy.club", "/event/../../x"))
        assertNull(DeepLink.parse("https", "campbuddy.club", "/event/"))
        assertNull(DeepLink.parse("http", "campbuddy.club", "/event/a"))
        assertEquals(null, DeepLink.parse("https", "campbuddy.club", "/event/a/unknown")?.tab)
    }
}
