package club.campbuddy.app

import android.app.AlarmManager
import android.app.NotificationChannel
import android.app.NotificationManager
import android.app.PendingIntent
import android.content.BroadcastReceiver
import android.content.Context
import android.content.Intent
import android.content.pm.PackageManager
import android.net.Uri
import android.os.Build
import android.provider.Settings
import androidx.core.app.NotificationCompat
import androidx.core.content.ContextCompat
import club.campbuddy.shared.domain.Reminder
import club.campbuddy.shared.domain.ReminderPlatform
import kotlin.coroutines.resume
import kotlinx.coroutines.CancellableContinuation
import kotlinx.coroutines.suspendCancellableCoroutine

private const val CHANNEL = "session-reminders"
private const val PREFS = "campbuddy.reminders"

/**
 * Local reminders with AlarmManager (inexact: a few minutes of slack, which keeps the app free of the
 * exact-alarm permission). Works with no signal. The permission question is asked by MainActivity.
 */
class AndroidReminders(private val context: Context, private val ask: (CancellableContinuation<Boolean>) -> Unit) : ReminderPlatform {

    override suspend fun hasPermission(): Boolean =
        Build.VERSION.SDK_INT < 33 || ContextCompat.checkSelfPermission(context, android.Manifest.permission.POST_NOTIFICATIONS) == PackageManager.PERMISSION_GRANTED

    override suspend fun requestPermission(): Boolean {
        if (hasPermission()) return true

        return suspendCancellableCoroutine { continuation -> ask(continuation) }
    }

    override fun replaceAll(reminders: List<Reminder>) {
        val alarms = context.getSystemService(Context.ALARM_SERVICE) as AlarmManager
        val prefs = context.getSharedPreferences(PREFS, Context.MODE_PRIVATE)

        // Cancel what was planned before; the new list is the whole truth.
        prefs.getStringSet("ids", emptySet()).orEmpty().forEach { id -> alarms.cancel(intent(id.toInt(), null)) }

        reminders.forEach { r ->
            alarms.setAndAllowWhileIdle(AlarmManager.RTC_WAKEUP, r.atEpochMs, intent(r.id, r))
        }
        prefs.edit().putStringSet("ids", reminders.map { it.id.toString() }.toSet()).apply()
    }

    /** CampBuddy's notification settings (Android 8+), else its App info page, where notifications are allowed again. */
    override fun openSettings() {
        val intent = if (Build.VERSION.SDK_INT >= 26) {
            Intent(Settings.ACTION_APP_NOTIFICATION_SETTINGS).putExtra(Settings.EXTRA_APP_PACKAGE, context.packageName)
        } else {
            Intent(Settings.ACTION_APPLICATION_DETAILS_SETTINGS, Uri.fromParts("package", context.packageName, null))
        }
        runCatching { context.startActivity(intent.addFlags(Intent.FLAG_ACTIVITY_NEW_TASK)) }
    }

    private fun intent(id: Int, reminder: Reminder?): PendingIntent {
        val intent = Intent(context, ReminderReceiver::class.java).apply {
            action = "club.campbuddy.app.REMIND.$id"
            reminder?.let {
                putExtra("title", it.title)
                putExtra("body", it.body)
                putExtra("id", it.id)
            }
        }

        return PendingIntent.getBroadcast(context, id, intent, PendingIntent.FLAG_UPDATE_CURRENT or PendingIntent.FLAG_IMMUTABLE)
    }
}

/** Shows the notification when its time comes. */
class ReminderReceiver : BroadcastReceiver() {
    override fun onReceive(context: Context, intent: Intent) {
        val manager = context.getSystemService(Context.NOTIFICATION_SERVICE) as NotificationManager
        if (Build.VERSION.SDK_INT >= 26) {
            manager.createNotificationChannel(NotificationChannel(CHANNEL, "Session reminders", NotificationManager.IMPORTANCE_HIGH).apply { description = "A few minutes before a session you saved" })
        }

        val open = PendingIntent.getActivity(context, 0, Intent(context, MainActivity::class.java).addFlags(Intent.FLAG_ACTIVITY_SINGLE_TOP), PendingIntent.FLAG_IMMUTABLE)
        val notification = NotificationCompat.Builder(context, CHANNEL)
            .setSmallIcon(R.drawable.ic_stat_campbuddy)
            .setColor(0xFF60121C.toInt())
            .setContentTitle(intent.getStringExtra("title") ?: "A session starts soon")
            .setContentText(intent.getStringExtra("body") ?: "")
            .setContentIntent(open)
            .setAutoCancel(true)
            .setPriority(NotificationCompat.PRIORITY_HIGH)
            .build()

        if (Build.VERSION.SDK_INT < 33 || ContextCompat.checkSelfPermission(context, android.Manifest.permission.POST_NOTIFICATIONS) == PackageManager.PERMISSION_GRANTED) {
            manager.notify(intent.getIntExtra("id", 1), notification)
        }
    }
}
