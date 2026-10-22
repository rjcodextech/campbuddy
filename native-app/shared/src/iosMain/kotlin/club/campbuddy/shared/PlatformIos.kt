package club.campbuddy.shared

import androidx.compose.runtime.Composable
import androidx.compose.runtime.mutableStateOf
import androidx.compose.ui.window.ComposeUIViewController
import club.campbuddy.shared.data.KeyValueStore
import club.campbuddy.shared.domain.DeepLink
import kotlin.experimental.ExperimentalNativeApi
import kotlinx.cinterop.ExperimentalForeignApi
import kotlinx.cinterop.useContents
import platform.CoreGraphics.CGRectMake
import platform.Foundation.NSBundle
import platform.Foundation.NSLocale
import platform.Foundation.NSProcessInfo
import platform.Foundation.NSURL
import platform.Foundation.NSUserDefaults
import platform.Foundation.preferredLanguages
import platform.SafariServices.SFSafariViewController
import platform.UIKit.UIApplication
import platform.UIKit.UIDevice
import platform.UIKit.UIViewController
import platform.UIKit.popoverPresentationController

@Composable
actual fun PlatformBackHandler(enabled: Boolean, onBack: () -> Unit) {
    // iOS: the swipe-back gesture is the user's; screens still have a visible Back button.
}

/**
 * Plain settings in UserDefaults; the organizer sign-in (token, role, name) in the Keychain, as Android keeps it
 * encrypted. The Keychain outlives a reinstall, so a fresh install clears what an old one left there.
 */
private class IosStore : KeyValueStore {
    private val defaults = NSUserDefaults.standardUserDefaults

    init {
        if (!defaults.boolForKey("campbuddy.installed")) {
            IosKeychain.clear()
            defaults.setBool(true, "campbuddy.installed")
        }
    }

    private fun secret(key: String) = key.startsWith("organizer.")

    override fun get(key: String): String? =
        (if (secret(key)) IosKeychain.get(key) else null) ?: defaults.stringForKey("campbuddy.$key")

    override fun put(key: String, value: String) {
        if (secret(key) && IosKeychain.put(key, value)) {
            defaults.removeObjectForKey("campbuddy.$key")
            return
        }
        defaults.setObject(value, "campbuddy.$key")
    }

    override fun remove(key: String) {
        if (secret(key)) IosKeychain.remove(key)
        defaults.removeObjectForKey("campbuddy.$key")
    }
}

/** The topmost screen, to show the share sheet or a web page over whatever is already open. */
internal fun topController(): UIViewController? {
    var top = UIApplication.sharedApplication.keyWindow?.rootViewController
    while (top?.presentedViewController != null) top = top.presentedViewController

    return top
}

/** Shows [controller] over the app; on an iPad a share sheet needs a place to point at, so it points at the middle. */
@OptIn(ExperimentalForeignApi::class)
internal fun present(controller: UIViewController) {
    val host = topController() ?: return
    controller.popoverPresentationController?.let { popover ->
        popover.sourceView = host.view
        host.view.bounds.useContents { popover.sourceRect = CGRectMake(size.width / 2, size.height / 2, 1.0, 1.0) }
    }
    host.presentViewController(controller, true, null)
}

private fun openOutside(url: String) {
    NSURL.URLWithString(url)?.let { UIApplication.sharedApplication.openURL(it, options = emptyMap<Any?, Any?>(), completionHandler = null) }
}

private val incomingLink = mutableStateOf<DeepLink?>(null)
private var linkCount = 0

/**
 * Called from Swift for a universal link (https://campbuddy.club/event/…), a campbuddy:// link or a tapped push
 * (whose url is a path on the site). Anything that is not one of ours is ignored.
 */
fun openLink(url: String) {
    val full = if (url.startsWith("/")) "https://campbuddy.club$url" else url
    val parsed = NSURL.URLWithString(full) ?: return
    DeepLink.parse(parsed.scheme, parsed.host, parsed.path, ++linkCount, parsed.query)?.let { incomingLink.value = it }
}

/**
 * The server to talk to. Release builds always use campbuddy.club. A debug build started from Xcode can be pointed at
 * a development server with the scheme's environment variable CAMPBUDDY_API (e.g. http://127.0.0.1:8099/api/v1).
 */
@OptIn(ExperimentalNativeApi::class)
private fun apiBase(): String {
    val given = if (Platform.isDebugBinary) NSProcessInfo.processInfo.environment["CAMPBUDDY_API"] as? String else null

    return given?.takeIf { it.startsWith("http") } ?: "https://campbuddy.club/api/v1"
}

/** Called from Swift (iosApp): the whole shared app as a UIViewController. */
fun MainViewController(): UIViewController {
    val store = IosStore()
    val reminders = IosReminders()
    val push = IosPush()
    val language = (NSLocale.preferredLanguages.firstOrNull() as? String)?.take(2)?.takeIf { it == "hi" } ?: "en"
    val version = NSBundle.mainBundle.objectForInfoDictionaryKey("CFBundleShortVersionString") as? String ?: "0.0.1"
    val api = apiBase()

    return ComposeUIViewController {
        App(
            AppDeps(
                store = store,
                appVersion = version,
                platform = "ios",
                language = language,
                deviceName = UIDevice.currentDevice.name.take(80),
                openUrl = ::openOutside,
                reminders = reminders,
                push = push,
                link = incomingLink.value,
                shareImage = { image, _ -> IosCardExport.share(image) },
                saveImage = { image, _ -> IosCardExport.save(image) },
                shareFile = { name, _, bytes -> IosCardExport.shareFile(name, bytes) },
                encodePng = IosCardExport::png,
                makePdf = IosDayPdf::render,
                openInApp = { url ->
                    val address = NSURL.URLWithString(url)
                    if (address != null && (address.scheme == "https" || address.scheme == "http")) present(SFSafariViewController(uRL = address)) else openOutside(url)
                },
                apiBase = api,
            )
        )
    }
}
