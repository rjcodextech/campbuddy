package club.campbuddy.shared

import androidx.compose.ui.graphics.ImageBitmap
import androidx.compose.ui.graphics.asSkiaBitmap
import kotlinx.cinterop.BetaInteropApi
import kotlinx.cinterop.ExperimentalForeignApi
import kotlinx.cinterop.addressOf
import kotlinx.cinterop.usePinned
import org.jetbrains.skia.EncodedImageFormat
import org.jetbrains.skia.Image
import platform.Foundation.NSData
import platform.Foundation.NSTemporaryDirectory
import platform.Foundation.NSURL
import platform.Foundation.create
import platform.Foundation.writeToFile
import platform.UIKit.UIActivityViewController
import platform.UIKit.UIImage
import platform.UIKit.UIImageWriteToSavedPhotosAlbum

/** The Camp Card picture to the iOS share sheet or the photo library. */
@OptIn(ExperimentalForeignApi::class, BetaInteropApi::class)
internal object IosCardExport {
    fun png(image: ImageBitmap): ByteArray? = Image.makeFromBitmap(image.asSkiaBitmap()).encodeToData(EncodedImageFormat.PNG)?.bytes

    private fun data(bytes: ByteArray): NSData =
        if (bytes.isEmpty()) NSData() else bytes.usePinned { NSData.create(bytes = it.addressOf(0), length = bytes.size.toULong()) }

    private fun uiImage(image: ImageBitmap): UIImage? = png(image)?.let { UIImage(data = data(it)) }

    fun share(image: ImageBitmap) {
        val picture = uiImage(image) ?: return
        present(UIActivityViewController(listOf(picture), null))
    }

    /** A calendar file (or the day's PDF) to the share sheet: written to the temporary folder first. */
    fun shareFile(name: String, bytes: ByteArray) {
        val path = NSTemporaryDirectory() + name
        data(bytes).writeToFile(path, true)
        present(UIActivityViewController(listOf(NSURL.fileURLWithPath(path)), null))
    }

    fun save(image: ImageBitmap): Boolean {
        val picture = uiImage(image) ?: return false
        UIImageWriteToSavedPhotosAlbum(picture, null, null, null)

        return true
    }
}
