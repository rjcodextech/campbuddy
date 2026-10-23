package club.campbuddy.app.update

import android.content.Context
import club.campbuddy.shared.domain.AppUpdater

/** The Play Store build: Play updates the app, so there is no updater here (src/direct has the APK's). */
object Updates {
    fun updater(context: Context, apiBase: String): AppUpdater? = null

    fun onPush(context: Context, data: Map<String, String>): Boolean = false

    fun onForeground(context: Context, foreground: Boolean) {}
}
