package club.campbuddy.app.update

import android.annotation.SuppressLint
import android.app.NotificationChannel
import android.app.NotificationManager
import android.app.PendingIntent
import android.content.Context
import android.content.Intent
import android.content.pm.PackageInfo
import android.content.pm.PackageInstaller
import android.content.pm.PackageManager
import android.net.Uri
import android.os.Build
import android.provider.Settings
import androidx.core.app.NotificationCompat
import androidx.core.app.NotificationManagerCompat
import androidx.core.content.pm.PackageInfoCompat
import club.campbuddy.app.BuildConfig
import club.campbuddy.app.MainActivity
import club.campbuddy.app.R
import club.campbuddy.shared.domain.AppUpdater
import club.campbuddy.shared.domain.UpdateState
import java.io.File
import java.io.FileOutputStream
import java.io.IOException
import java.net.HttpURLConnection
import java.net.URL
import java.security.MessageDigest
import kotlinx.coroutines.CoroutineScope
import kotlinx.coroutines.Dispatchers
import kotlinx.coroutines.SupervisorJob
import kotlinx.coroutines.delay
import kotlinx.coroutines.flow.MutableStateFlow
import kotlinx.coroutines.flow.StateFlow
import kotlinx.coroutines.launch
import kotlinx.coroutines.sync.Mutex
import kotlinx.coroutines.sync.withLock
import org.json.JSONObject

/**
 * Keeps the APK build of CampBuddy up to date from campbuddy.club (Admin → Mobile app → App updates).
 *
 * 1. Asks `/api/v1/app/update` when the app opens (at most every 30 minutes), every 6 hours in the background
 *    (UpdateWorker) and right away on the silent push sent when a version is published.
 * 2. Downloads the newer APK into the app's own storage, resuming a broken download, and checks it: the size and
 *    SHA-256 the server gave, the package name, a higher build number, and this app's own signing key.
 * 3. Installs it with PackageInstaller. On Android 12 and newer an app updating itself installs without a tap;
 *    otherwise Android asks once (in the app when it is open, from a notification when it is not). Android also
 *    needs a one-time "Install unknown apps" OK for CampBuddy, which the app asks for with an explanation.
 *
 * While the app is on screen nothing is installed under the person's feet: the app offers the update (or, for a
 * required one, shows only the update screen) and an optional one installs a few minutes after the app is closed.
 */
class ApkUpdater private constructor(private val context: Context) : AppUpdater {
    private val prefs = context.getSharedPreferences("app-update", Context.MODE_PRIVATE)
    private val pm: PackageManager = context.packageManager
    private val pkg: String = context.packageName
    private val scope = CoroutineScope(SupervisorJob() + Dispatchers.IO)
    private val lock = Mutex()
    private val _state = MutableStateFlow<UpdateState>(UpdateState.Idle)
    override val state: StateFlow<UpdateState> = _state

    @Volatile
    var foreground = false
        private set

    var apiBase: String
        get() = prefs.getString("api", null) ?: BuildConfig.API_BASE
        set(value) = prefs.edit().putString("api", value).apply()

    private val currentCode: Long get() = PackageInfoCompat.getLongVersionCode(pm.getPackageInfo(pkg, 0))

    private val dir: File get() = File(context.noBackupFilesDir, "updates").apply { mkdirs() }

    override fun check() {
        scope.launch { run(force = true) }
    }

    override fun install() {
        scope.launch { lock.withLock { installNow(fromPerson = true) } }
    }

    override fun allowInstalls() {
        val intent = if (Build.VERSION.SDK_INT >= 26) {
            Intent(Settings.ACTION_MANAGE_UNKNOWN_APP_SOURCES, Uri.parse("package:$pkg"))
        } else {
            Intent(Settings.ACTION_SECURITY_SETTINGS)
        }
        runCatching { context.startActivity(intent.addFlags(Intent.FLAG_ACTIVITY_NEW_TASK)) }
    }

    override fun later() {
        val now = _state.value
        val ready = pending() ?: return
        prefs.edit().putLong("postponed", ready.code).apply()
        _state.value = UpdateState.Ready(ready.name, ready.notes, ready.required || now.required, postponed = true)
    }

    fun onForeground(context: Context, visible: Boolean) {
        foreground = visible
        if (visible) {
            // Back from the "Install unknown apps" setting with the OK given: carry on with what the person wanted.
            val now = _state.value
            if (now is UpdateState.NeedsPermission && canInstall()) install()
            scope.launch { run(force = false) }
        } else if (_state.value is UpdateState.Ready) {
            UpdateWorker.installSoon(context)
        }
    }

