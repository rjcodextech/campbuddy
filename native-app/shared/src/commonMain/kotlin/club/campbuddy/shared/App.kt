package club.campbuddy.shared

import androidx.compose.foundation.background
import androidx.compose.foundation.layout.Arrangement
import androidx.compose.foundation.layout.Column
import androidx.compose.foundation.layout.fillMaxSize
import androidx.compose.foundation.layout.fillMaxWidth
import androidx.compose.foundation.layout.padding
import androidx.compose.foundation.layout.statusBarsPadding
import androidx.compose.material3.CircularProgressIndicator
import androidx.compose.material3.LinearProgressIndicator
import androidx.compose.material3.MaterialTheme
import androidx.compose.material3.Text
import androidx.compose.runtime.Composable
import androidx.compose.runtime.CompositionLocalProvider
import androidx.compose.runtime.LaunchedEffect
import androidx.compose.runtime.collectAsState
import androidx.compose.runtime.getValue
import androidx.compose.runtime.mutableStateListOf
import androidx.compose.runtime.mutableStateOf
import androidx.compose.runtime.remember
import androidx.compose.runtime.rememberCoroutineScope
import androidx.compose.runtime.setValue
import androidx.compose.ui.Alignment
import androidx.compose.ui.Modifier
import androidx.compose.ui.text.style.TextAlign
import androidx.compose.ui.unit.dp
import kotlinx.coroutines.launch
import club.campbuddy.shared.data.ApiClient
import club.campbuddy.shared.data.KeyValueStore
import club.campbuddy.shared.data.Repository
import club.campbuddy.shared.data.UserData
import club.campbuddy.shared.ui.Env
import club.campbuddy.shared.ui.LocalEnv
import club.campbuddy.shared.ui.ReminderSync
import club.campbuddy.shared.domain.AppUpdater
import club.campbuddy.shared.domain.UpdateState
import club.campbuddy.shared.domain.compareVersions
import club.campbuddy.shared.model.OrganizerEvent
import club.campbuddy.shared.ui.DataSweep
import club.campbuddy.shared.ui.CampBuddyTheme
import club.campbuddy.shared.ui.EventScreen
import club.campbuddy.shared.ui.LocalBrand
import club.campbuddy.shared.ui.OrganizerEventScreen
import club.campbuddy.shared.ui.OrganizerHomeScreen
import club.campbuddy.shared.ui.OrganizerLoginScreen
import club.campbuddy.shared.ui.PickerScreen
import club.campbuddy.shared.ui.PrimaryButton
import club.campbuddy.shared.ui.QuietButton
import club.campbuddy.shared.ui.Sheet
import club.campbuddy.shared.ui.SheetActions
import club.campbuddy.shared.ui.Words

/** What the platform shell hands the shared app. */
class AppDeps(
    val store: KeyValueStore,
    val appVersion: String,
    val platform: String, // "android" | "ios"
    val language: String, // "en" | "hi"
    val deviceName: String,
    val apiBase: String = "https://campbuddy.club/api/v1",
    val openUrl: (String) -> Unit,
    val reminders: club.campbuddy.shared.domain.ReminderPlatform = club.campbuddy.shared.domain.NoReminders,
    val push: club.campbuddy.shared.domain.PushPlatform = club.campbuddy.shared.domain.NoPush,
    val link: club.campbuddy.shared.domain.DeepLink? = null,
    /** Opens the share sheet with the card picture. */
    val shareImage: (androidx.compose.ui.graphics.ImageBitmap, String) -> Unit = { _, _ -> },
    /** Opens the share sheet with a file (a calendar file for a meeting or the plan): name, MIME type, contents. */
    val shareFile: (String, String, ByteArray) -> Unit = { _, _, _ -> },
    /** Saves the card picture to the phone's gallery; false when that is not possible. */
    val saveImage: (androidx.compose.ui.graphics.ImageBitmap, String) -> Boolean = { _, _ -> false },
    /** Draws "my day" as PDF pages; null when this phone cannot (the app then shares plain text). */
    /** Opens a web page inside the app (a sponsor site) instead of leaving for another app; falls back to [openUrl]. */
    val openInApp: ((String) -> Unit)? = null,
    val makePdf: (club.campbuddy.shared.domain.DayPdf) -> ByteArray? = { null },
    /** A picture as PNG bytes (the Social media card sent to Publish); null where the phone cannot. */
    val encodePng: (androidx.compose.ui.graphics.ImageBitmap) -> ByteArray? = { null },
    /** The APK build's self-updater; null on iPhone and in the Play Store build (their stores update them). */
    val updater: club.campbuddy.shared.domain.AppUpdater? = null,
)

