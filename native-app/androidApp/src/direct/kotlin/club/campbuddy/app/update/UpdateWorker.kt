package club.campbuddy.app.update

import android.content.Context
import androidx.work.BackoffPolicy
import androidx.work.Constraints
import androidx.work.CoroutineWorker
import androidx.work.ExistingPeriodicWorkPolicy
import androidx.work.ExistingWorkPolicy
import androidx.work.NetworkType
import androidx.work.OneTimeWorkRequestBuilder
import androidx.work.PeriodicWorkRequestBuilder
import androidx.work.WorkManager
import androidx.work.WorkerParameters
import java.util.concurrent.TimeUnit

/** The APK's update check in the background: every 6 hours, on the "new version" push, and soon after the app is closed. */
class UpdateWorker(context: Context, params: WorkerParameters) : CoroutineWorker(context, params) {
    override suspend fun doWork(): Result {
        val force = inputData.getBoolean(FORCE, true)

        return if (ApkUpdater.get(applicationContext).run(force)) Result.success() else Result.retry()
    }

    companion object {
        private const val FORCE = "force"
        private val online = Constraints.Builder().setRequiredNetworkType(NetworkType.CONNECTED).build()

        fun schedule(context: Context) {
            val request = PeriodicWorkRequestBuilder<UpdateWorker>(6, TimeUnit.HOURS)
                .setConstraints(online).setBackoffCriteria(BackoffPolicy.EXPONENTIAL, 15, TimeUnit.MINUTES).build()
            WorkManager.getInstance(context).enqueueUniquePeriodicWork("app-update", ExistingPeriodicWorkPolicy.KEEP, request)
        }

        /** A version was just published (silent push): check now. */
        fun now(context: Context) {
            val request = OneTimeWorkRequestBuilder<UpdateWorker>()
                .setConstraints(online).setBackoffCriteria(BackoffPolicy.EXPONENTIAL, 1, TimeUnit.MINUTES).build()
            WorkManager.getInstance(context).enqueueUniqueWork("app-update-now", ExistingWorkPolicy.REPLACE, request)
        }

        /** The app went to the background with an update ready: install it in a few minutes unless the person is back. */
        fun installSoon(context: Context) {
            val request = OneTimeWorkRequestBuilder<UpdateWorker>()
                .setInitialDelay(3, TimeUnit.MINUTES)
                .setInputData(androidx.work.workDataOf(FORCE to false)).build()
            WorkManager.getInstance(context).enqueueUniqueWork("app-update-install", ExistingWorkPolicy.REPLACE, request)
        }
    }
}