    /**
     * Checks the server (unless it was asked within 30 minutes and [force] is false), downloads and checks a newer
     * version, and installs it when the app is not on screen. False when the network failed (the worker retries).
     */
    suspend fun run(force: Boolean): Boolean = lock.withLock {
        restore()
        if (!force && System.currentTimeMillis() - prefs.getLong("checked", 0) < CHECK_GAP_MS) {
            if (!foreground) installNow(fromPerson = false)
            return true
        }

        val info = try {
            fetch()
        } catch (e: Exception) {
            if (_state.value is UpdateState.Downloading) _state.value = UpdateState.Failed(_state.value.required)
            return false
        }
        prefs.edit().putLong("checked", System.currentTimeMillis()).apply()
        val current = currentCode
        val required = info.requiredCode > current
        val latest = info.latest

        if (latest == null || latest.code <= current) {
            clear()
            // Required yet nothing newer is offered (withdrawn?): the update screen offers the website instead.
            _state.value = if (required) UpdateState.Failed(true) else UpdateState.Idle
            return true
        }
        if (latest.cert.isNotEmpty() && latest.cert !in installedCerts()) {
            // Signed with another key than this install (e.g. a Play Store copy): Android would refuse it.
            clear()
            _state.value = if (required) UpdateState.Failed(true) else UpdateState.Idle
            return true
        }

        val file = download(latest, required) ?: run {
            _state.value = UpdateState.Failed(required)
            return false
        }
        if (!verified(file, latest)) {
            file.delete()
            _state.value = UpdateState.Failed(required)
            return true
        }
        prefs.edit().putString("pending", latest.copy(required = required).toJson().toString()).apply()
        _state.value = UpdateState.Ready(latest.name, latest.notes, required, postponed = prefs.getLong("postponed", 0) == latest.code)
        if (!foreground) installNow(fromPerson = false)
        true
    }

    /** The answer from the installer: Android wants the person to confirm. */
    fun confirm(intent: Intent) {
        val ready = pending()
        if (ready != null) _state.value = UpdateState.Ready(ready.name, ready.notes, ready.required, postponed = true)
        if (foreground) {
            runCatching { context.startActivity(intent.addFlags(Intent.FLAG_ACTIVITY_NEW_TASK)) }
        } else {
            notify(context.getString(R.string.update_ready_title), context.getString(R.string.update_ready_text), PendingIntent.getActivity(context, 1, intent, PendingIntent.FLAG_IMMUTABLE or PendingIntent.FLAG_UPDATE_CURRENT))
        }
    }

    /** The installer gave up: the person said no, or the file was refused. */
    fun failed(status: Int) {
        val ready = pending()
        when {
            ready == null -> _state.value = UpdateState.Idle
            status == PackageInstaller.STATUS_FAILURE_ABORTED -> {
                // "Cancel" on Android's question: ask again later, not now.
                prefs.edit().putLong("postponed", ready.code).apply()
                _state.value = UpdateState.Ready(ready.name, ready.notes, ready.required, postponed = true)
            }
            status == PackageInstaller.STATUS_FAILURE_STORAGE -> _state.value = UpdateState.Failed(ready.required)
            else -> {
                clear()
                _state.value = UpdateState.Failed(ready.required)
            }
        }
    }

    /** Called in the new version after an update: the downloaded file is no longer needed. */
    fun replaced() {
        val reopen = prefs.getBoolean("reopen", false)
        val name = pending()?.name
        clear()
        prefs.edit().remove("reopen").apply()
        if (reopen && name != null) {
            val open = PendingIntent.getActivity(context, 2, Intent(context, MainActivity::class.java).addFlags(Intent.FLAG_ACTIVITY_NEW_TASK), PendingIntent.FLAG_IMMUTABLE)
            notify(context.getString(R.string.update_done_title, name), context.getString(R.string.update_done_text), open)
        }
    }

    private fun canInstall(): Boolean = Build.VERSION.SDK_INT < 26 || pm.canRequestPackageInstalls()

    /** The state after the app process was started again (the download is kept on disk). */
    private fun restore() {
        if (_state.value != UpdateState.Idle) return
        val ready = pending() ?: return
        val file = File(dir, "${ready.code}.apk")
        if (ready.code <= currentCode || !file.exists()) {
            clear()
            return
        }
        _state.value = UpdateState.Ready(ready.name, ready.notes, ready.required, postponed = prefs.getLong("postponed", 0) == ready.code)
    }

