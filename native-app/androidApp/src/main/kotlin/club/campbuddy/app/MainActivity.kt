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
import club.campbuddy.shared.App
import club.campbuddy.shared.AppDeps
import club.campbuddy.shared.data.KeyValueStore
import java.util.Locale
import kotlin.coroutines.resume
import kotlinx.coroutines.CancellableContinuation

class MainActivity : ComponentActivity() {
    private var waiting: CancellableContinuation<Boolean>? = null

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

        val prefs = getSharedPreferences("campbuddy", Context.MODE_PRIVATE)
        val store = object : KeyValueStore {
            override fun get(key: String): String? = prefs.getString(key, null)
            override fun put(key: String, value: String) = prefs.edit().putString(key, value).apply()
            override fun remove(key: String) = prefs.edit().remove(key).apply()
        }
        val version = packageManager.getPackageInfo(packageName, 0).versionName ?: "2.0.0"
        val reminders = AndroidReminders(applicationContext) { continuation ->
            waiting = continuation
            notificationQuestion.launch(android.Manifest.permission.POST_NOTIFICATIONS)
        }

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
                    apiBase = apiBase(prefs),
                )
            )
        }
    }
}
