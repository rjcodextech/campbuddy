package club.campbuddy.shared.domain

import kotlinx.coroutines.flow.StateFlow

/**
 * The Android APK build's self-updater (Admin → Mobile app → App updates on the website). The app shell
 * passes one in AppDeps; it is null on iPhone and in the Play Store build, which are updated by their stores.
 */
interface AppUpdater {
    val state: StateFlow<UpdateState>

    /** Asks the server now, even when it was asked a moment ago. */
    fun check()

    /** Installs the downloaded, checked update (Android may ask the person to confirm). */
    fun install()

    /** Opens the system setting that lets CampBuddy install its own updates. */
    fun allowInstalls()

    /** "Later": the update installs itself the next time the app is closed instead. */
    fun later()
}

sealed interface UpdateState {
    /** True when this build is older than the newest required one: the app cannot be used until it updates. */
    val required: Boolean

    data object Idle : UpdateState {
        override val required = false
    }

    data class Downloading(val version: String, val percent: Int, override val required: Boolean) : UpdateState

    /** Downloaded and checked; `notes` is "what's new" by language ("en", "hi"). */
    data class Ready(val version: String, val notes: Map<String, String>, override val required: Boolean, val postponed: Boolean = false) : UpdateState

    /** Android needs the person's one-time OK ("Install unknown apps") before CampBuddy can install updates. */
    data class NeedsPermission(val version: String, override val required: Boolean) : UpdateState

    data class Installing(val version: String, override val required: Boolean) : UpdateState

    data class Failed(override val required: Boolean) : UpdateState
}
