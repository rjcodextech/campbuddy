package club.campbuddy.shared

import androidx.compose.foundation.background
import androidx.compose.foundation.layout.Arrangement
import androidx.compose.foundation.layout.Column
import androidx.compose.foundation.layout.fillMaxSize
import androidx.compose.foundation.layout.padding
import androidx.compose.foundation.layout.statusBarsPadding
import androidx.compose.material3.MaterialTheme
import androidx.compose.material3.Text
import androidx.compose.runtime.Composable
import androidx.compose.runtime.CompositionLocalProvider
import androidx.compose.runtime.LaunchedEffect
import androidx.compose.runtime.getValue
import androidx.compose.runtime.mutableStateListOf
import androidx.compose.runtime.mutableStateOf
import androidx.compose.runtime.remember
import androidx.compose.runtime.setValue
import androidx.compose.ui.Alignment
import androidx.compose.ui.Modifier
import androidx.compose.ui.unit.dp
import club.campbuddy.shared.data.ApiClient
import club.campbuddy.shared.data.KeyValueStore
import club.campbuddy.shared.data.Repository
import club.campbuddy.shared.data.UserData
import club.campbuddy.shared.ui.Env
import club.campbuddy.shared.ui.LocalEnv
import club.campbuddy.shared.ui.ReminderSync
import club.campbuddy.shared.domain.compareVersions
import club.campbuddy.shared.model.OrganizerEvent
import club.campbuddy.shared.ui.CampBuddyTheme
import club.campbuddy.shared.ui.EventScreen
import club.campbuddy.shared.ui.LocalBrand
import club.campbuddy.shared.ui.OrganizerEventScreen
import club.campbuddy.shared.ui.OrganizerHomeScreen
import club.campbuddy.shared.ui.OrganizerLoginScreen
import club.campbuddy.shared.ui.PickerScreen
import club.campbuddy.shared.ui.PrimaryButton
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
    val makePdf: (club.campbuddy.shared.domain.DayPdf) -> ByteArray? = { null },
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

    CampBuddyTheme(words) {
      CompositionLocalProvider(LocalEnv provides env) {
        ReminderSync()
        if (compareVersions(deps.appVersion, minimum) < 0) {
            UpdateScreen(words, deps)
            return@CompositionLocalProvider
        }

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
            is Screen.Event -> androidx.compose.runtime.key(screen) { EventScreen(screen.slug, onBack = ::pop, startInGuide = screen.guide, startTab = screen.tab, startSub = screen.sub) }
            Screen.OrganizerLogin -> OrganizerLoginScreen(api, repository, deps.deviceName, onSignedIn = { stack.removeAt(stack.lastIndex); push(Screen.OrganizerHome) }, onBack = ::pop)
            Screen.OrganizerHome -> OrganizerHomeScreen(api, repository, onEvent = { push(Screen.OrganizerEvent(it)) }, onSignedOut = { while (stack.size > 1) stack.removeAt(stack.lastIndex) }, onBack = ::pop)
            is Screen.OrganizerEvent -> OrganizerEventScreen(api, repository, screen.event, onBack = ::pop)
        }
      }
    }
}

@Composable
private fun UpdateScreen(words: Words, deps: AppDeps) {
    val brand = LocalBrand.current
    Column(Modifier.fillMaxSize().background(brand.background).statusBarsPadding().padding(24.dp), verticalArrangement = Arrangement.Center, horizontalAlignment = Alignment.CenterHorizontally) {
        Text(words("common.update_title"), style = MaterialTheme.typography.headlineSmall)
        Text(words("common.update_text"), style = MaterialTheme.typography.bodyLarge, modifier = Modifier.padding(vertical = 12.dp))
        PrimaryButton("Update", { deps.openUrl("https://campbuddy.club") })
    }
}
