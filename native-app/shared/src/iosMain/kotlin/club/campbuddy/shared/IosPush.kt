package club.campbuddy.shared

import club.campbuddy.shared.domain.PushPlatform
import kotlinx.coroutines.delay
import platform.Foundation.NSBundle
import platform.Foundation.NSUserDefaults

private const val TOKEN_KEY = "campbuddy.push.fcm"

/** Called from Swift (AppDelegate) whenever Firebase hands over a new FCM token. */
fun savePushToken(token: String?) {
    val defaults = NSUserDefaults.standardUserDefaults
    if (token.isNullOrEmpty()) defaults.removeObjectForKey(TOKEN_KEY) else defaults.setObject(token, TOKEN_KEY)
}

/**
 * Push through Firebase Cloud Messaging (which delivers through APNs). Off (no token) when the build has no
 * GoogleService-Info.plist. The token arrives a moment after launch, so the first ask waits a few seconds for it.
 */
class IosPush : PushPlatform {
    private val configured = NSBundle.mainBundle.pathForResource("GoogleService-Info", "plist") != null

    override suspend fun token(): String? {
        if (!configured) return null

        repeat(20) {
            NSUserDefaults.standardUserDefaults.stringForKey(TOKEN_KEY)?.let { return it }
            delay(500)
        }

        return null
    }
}
