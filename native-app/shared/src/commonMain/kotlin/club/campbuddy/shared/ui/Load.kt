package club.campbuddy.shared.ui

import androidx.compose.runtime.Composable
import androidx.compose.runtime.MutableState
import androidx.compose.runtime.State
import androidx.compose.runtime.getValue
import androidx.compose.runtime.LaunchedEffect
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
    var attempt by remember(key) { mutableStateOf(0) }
    val state: MutableState<LoadState<T>> = remember(key) { mutableStateOf(LoadState.Loading) }

    LaunchedEffect(key, attempt) {
        state.value = LoadState.Loading
        state.value = try {
            val loaded = fetch()
            LoadState.Ready(loaded.value, loaded.fromCache)
        } catch (cancelled: CancellationException) {
            throw cancelled
        } catch (error: ApiException) {
            LoadState.Failed(error.message)
        } catch (error: Exception) {
            LoadState.Failed("Could not reach CampBuddy. Check your connection and try again.")
        }
    }

    return Loader(state) { attempt++ }
}
