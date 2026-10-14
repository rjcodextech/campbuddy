package club.campbuddy.app

import android.content.ContentValues
import android.content.Context
import android.content.Intent
import android.graphics.Bitmap
import android.os.Build
import android.provider.MediaStore
import androidx.compose.ui.graphics.ImageBitmap
import androidx.compose.ui.graphics.asAndroidBitmap
import androidx.core.content.FileProvider
import java.io.File

/** Hands the Camp Card picture to the system: the share sheet, or the Pictures folder. */
class AndroidCardExport(private val context: Context) {
    fun share(image: ImageBitmap, name: String) {
        val file = File(File(context.cacheDir, "shared").apply { mkdirs() }, name)
        file.outputStream().use { image.asAndroidBitmap().compress(Bitmap.CompressFormat.PNG, 100, it) }
        val uri = FileProvider.getUriForFile(context, "${context.packageName}.files", file)
        val send = Intent(Intent.ACTION_SEND).apply {
            type = "image/png"
            putExtra(Intent.EXTRA_STREAM, uri)
            addFlags(Intent.FLAG_GRANT_READ_URI_PERMISSION)
        }
        context.startActivity(Intent.createChooser(send, "Share your Camp Card").addFlags(Intent.FLAG_ACTIVITY_NEW_TASK))
    }

    /** Pictures/CampBuddy on Android 10+; older phones would need a storage permission, so they use Share instead. */
    fun save(image: ImageBitmap, name: String): Boolean {
        if (Build.VERSION.SDK_INT < 29) return false
        val values = ContentValues().apply {
            put(MediaStore.Images.Media.DISPLAY_NAME, name)
            put(MediaStore.Images.Media.MIME_TYPE, "image/png")
            put(MediaStore.Images.Media.RELATIVE_PATH, "Pictures/CampBuddy")
        }
        val uri = context.contentResolver.insert(MediaStore.Images.Media.EXTERNAL_CONTENT_URI, values) ?: return false

        return runCatching {
            context.contentResolver.openOutputStream(uri)?.use { image.asAndroidBitmap().compress(Bitmap.CompressFormat.PNG, 100, it) }
            true
        }.getOrDefault(false)
    }
}