    @SuppressLint("RequestInstallPackagesPolicy")
    private fun installNow(fromPerson: Boolean) {
        val ready = pending() ?: return
        val file = File(dir, "${ready.code}.apk")
        if (!file.exists() || ready.code <= currentCode) {
            clear()
            _state.value = UpdateState.Idle
            return
        }
        if (!canInstall()) {
            _state.value = UpdateState.NeedsPermission(ready.name, ready.required)
            if (!foreground) {
                val open = PendingIntent.getActivity(context, 3, Intent(context, MainActivity::class.java).addFlags(Intent.FLAG_ACTIVITY_NEW_TASK), PendingIntent.FLAG_IMMUTABLE)
                notify(context.getString(R.string.update_ready_title), context.getString(R.string.update_allow_text), open)
            }
            return
        }

        _state.value = UpdateState.Installing(ready.name, ready.required)
        prefs.edit().putBoolean("reopen", fromPerson && foreground).apply()
        try {
            val installer = pm.packageInstaller
            val params = PackageInstaller.SessionParams(PackageInstaller.SessionParams.MODE_FULL_INSTALL).apply {
                setAppPackageName(pkg)
                setSize(file.length())
                if (Build.VERSION.SDK_INT >= 31) setRequireUserAction(PackageInstaller.SessionParams.USER_ACTION_NOT_REQUIRED)
            }
            val id = installer.createSession(params)
            installer.openSession(id).use { session ->
                session.openWrite("campbuddy.apk", 0, file.length()).use { out ->
                    file.inputStream().use { it.copyTo(out) }
                    session.fsync(out)
                }
                val result = Intent(context, InstallResultReceiver::class.java).setPackage(pkg)
                val flags = PendingIntent.FLAG_UPDATE_CURRENT or (if (Build.VERSION.SDK_INT >= 31) PendingIntent.FLAG_MUTABLE else 0)
                session.commit(PendingIntent.getBroadcast(context, id, result, flags).intentSender)
            }
        } catch (e: Exception) {
            _state.value = UpdateState.Failed(ready.required)
        }
    }

    private fun fetch(): Info {
        val url = "$apiBase/app/update?package=${Uri.encode(pkg)}&code=$currentCode"
        val conn = open(url)
        try {
            if (conn.responseCode != 200) throw IOException("HTTP ${conn.responseCode}")
            val json = JSONObject(conn.inputStream.bufferedReader().use { it.readText() })
            val latest = json.optJSONObject("latest")?.let(Release::from)

            return Info(json.optLong("required_code", 0), latest)
        } finally {
            conn.disconnect()
        }
    }

    /** Downloads into "<code>.apk.part", resuming what is there, and renames it when complete. */
    private suspend fun download(latest: Release, required: Boolean): File? {
        val done = File(dir, "${latest.code}.apk")
        if (done.exists() && done.length() == latest.size && sha256(done) == latest.sha256) return done
        dir.listFiles()?.filter { !it.name.startsWith("${latest.code}.") }?.forEach { it.delete() }
        if (!latest.url.startsWith("https://") && !BuildConfig.DEBUG) return null

        val part = File(dir, "${latest.code}.apk.part")
        if (part.length() > latest.size) part.delete()
        repeat(DOWNLOAD_TRIES) { attempt ->
            var have = part.length()
            _state.value = UpdateState.Downloading(latest.name, percent(have, latest.size), required)
            try {
                val conn = open(latest.url, if (have > 0) "bytes=$have-" else null)
                try {
                    when (conn.responseCode) {
                        206 -> Unit
                        200 -> { part.delete(); have = 0 } // the server sent the whole file
                        416 -> { part.delete(); throw IOException("range") }
                        else -> throw IOException("HTTP ${conn.responseCode}")
                    }
                    conn.inputStream.use { input ->
                        FileOutputStream(part, have > 0).use { output ->
                            val buffer = ByteArray(64 * 1024)
                            var shown = -1
                            while (true) {
                                val read = input.read(buffer)
                                if (read < 0) break
                                output.write(buffer, 0, read)
                                have += read
                                val now = percent(have, latest.size)
                                if (now != shown) {
                                    shown = now
                                    _state.value = UpdateState.Downloading(latest.name, now, required)
                                }
                            }
                        }
                    }
                } finally {
                    conn.disconnect()
                }
                if (part.length() == latest.size && part.renameTo(done)) return done
                if (part.length() > latest.size) part.delete()
            } catch (e: IOException) {
                // Network trouble: wait a little and resume from what arrived.
            }
            delay(2000L shl attempt)
        }

        return null
    }

    private fun verified(file: File, latest: Release): Boolean {
        if (file.length() != latest.size || sha256(file) != latest.sha256) return false
        @Suppress("DEPRECATION")
        val flags = if (Build.VERSION.SDK_INT >= 28) PackageManager.GET_SIGNING_CERTIFICATES else PackageManager.GET_SIGNATURES
        val archive = pm.getPackageArchiveInfo(file.path, flags) ?: return false
        if (archive.packageName != pkg || PackageInfoCompat.getLongVersionCode(archive) <= currentCode) return false
        val theirs = certs(archive)

        return theirs.isEmpty() || theirs.any { it in installedCerts() }
    }

