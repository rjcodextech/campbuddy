package club.campbuddy.shared.domain

/**
 * What the phone does for reminders: ask for the notification permission, and show the planned
 * reminders at their time (AlarmManager on Android, UNUserNotificationCenter on iOS). The shared
 * app decides WHAT to remind about (Reminders); the platform only delivers it.
 */
interface ReminderPlatform {
    /** True when the person has already allowed notifications. */
    suspend fun hasPermission(): Boolean

    /** Shows the system question once; true when allowed. A "no" is final for the app (N4). */
    suspend fun requestPermission(): Boolean

    /** Replaces every pending reminder with [reminders] (cancel what is no longer wanted). */
    fun replaceAll(reminders: List<Reminder>)
}

/** For previews and tests. */
object NoReminders : ReminderPlatform {
    override suspend fun hasPermission() = false
    override suspend fun requestPermission() = false
    override fun replaceAll(reminders: List<Reminder>) = Unit
}
