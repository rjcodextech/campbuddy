package club.campbuddy.app

import android.graphics.Color
import android.graphics.Paint
import android.graphics.Typeface
import android.graphics.pdf.PdfDocument
import android.text.Layout
import android.text.StaticLayout
import android.text.TextPaint
import club.campbuddy.shared.domain.DayPdf
import java.io.ByteArrayOutputStream

/**
 * The "Save my day as PDF" pages, drawn with the phone's own text engine, so Hindi and every other script come out
 * right (the website has to draw those as pictures). A4, a coloured title band, headings and wrapped rows.
 */
object AndroidDayPdf {
    private const val W = 595
    private const val H = 842
    private const val M = 48

    fun render(pdf: DayPdf): ByteArray? = runCatching {
        val doc = PdfDocument()
        val width = W - M * 2
        val body = TextPaint(Paint.ANTI_ALIAS_FLAG).apply { color = Color.rgb(35, 31, 32); textSize = 11f }
        val heading = TextPaint(Paint.ANTI_ALIAS_FLAG).apply { color = Color.rgb(195, 58, 25); textSize = 14f; typeface = Typeface.create(Typeface.DEFAULT, Typeface.BOLD) }
        val title = TextPaint(Paint.ANTI_ALIAS_FLAG).apply { color = Color.WHITE; textSize = 20f; typeface = Typeface.create(Typeface.DEFAULT, Typeface.BOLD) }
        val small = TextPaint(Paint.ANTI_ALIAS_FLAG).apply { color = Color.rgb(107, 98, 94); textSize = 9f }

        var number = 0
        var page: PdfDocument.Page? = null
        var y = 0f

        fun open() {
            page?.let { doc.finishPage(it) }
            number += 1
            page = doc.startPage(PdfDocument.PageInfo.Builder(W, H, number).create())
            y = M.toFloat()
        }

        fun layout(text: String, paint: TextPaint, w: Int) = StaticLayout.Builder.obtain(text, 0, text.length, paint, w).setLineSpacing(2f, 1f).setAlignment(Layout.Alignment.ALIGN_NORMAL).build()

        fun draw(l: StaticLayout, x: Float) {
            val canvas = page!!.canvas
            canvas.save(); canvas.translate(x, y); l.draw(canvas); canvas.restore()
            y += l.height
        }

        open()
        val band = Paint().apply { color = Color.rgb(195, 58, 25) }
        val titleLayout = layout(pdf.title, title, width - 24)
        val bandHeight = titleLayout.height + 28f
        page!!.canvas.drawRect(0f, 0f, W.toFloat(), bandHeight + 20f, band)
        y = 24f
        draw(titleLayout, M.toFloat() + 0f)
        y = bandHeight + 40f

        pdf.sections.forEach { section ->
            val h = layout(section.heading, heading, width)
            if (y + h.height + 30 > H - M) open()
            draw(h, M.toFloat())
            y += 6f
            section.rows.forEach { row ->
                val l = layout(row, body, width - 14)
                if (y + l.height + 8 > H - M) open()
                page!!.canvas.drawCircle(M + 3f, y + 7f, 2f, Paint().apply { color = Color.rgb(227, 157, 28) })
                draw(l, M + 14f)
                y += 8f
            }
            y += 12f
        }

        val footer = layout("Made on this phone with CampBuddy. Nothing in it was sent anywhere.", small, width)
        if (y + footer.height > H - M) open()
        y = H - M.toFloat()
        draw(footer, M.toFloat())
        doc.finishPage(page!!)

        ByteArrayOutputStream().also { doc.writeTo(it); doc.close() }.toByteArray()
    }.getOrNull()
}