    private fun installedCerts(): Set<String> {
        @Suppress("DEPRECATION")
        val flags = if (Build.VERSION.SDK_INT >= 28) PackageManager.GET_SIGNING_CERTIFICATES else PackageManager.GET_SIGNATURES

        return certs(pm.getPackageInfo(pkg, flags))
    }

    private fun certs(info: PackageInfo): Set<String> {
        val signatures = if (Build.VERSION.SDK_INT >= 28) {
            info.signingInfo?.let { if (it.hasMultipleSigners()) it.apkContentsSigners else it.signingCertificateHistory }
        } else {
            @Suppress("DEPRECATION") info.signatures
        }

        return signatures.orEmpty().map { hex(MessageDigest.getInstance("SHA-256").digest(it.toByteArray())) }.toSet()
    }

    private fun open(url: String, range: String? = null): HttpURLConnection =
        (URL(url).openConnection() as HttpURLConnection).apply {
            connectTimeout = 15_000
            readTimeout = 30_000
            instanceFollowRedirects = true
            setRequestProperty("Accept", "application/json, application/vnd.android.package-archive")
            setRequestProperty("User-Agent", "CampBuddyApp/${BuildConfig.VERSION_NAME} (Android ${Build.VERSION.RELEASE})")
            if (range != null) setRequestProperty("Range", range)
        }

    private fun pending(): Release? = prefs.getString("pending", null)?.let { runCatching { Release.from(JSONObject(it)) }.getOrNull() }

    private fun clear() {
        dir.listFiles()?.forEach { it.delete() }
        prefs.edit().remove("pending").remove("postponed").apply()
    }

    private fun notify(title: String, text: String, open: PendingIntent) {
        val manager = NotificationManagerCompat.from(context)
        if (!manager.areNotificationsEnabled()) return
        if (Build.VERSION.SDK_INT >= 26) {
            context.getSystemService(NotificationManager::class.java)
                .createNotificationChannel(NotificationChannel(CHANNEL, context.getString(R.string.update_channel), NotificationManager.IMPORTANCE_DEFAULT))
        }
        runCatching {
            manager.notify(
                NOTIFICATION_ID,
                NotificationCompat.Builder(context, CHANNEL).setSmallIcon(R.drawable.ic_stat_campbuddy).setContentTitle(title).setContentText(text)
                    .setStyle(NotificationCompat.BigTextStyle().bigText(text)).setContentIntent(open).setAutoCancel(true).build(),
            )
        }
    }

    private class Info(val requiredCode: Long, val latest: Release?)

    private data class Release(
        val code: Long,
        val name: String,
        val size: Long,
        val sha256: String,
        val cert: String,
        val url: String,
        val notes: Map<String, String>,
        val required: Boolean,
    ) {
        fun toJson(): JSONObject = JSONObject()
            .put("version_code", code).put("version_name", name).put("size", size).put("sha256", sha256).put("cert_sha256", cert)
            .put("url", url).put("notes", JSONObject(notes)).put("required", required)

        companion object {
            fun from(json: JSONObject): Release {
                val notes = json.optJSONObject("notes")
                return Release(
                    code = json.getLong("version_code"),
                    name = json.getString("version_name"),
                    size = json.getLong("size"),
                    sha256 = json.getString("sha256").lowercase(),
                    cert = json.optString("cert_sha256").lowercase(),
                    url = json.getString("url"),
                    notes = notes?.keys()?.asSequence()?.associateWith { notes.optString(it) }.orEmpty(),
                    required = json.optBoolean("required"),
                )
            }
        }
    }

    companion object {
        private const val CHANNEL = "app-updates"
        private const val NOTIFICATION_ID = 7001
        private const val CHECK_GAP_MS = 30 * 60 * 1000L
        private const val DOWNLOAD_TRIES = 4

        @Volatile
        private var instance: ApkUpdater? = null

        fun get(context: Context): ApkUpdater =
            instance ?: synchronized(this) { instance ?: ApkUpdater(context.applicationContext).also { instance = it } }

        private fun percent(have: Long, size: Long): Int = if (size <= 0) 0 else ((have * 100) / size).toInt().coerceIn(0, 100)

        private fun hex(bytes: ByteArray): String = bytes.joinToString("") { "%02x".format(it) }

        private fun sha256(file: File): String {
            val digest = MessageDigest.getInstance("SHA-256")
            file.inputStream().use { input ->
                val buffer = ByteArray(64 * 1024)
                while (true) {
                    val read = input.read(buffer)
                    if (read < 0) break
                    digest.update(buffer, 0, read)
                }
            }
            return hex(digest.digest())
        }
    }
}
