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
