package club.campbuddy.app.update

import android.content.Context
import club.campbuddy.shared.domain.AppUpdater

/** The APK build: CampBuddy updates itself from campbuddy.club (ApkUpdater). src/play has the Play build's no-op. */
object Updates {
    fun updater(context: Context, apiBase: String): AppUpdater = ApkUpdater.get(context).also {
        it.apiBase = apiBase
        UpdateWorker.schedule(context)
    }

    /** The silent "new version" push; true when it was one. */
    fun onPush(context: Context, data: Map<String, String>): Boolean {
        if (data["kind"] != "app-update") return false
        UpdateWorker.now(context)
        return true
    }

    fun onForeground(context: Context, foreground: Boolean) = ApkUpdater.get(context).onForeground(context, foreground)
}
