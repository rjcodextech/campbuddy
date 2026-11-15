package club.campbuddy.shared

import club.campbuddy.shared.ui.absoluteUrl
import kotlin.test.Test
import kotlin.test.assertEquals

class RemoteImageTest {

    @Test
    fun resolvesRelativePathWithLeadingSlash() {
        val result = absoluteUrl("https://campbuddy.club/api/v1", "/storage/branding/27/logo.png")
        assertEquals("https://campbuddy.club/storage/branding/27/logo.png", result)
    }

    @Test
    fun resolvesRelativePathWithoutLeadingSlash() {
        val result = absoluteUrl("https://campbuddy.club/api/v1", "storage/branding/27/logo.png")
        assertEquals("https://campbuddy.club/storage/branding/27/logo.png", result)
    }

    @Test
    fun resolvesSchemeRelativeUrlToHttps() {
        val result = absoluteUrl("https://campbuddy.club/api/v1", "//secure.gravatar.com/avatar/abc")
        assertEquals("https://secure.gravatar.com/avatar/abc", result)
    }

    @Test
    fun upgradesHttpRemoteUrlToHttpsForCleartextPolicy() {
        val result = absoluteUrl("https://campbuddy.club/api/v1", "http://2024.bhopal.wordcamp.org/files/2024/02/logo.png")
        assertEquals("https://2024.bhopal.wordcamp.org/files/2024/02/logo.png", result)
    }

    @Test
    fun preservesLocalhostHttpUrls() {
        val result = absoluteUrl("http://localhost:8000/api/v1", "http://localhost:8000/storage/pic.jpg")
        assertEquals("http://localhost:8000/storage/pic.jpg", result)
    }

    @Test
    fun preservesHttpsUrl() {
        val result = absoluteUrl("https://campbuddy.club/api/v1", "https://wordpress.org/logo.png")
        assertEquals("https://wordpress.org/logo.png", result)
    }

    @Test
    fun preservesDataUrl() {
        val dataUri = "data:image/png;base64,iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mNk+A8AAQUBAScY42YAAAAASUVORK5CYII="
        val result = absoluteUrl("https://campbuddy.club/api/v1", dataUri)
        assertEquals(dataUri, result)
    }

    @Test
    fun trimsWhitespaceAroundUrls() {
        val result = absoluteUrl("https://campbuddy.club/api/v1", "   https://example.com/photo.png   \n")
        assertEquals("https://example.com/photo.png", result)
    }
}
