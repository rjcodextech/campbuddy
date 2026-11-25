package club.campbuddy.shared.ui

import androidx.compose.runtime.Composable
import androidx.compose.runtime.LaunchedEffect
import androidx.compose.runtime.MutableState
import androidx.compose.runtime.State
import androidx.compose.runtime.collectAsState
import androidx.compose.runtime.getValue
import androidx.compose.runtime.mutableStateOf
import androidx.compose.runtime.remember
import androidx.compose.runtime.setValue
import club.campbuddy.shared.data.ApiException
import club.campbuddy.shared.data.Loaded
import kotlinx.coroutines.CancellationException

sealed interface LoadState<out T> {
    data object Loading : LoadState<Nothing>
    data class Ready<T>(val value: T, val fromCache: Boolean) : LoadState<T>
    data class Failed(val message: String) : LoadState<Nothing>
}

/** A screen load that can be repeated: [reload] runs it again (retry, pull to refresh). */
class Loader<T>(val state: State<LoadState<T>>, val reload: () -> Unit)

@Composable
fun <T> rememberLoader(key: Any?, fetch: suspend () -> Loaded<T>): Loader<T> {
    val repo = LocalEnv.current.repository
    val catalogTick by repo.catalogTick.collectAsState()
    val eventTick by repo.eventTick.collectAsState()
    val autoTick = when {
        key == "events" -> catalogTick
        key is String && key.contains(':') && !key.startsWith("org-") && !key.startsWith("admin-") -> eventTick
        else -> 0
    }
    var attempt by remember(key) { mutableStateOf(0) }
    var lastAttempt by remember(key) { mutableStateOf(-1) }
    val state: MutableState<LoadState<T>> = remember(key) { mutableStateOf(LoadState.Loading) }

    LaunchedEffect(key, attempt, autoTick) {
        val silent = state.value is LoadState.Ready && attempt == lastAttempt
        lastAttempt = attempt
        if (!silent) state.value = LoadState.Loading
        try {
            val loaded = fetch()
            state.value = LoadState.Ready(loaded.value, loaded.fromCache)
        } catch (cancelled: CancellationException) {
            throw cancelled
        } catch (error: ApiException) {
            if (!silent) state.value = LoadState.Failed(error.message)
        } catch (error: Exception) {
            if (!silent) state.value = LoadState.Failed("Could not reach CampBuddy. Check your connection and try again.")
        }
    }

    return Loader(state) { attempt++ }
}
