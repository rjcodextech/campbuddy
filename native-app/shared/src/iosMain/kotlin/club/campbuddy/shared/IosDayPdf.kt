package club.campbuddy.shared

import club.campbuddy.shared.domain.DayPdf
import kotlinx.cinterop.BetaInteropApi
import kotlinx.cinterop.ExperimentalForeignApi
import kotlinx.cinterop.addressOf
import kotlinx.cinterop.useContents
import kotlinx.cinterop.usePinned
import platform.CoreGraphics.CGRectMake
import platform.CoreGraphics.CGSizeMake
import platform.Foundation.NSString
import platform.Foundation.create
import platform.UIKit.NSFontAttributeName
import platform.UIKit.NSForegroundColorAttributeName
import platform.UIKit.NSStringDrawingUsesLineFragmentOrigin
import platform.UIKit.UIBezierPath
import platform.UIKit.UIColor
import platform.UIKit.UIFont
import platform.UIKit.UIGraphicsPDFRenderer
import platform.UIKit.UIGraphicsPDFRendererFormat
import platform.UIKit.UIRectFill
import platform.UIKit.boundingRectWithSize
import platform.UIKit.drawWithRect
import platform.posix.memcpy

/**
 * The "Save my day as PDF" pages, drawn with the phone's own text engine, so Hindi and every other script come out
 * right. Same layout as Android's AndroidDayPdf: A4, a coloured title band, headings and wrapped rows.
 */
@OptIn(ExperimentalForeignApi::class, BetaInteropApi::class)
internal object IosDayPdf {
    private const val W = 595.0
    private const val H = 842.0
    private const val M = 48.0

    private fun rgb(r: Int, g: Int, b: Int) = UIColor(red = r / 255.0, green = g / 255.0, blue = b / 255.0, alpha = 1.0)

    private class Style(val font: UIFont, val color: UIColor) {
        val attributes: Map<Any?, Any?> = mapOf(NSFontAttributeName to font, NSForegroundColorAttributeName to color)
    }

    fun render(pdf: DayPdf): ByteArray? = runCatching {
        val width = W - M * 2
        val body = Style(UIFont.systemFontOfSize(11.0), rgb(35, 31, 32))
        val heading = Style(UIFont.boldSystemFontOfSize(14.0), rgb(195, 58, 25))
        val title = Style(UIFont.boldSystemFontOfSize(20.0), UIColor.whiteColor)
        val small = Style(UIFont.systemFontOfSize(9.0), rgb(107, 98, 94))

        fun height(text: String, style: Style, w: Double): Double =
            NSString.create(string = text).boundingRectWithSize(CGSizeMake(w, 10_000.0), NSStringDrawingUsesLineFragmentOrigin, style.attributes, null)
                .useContents { size.height + 2.0 }

        val renderer = UIGraphicsPDFRenderer(bounds = CGRectMake(0.0, 0.0, W, H), format = UIGraphicsPDFRendererFormat())
        val data = renderer.PDFDataWithActions { context ->
            var y = M

            fun open() {
                context?.beginPage()
                y = M
            }

            fun draw(text: String, style: Style, x: Double, w: Double) {
                val h = height(text, style, w)
                NSString.create(string = text).drawWithRect(CGRectMake(x, y, w, h), NSStringDrawingUsesLineFragmentOrigin, style.attributes, null)
                y += h
            }

            open()
            val titleHeight = height(pdf.title, title, width - 24)
            val bandHeight = titleHeight + 28.0
            rgb(195, 58, 25).setFill()
            UIRectFill(CGRectMake(0.0, 0.0, W, bandHeight + 20.0))
            y = 24.0
            draw(pdf.title, title, M, width - 24)
            y = bandHeight + 40.0

            val dot = rgb(227, 157, 28)
            pdf.sections.forEach { section ->
                if (y + height(section.heading, heading, width) + 30 > H - M) open()
                draw(section.heading, heading, M, width)
                y += 6.0
                section.rows.forEach { row ->
                    if (y + height(row, body, width - 14) + 8 > H - M) open()
                    dot.setFill()
                    UIBezierPath.bezierPathWithOvalInRect(CGRectMake(M + 1.0, y + 5.0, 4.0, 4.0)).fill()
                    draw(row, body, M + 14, width - 14)
                    y += 8.0
                }
                y += 12.0
            }

            val footer = "Made on this phone with CampBuddy. Nothing in it was sent anywhere."
            if (y + height(footer, small, width) > H - M) open()
            y = H - M
            draw(footer, small, M, width)
        }

        val size = data.length.toInt()
        ByteArray(size).also { bytes -> if (size > 0) bytes.usePinned { memcpy(it.addressOf(0), data.bytes, data.length) } }
    }.getOrNull()
}