private sealed interface Screen {
    data object Picker : Screen
    data class Event(val slug: String, val guide: Boolean = false, val tab: String? = null, val stamp: Int = 0, val sub: String? = null) : Screen
    data object OrganizerLogin : Screen
    data object OrganizerHome : Screen
    data class OrganizerEvent(val event: club.campbuddy.shared.model.OrganizerEvent) : Screen
}

/** The whole app. Both platform shells call this and nothing else. */
@Composable
fun App(deps: AppDeps) {
    val api = remember { ApiClient(baseUrl = deps.apiBase, appVersion = deps.appVersion, platform = deps.platform) }
    val repository = remember { Repository(api, deps.store) }
    val user = remember { UserData(deps.store) }
    val env = remember { Env(deps, api, repository, user) }
    coil3.compose.setSingletonImageLoaderFactory { context ->
        coil3.ImageLoader.Builder(context).components { add(coil3.network.ktor3.KtorNetworkFetcherFactory()) }.build()
    }
    var config by remember { mutableStateOf(repository.cachedConfig()) }
    val stack = remember { mutableStateListOf<Screen>(Screen.Picker) }

    // The saved (or bundled) config draws the first frame; the fresh one replaces it when it arrives.
    LaunchedEffect(Unit) { repository.freshConfig()?.let { config = it } }

    // A link from outside (a message, a QR code, a notification) opens that event, on top of the picker.
    LaunchedEffect(deps.link?.id, deps.link?.slug) {
        deps.link?.let { link ->
            while (stack.size > 1) stack.removeAt(stack.lastIndex)
            stack.add(Screen.Event(link.slug, guide = link.tab == "guide", tab = link.tab?.takeIf { it != "guide" }, stamp = link.id, sub = link.sub))
        }
    }

    val words = Words(config, deps.language)
    val minimum = if (deps.platform == "ios") config.minApp.ios else config.minApp.android
    val update by (deps.updater?.state ?: remember { kotlinx.coroutines.flow.MutableStateFlow<UpdateState>(UpdateState.Idle) }).collectAsState()

    CampBuddyTheme(words) {
      CompositionLocalProvider(LocalEnv provides env) {
        ReminderSync()
        DataSweep()
        if (compareVersions(deps.appVersion, minimum) < 0 || update.required) {
            UpdateScreen(words, deps, update)
            return@CompositionLocalProvider
        }
        UpdatePrompt(words, deps, update)

        val scope = rememberCoroutineScope()
        fun push(screen: Screen) = stack.add(screen)
        fun pop() { if (stack.size > 1) stack.removeAt(stack.lastIndex) }

        when (val screen = stack.last()) {
            Screen.Picker -> PickerScreen(
                repository = repository,
                banner = config.banner,
                language = deps.language,
                organizerEnabled = config.flag("organizer_login"),
                onBanner = { banner ->
                    val action = banner.action
                    when {
                        action?.type == "event" && action.slug != null -> push(Screen.Event(action.slug))
                        action?.type == "url" && action.url != null -> deps.openUrl(action.url)
                    }
                },
                onOpen = { push(Screen.Event(it.slug)) },
                onFirstTimer = { push(Screen.Event(it.slug, guide = true)) },
                onOrganizer = { push(if (repository.organizerToken() != null) Screen.OrganizerHome else Screen.OrganizerLogin) },
            )
            is Screen.Event -> androidx.compose.runtime.key(screen) {
                EventScreen(
                    slug = screen.slug,
                    onBack = ::pop,
                    startInGuide = screen.guide,
                    startTab = screen.tab,
                    startSub = screen.sub,
                    onStaff = if (repository.organizerToken() != null) {
                        {
                            val token = repository.organizerToken().orEmpty()
                            scope.launch {
                                val events = runCatching { api.organizerEvents(token) }.getOrNull().orEmpty()
                                val target = events.firstOrNull { it.slug == screen.slug }
                                if (target != null) {
                                    push(Screen.OrganizerEvent(target))
                                } else {
                                    push(Screen.OrganizerHome)
                                }
                            }
                        }
                    } else null,
                )
            }
            Screen.OrganizerLogin -> OrganizerLoginScreen(api, repository, deps.deviceName, onSignedIn = { stack.removeAt(stack.lastIndex); push(Screen.OrganizerHome) }, onBack = ::pop)
            Screen.OrganizerHome -> OrganizerHomeScreen(
                api = api,
                repository = repository,
                onEvent = { push(Screen.OrganizerEvent(it)) },
                onPreviewAttendee = { push(Screen.Event(it)) },
                onSignedOut = { while (stack.size > 1) stack.removeAt(stack.lastIndex) },
                onBack = ::pop,
            )
            is Screen.OrganizerEvent -> OrganizerEventScreen(api, repository, screen.event, onBack = ::pop, onPreviewAttendee = { push(Screen.Event(it)) })
        }
      }
    }
}

