package club.campbuddy.app.update

import android.content.BroadcastReceiver
import android.content.Context
import android.content.Intent
import android.content.pm.PackageInstaller
import androidx.core.content.IntentCompat

/** What PackageInstaller says about an update CampBuddy handed it (ApkUpdater). */
class InstallResultReceiver : BroadcastReceiver() {
    override fun onReceive(context: Context, intent: Intent) {
        val updater = ApkUpdater.get(context)
        when (val status = intent.getIntExtra(PackageInstaller.EXTRA_STATUS, PackageInstaller.STATUS_FAILURE)) {
            PackageInstaller.STATUS_PENDING_USER_ACTION ->
                IntentCompat.getParcelableExtra(intent, Intent.EXTRA_INTENT, Intent::class.java)?.let(updater::confirm)
            PackageInstaller.STATUS_SUCCESS -> Unit // the new version starts fresh (PackageReplacedReceiver)
            else -> updater.failed(status)
        }
    }
}
