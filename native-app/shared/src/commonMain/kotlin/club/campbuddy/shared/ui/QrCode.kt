package club.campbuddy.shared.ui

import androidx.compose.foundation.Canvas
import androidx.compose.foundation.layout.size
import androidx.compose.runtime.Composable
import androidx.compose.runtime.remember
import androidx.compose.ui.Modifier
import androidx.compose.ui.geometry.Offset
import androidx.compose.ui.geometry.Size
import androidx.compose.ui.graphics.Color
import androidx.compose.ui.unit.Dp
import androidx.compose.ui.unit.dp
import qrcode.QRCode

/** A QR code for [data], drawn as plain squares (dark on white, with the quiet border scanners need). */
@Composable
fun QrCodeView(data: String, size: Dp = 120.dp, dark: Color = Color(0xFF1F1B1C)) {
    val modules = remember(data) { qrModules(data) }
    Canvas(Modifier.size(size)) {
        drawRect(Color.White)
        val count = modules.size
        if (count == 0) return@Canvas
        val quiet = 2
        val cell = this.size.width / (count + 2 * quiet)
        for (row in 0 until count) {
            for (col in 0 until count) {
                if (modules[row][col]) {
                    drawRect(dark, Offset((col + quiet) * cell, (row + quiet) * cell), Size(cell + 0.5f, cell + 0.5f))
                }
            }
        }
    }
}

/** The dark/light grid of a QR code; empty when the text cannot be encoded. */
internal fun qrModules(data: String): List<List<Boolean>> = runCatching {
    QRCode(data).rawData.map { row -> row.map { it.dark } }
}.getOrDefault(emptyList())

/** The link a Camp Card QR opens: the WordPress.org profile, else LinkedIn, else the website (web addresses only). */
fun cardLink(fields: Map<String, String>): String? {
    fun web(text: String?): String? {
        val t = text?.trim().orEmpty()
        if (t.isEmpty()) return null
        val withScheme = if (t.startsWith("http://") || t.startsWith("https://")) t else "https://$t"

        return withScheme.takeIf { Regex("^https?://[^\\s/]+\\.[^\\s/]+.*$").matches(it) }
    }
    val username = fields["wordpressOrg"]?.trim()?.removePrefix("@").orEmpty()
    val profile = when {
        username.isEmpty() -> null
        username.contains("profiles.wordpress.org") -> web(username)
        Regex("[A-Za-z0-9][A-Za-z0-9._@-]*").matches(username) -> "https://profiles.wordpress.org/${username.lowercase()}/"
        else -> null
    }

    return profile ?: web(fields["linkedin"]) ?: web(fields["website"])
}