/** This build may no longer be used. The APK build updates itself from here; iPhone and the Play build open the website. */
@Composable
private fun UpdateScreen(words: Words, deps: AppDeps, update: UpdateState) {
    val brand = LocalBrand.current
    val updater = deps.updater
    Column(Modifier.fillMaxSize().background(brand.background).statusBarsPadding().padding(24.dp), verticalArrangement = Arrangement.Center, horizontalAlignment = Alignment.CenterHorizontally) {
        Text(words("common.update_title"), style = MaterialTheme.typography.headlineSmall, textAlign = TextAlign.Center)
        Text(words("common.update_text"), style = MaterialTheme.typography.bodyLarge, textAlign = TextAlign.Center, modifier = Modifier.padding(vertical = 12.dp))
        if (updater == null) {
            PrimaryButton("Update", { deps.openUrl("https://campbuddy.club") })
            return@Column
        }
        UpdateSteps(words, deps, updater, update)
    }
}

/** The steps of an update in progress, shared by the blocking screen and the "new version" sheet. */
@Composable
private fun UpdateSteps(words: Words, deps: AppDeps, updater: AppUpdater, update: UpdateState) {
    val brand = LocalBrand.current
    Column(Modifier.fillMaxWidth(), verticalArrangement = Arrangement.spacedBy(12.dp), horizontalAlignment = Alignment.CenterHorizontally) {
        when (update) {
            is UpdateState.Downloading -> {
                Text(words("common.update_downloading"), style = MaterialTheme.typography.bodyMedium)
                LinearProgressIndicator(progress = { update.percent / 100f }, modifier = Modifier.fillMaxWidth(), color = brand.primary, trackColor = brand.line)
            }
            is UpdateState.Installing -> CircularProgressIndicator(color = brand.primary)
            is UpdateState.NeedsPermission -> {
                Text(words("common.update_allow"), style = MaterialTheme.typography.bodyMedium, textAlign = TextAlign.Center)
                PrimaryButton(words("common.update_allow_button"), updater::allowInstalls, Modifier.fillMaxWidth())
            }
            is UpdateState.Ready -> PrimaryButton(words("common.update_install"), updater::install, Modifier.fillMaxWidth())
            is UpdateState.Failed -> {
                Text(words("common.update_failed"), style = MaterialTheme.typography.bodyMedium.copy(color = brand.danger), textAlign = TextAlign.Center)
                PrimaryButton(words("common.retry"), updater::check, Modifier.fillMaxWidth())
            }
            UpdateState.Idle -> PrimaryButton(words("common.update_now"), updater::check, Modifier.fillMaxWidth())
        }
        if (update is UpdateState.Failed || update == UpdateState.Idle) {
            // Last resort: the newest APK from the website (same domain as the API).
            QuietButton(words("common.update_browser"), { deps.openUrl(deps.apiBase.substringBefore("/api/") + "/app/android") }, Modifier.fillMaxWidth())
        }
    }
}

/**
 * A newer version is downloaded and checked: offered once. "Later" (or closing the sheet) installs it the next
 * time the app is closed instead; if Android first needs the person's OK, the sheet asks for that.
 */
@Composable
private fun UpdatePrompt(words: Words, deps: AppDeps, update: UpdateState) {
    val updater = deps.updater ?: return
    val version = when (update) {
        is UpdateState.Ready -> update.version.takeUnless { update.postponed }
        is UpdateState.NeedsPermission -> update.version
        else -> null
    } ?: return
    val brand = LocalBrand.current
    val ready = update as? UpdateState.Ready
    val actions: (@Composable () -> Unit)? = if (ready == null) null else {
        { SheetActions(words("common.update_install"), updater::install, words("common.update_later"), updater::later) }
    }
    Sheet("CampBuddy $version", onClose = updater::later, actions = actions) {
        if (ready == null) {
            UpdateSteps(words, deps, updater, update)
        } else {
            Text(words("common.update_ready"), style = MaterialTheme.typography.bodyLarge)
            val notes = ready.notes[deps.language]?.takeIf(String::isNotBlank) ?: ready.notes["en"].orEmpty()
            if (notes.isNotBlank()) Text(notes, style = MaterialTheme.typography.bodyMedium.copy(color = brand.muted))
        }
    }
}
