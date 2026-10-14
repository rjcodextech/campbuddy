package club.campbuddy.shared

import androidx.compose.runtime.Composable
import androidx.compose.ui.window.ComposeUIViewController
import club.campbuddy.shared.data.KeyValueStore
import platform.Foundation.NSBundle
import platform.Foundation.NSLocale
import platform.Foundation.NSUserDefaults
import platform.Foundation.preferredLanguages
import platform.UIKit.UIApplication
import platform.UIKit.UIDevice
import platform.UIKit.UIViewController
import platform.Foundation.NSURL

@Composable
actual fun PlatformBackHandler(enabled: Boolean, onBack: () -> Unit) {
    // iOS: the swipe-back gesture is the user's; screens still have a visible Back button.
}

private class UserDefaultsStore : KeyValueStore {
    private val defaults = NSUserDefaults.standardUserDefaults
    override fun get(key: String): String? = defaults.stringForKey("campbuddy.$key")
    override fun put(key: String, value: String) = defaults.setObject(value, "campbuddy.$key")
    override fun remove(key: String) = defaults.removeObjectForKey("campbuddy.$key")
}

/** Called from Swift (iosApp): the whole shared app as a UIViewController. */
fun MainViewController(): UIViewController = ComposeUIViewController {
    val language = (NSLocale.preferredLanguages.firstOrNull() as? String)?.take(2)?.takeIf { it == "hi" } ?: "en"
    val version = NSBundle.mainBundle.objectForInfoDictionaryKey("CFBundleShortVersionString") as? String ?: "2.0.0"
    App(
        AppDeps(
            store = UserDefaultsStore(),
            appVersion = version,
            platform = "ios",
            language = language,
            deviceName = UIDevice.currentDevice.name,
            openUrl = { url -> NSURL.URLWithString(url)?.let { UIApplication.sharedApplication.openURL(it) } },
            reminders = IosReminders(),
            shareImage = { image, _ -> IosCardExport.share(image) },
            saveImage = { image, _ -> IosCardExport.save(image) },
            shareFile = { name, _, bytes -> IosCardExport.shareFile(name, bytes) },
        )
    )
}
