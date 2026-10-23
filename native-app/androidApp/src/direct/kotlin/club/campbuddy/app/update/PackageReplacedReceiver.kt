package club.campbuddy.app.update

import android.content.BroadcastReceiver
import android.content.Context
import android.content.Intent

/** Runs in the new version right after an update: tidies the download and keeps the background checks going. */
class PackageReplacedReceiver : BroadcastReceiver() {
    override fun onReceive(context: Context, intent: Intent) {
        if (intent.action != Intent.ACTION_MY_PACKAGE_REPLACED) return
        ApkUpdater.get(context).replaced()
        UpdateWorker.schedule(context)
    }
}
