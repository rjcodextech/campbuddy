package club.campbuddy.shared

import androidx.compose.ui.graphics.ImageBitmap
import androidx.compose.ui.graphics.asSkiaBitmap
import kotlinx.cinterop.ExperimentalForeignApi
import kotlinx.cinterop.addressOf
import kotlinx.cinterop.usePinned
import org.jetbrains.skia.EncodedImageFormat
import org.jetbrains.skia.Image
import platform.Foundation.NSData
import platform.Foundation.create
import platform.UIKit.UIActivityViewController
import platform.UIKit.UIApplication
import platform.UIKit.UIImage
import platform.UIKit.UIImageWriteToSavedPhotosAlbum

/** The Camp Card picture to the iOS share sheet or the photo library. */
@OptIn(ExperimentalForeignApi::class)
internal object IosCardExport {
    private fun uiImage(image: ImageBitmap): UIImage? {
        val bytes = Image.makeFromBitmap(image.asSkiaBitmap()).encodeToData(EncodedImageFormat.PNG)?.bytes ?: return null
        val data = bytes.usePinned { NSData.create(bytes = it.addressOf(0), length = bytes.size.toULong()) }

        return UIImage(data = data)
    }

    fun share(image: ImageBitmap) {
        val picture = uiImage(image) ?: return
        val controller = UIApplication.sharedApplication.keyWindow?.rootViewController ?: return
        controller.presentViewController(UIActivityViewController(listOf(picture), null), true, null)
    }

    /** A calendar file to the share sheet: written to the temporary folder first. */
    fun shareFile(name: String, bytes: ByteArray) {
        val path = platform.Foundation.NSTemporaryDirectory() + name
        val data = bytes.usePinned { NSData.create(bytes = it.addressOf(0), length = bytes.size.toULong()) }
        data.writeToFile(path, true)
        val controller = UIApplication.sharedApplication.keyWindow?.rootViewController ?: return
        controller.presentViewController(UIActivityViewController(listOf(platform.Foundation.NSURL.fileURLWithPath(path)), null), true, null)
    }

    fun save(image: ImageBitmap): Boolean {
        val picture = uiImage(image) ?: return false
        UIImageWriteToSavedPhotosAlbum(picture, null, null, null)

        return true
    }
}
