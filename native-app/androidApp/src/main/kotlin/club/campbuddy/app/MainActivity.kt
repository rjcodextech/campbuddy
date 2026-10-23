package club.campbuddy.app

import android.content.Context
import android.content.Intent
import android.net.Uri
import android.os.Build
import android.os.Bundle
import androidx.activity.ComponentActivity
import androidx.activity.compose.setContent
import androidx.activity.enableEdgeToEdge
import androidx.activity.result.contract.ActivityResultContracts
import androidx.compose.runtime.getValue
import androidx.compose.ui.graphics.asAndroidBitmap
import androidx.compose.runtime.setValue
import club.campbuddy.shared.App
import club.campbuddy.shared.AppDeps
import club.campbuddy.shared.data.KeyValueStore
import club.campbuddy.shared.domain.DeepLink
import java.util.Locale
import kotlin.coroutines.resume
import kotlinx.coroutines.CancellableContinuation

class MainActivity : ComponentActivity() {
    private var waiting: CancellableContinuation<Boolean>? = null
    private var link by androidx.compose.runtime.mutableStateOf<DeepLink?>(null)
    private var linkCount = 0

    private fun linkFrom(intent: Intent?): DeepLink? =
        intent?.data?.let { DeepLink.parse(it.scheme, it.host, it.path, ++linkCount, it.query) }

    override fun onNewIntent(intent: Intent) {
        super.onNewIntent(intent)
        setIntent(intent)
        linkFrom(intent)?.let { link = it }
    }

    // Registered before the screen starts, as Android requires; answers the one notification question.
    private val notificationQuestion = registerForActivityResult(ActivityResultContracts.RequestPermission()) { granted ->
        waiting?.resume(granted)
        waiting = null
    }

    /**
     * The server to talk to. Release builds always use campbuddy.club. A debug build can be pointed at a
     * development server once: adb shell am start -n club.campbuddy.app.native/club.campbuddy.app.MainActivity --es api http://10.0.2.2:8099/api/v1
     * (and back with --es api reset).
     */
    private fun apiBase(prefs: android.content.SharedPreferences): String {
        if (!BuildConfig.DEBUG) return BuildConfig.API_BASE

        intent?.getStringExtra("api")?.let { given ->
            if (given == "reset") prefs.edit().remove("dev.api").apply() else prefs.edit().putString("dev.api", given).apply()
        }

        return prefs.getString("dev.api", null) ?: BuildConfig.API_BASE
    }

    override fun onCreate(savedInstanceState: Bundle?) {
        super.onCreate(savedInstanceState)
        enableEdgeToEdge()
        link = linkFrom(intent)

        val plainPrefs = getSharedPreferences("campbuddy", Context.MODE_PRIVATE)
        val prefs: android.content.SharedPreferences = runCatching {
            val masterKey = androidx.security.crypto.MasterKey.Builder(applicationContext)
                .setKeyScheme(androidx.security.crypto.MasterKey.KeyScheme.AES256_GCM)
                .build()
            val secure = androidx.security.crypto.EncryptedSharedPreferences.create(
                applicationContext,
                "campbuddy_secure",
                masterKey,
                androidx.security.crypto.EncryptedSharedPreferences.PrefKeyEncryptionScheme.AES256_SIV,
                androidx.security.crypto.EncryptedSharedPreferences.PrefValueEncryptionScheme.AES256_GCM
            )
            // One-time migration of plain preferences to encrypted storage
            if (plainPrefs.all.isNotEmpty()) {
                val editor = secure.edit()
                for ((k, v) in plainPrefs.all) {
                    if (v is String && !secure.contains(k)) {
                        editor.putString(k, v)
                    }
                }
                editor.apply()
                plainPrefs.edit().clear().apply()
            }
            secure
        }.getOrElse { plainPrefs }

        val store = object : KeyValueStore {
            override fun get(key: String): String? = prefs.getString(key, null)
            override fun put(key: String, value: String) = prefs.edit().putString(key, value).apply()
            override fun remove(key: String) = prefs.edit().remove(key).apply()
        }
        val version = packageManager.getPackageInfo(packageName, 0).versionName ?: "0.0.1"
        val reminders = AndroidReminders(applicationContext) { continuation ->
            waiting = continuation
            notificationQuestion.launch(android.Manifest.permission.POST_NOTIFICATIONS)
        }

        val cardExport = AndroidCardExport(this)
        val api = apiBase(prefs)
        // The APK build updates itself (Admin → Mobile app → App updates); null in the Play build.
        val updater = club.campbuddy.app.update.Updates.updater(applicationContext, api)

        setContent {
            App(
                AppDeps(
                    store = store,
                    appVersion = version,
                    platform = "android",
                    language = if (Locale.getDefault().language == "hi") "hi" else "en",
                    deviceName = "${Build.MANUFACTURER} ${Build.MODEL}".take(80),
                    openUrl = { url -> startActivity(Intent(Intent.ACTION_VIEW, Uri.parse(url))) },
                    reminders = reminders,
                    push = AndroidPush(applicationContext),
                    link = link,
                    shareImage = cardExport::share,
                    saveImage = cardExport::save,
                    shareFile = cardExport::shareFile,
                    encodePng = { image -> java.io.ByteArrayOutputStream().also { image.asAndroidBitmap().compress(android.graphics.Bitmap.CompressFormat.PNG, 100, it) }.toByteArray() },
                    makePdf = AndroidDayPdf::render,
                    openInApp = { url ->
                        val tab = androidx.browser.customtabs.CustomTabsIntent.Builder()
                            .setShowTitle(true)
                            .setUrlBarHidingEnabled(false)
                            .setShareState(androidx.browser.customtabs.CustomTabsIntent.SHARE_STATE_ON)
                            .setDefaultColorSchemeParams(androidx.browser.customtabs.CustomTabColorSchemeParams.Builder().setToolbarColor(0xFFFFF0DF.toInt()).build())
                            .build()
                        runCatching { tab.launchUrl(this@MainActivity, Uri.parse(url)) }.onFailure { startActivity(Intent(Intent.ACTION_VIEW, Uri.parse(url))) }
                    },
                    apiBase = api,
                    updater = updater,
                )
            )
        }
    }

    override fun onStart() {
        super.onStart()
        club.campbuddy.app.update.Updates.onForeground(applicationContext, true)
    }

    override fun onStop() {
        club.campbuddy.app.update.Updates.onForeground(applicationContext, false)
        super.onStop()
    }
}
