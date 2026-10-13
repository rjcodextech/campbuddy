package club.campbuddy.app

import android.content.Context
import android.content.Intent
import android.net.Uri
import android.os.Build
import android.os.Bundle
import androidx.activity.ComponentActivity
import androidx.activity.compose.setContent
import androidx.activity.enableEdgeToEdge
import club.campbuddy.shared.App
import club.campbuddy.shared.AppDeps
import club.campbuddy.shared.data.KeyValueStore
import java.util.Locale

class MainActivity : ComponentActivity() {
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

        setContent {
            App(
                AppDeps(
                    store = store,
                    appVersion = version,
                    platform = "android",
                    language = if (Locale.getDefault().language == "hi") "hi" else "en",
                    deviceName = "${Build.MANUFACTURER} ${Build.MODEL}".take(80),
                    openUrl = { url -> startActivity(Intent(Intent.ACTION_VIEW, Uri.parse(url))) },
                )
            )
        }
    }
}
