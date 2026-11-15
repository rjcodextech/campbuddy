package club.campbuddy.shared.ui

import androidx.compose.runtime.Composable
import androidx.compose.runtime.getValue
import androidx.compose.runtime.mutableStateOf
import androidx.compose.runtime.remember
import androidx.compose.runtime.setValue
import androidx.compose.ui.Modifier
import androidx.compose.ui.layout.ContentScale
import coil3.compose.AsyncImage

/** The website's own address for a path like /storage/branding/27/logo.png (the API lives at <site>/api/v1). */
internal fun absoluteUrl(apiBase: String, url: String): String {
    val trimmed = url.trim()
    if (trimmed.startsWith("data:") || trimmed.startsWith("content:") || trimmed.startsWith("file:")) {
        return trimmed
    }
    val full = when {
        trimmed.startsWith("//") -> "https:$trimmed"
        trimmed.startsWith("http://") || trimmed.startsWith("https://") -> trimmed
        else -> apiBase.substringBefore("/api/").trimEnd('/') + (if (trimmed.startsWith("/")) trimmed else "/$trimmed")
    }
    return if (full.startsWith("http://") &&
        !full.startsWith("http://localhost") &&
        !full.startsWith("http://127.0.0.1") &&
        !full.startsWith("http://10.0.2.2")
    ) {
        "https://" + full.substring(7)
    } else {
        full
    }
}

/**
 * A picture from the website (an event logo, a sponsor, a speaker). While it loads, or when there is none or it
 * cannot be fetched, [fallback] is drawn, so a screen never shows a broken-image hole.
 */
@Composable
fun RemoteImage(url: String?, modifier: Modifier = Modifier, contentScale: ContentScale = ContentScale.Fit, fallback: @Composable () -> Unit) {
    val env = LocalEnv.current
    var failed by remember(url) { mutableStateOf(false) }

    if (url.isNullOrBlank() || failed) {
        fallback()
    } else {
        AsyncImage(
            model = absoluteUrl(env.deps.apiBase, url),
            contentDescription = null,
            modifier = modifier,
            contentScale = contentScale,
            onError = { failed = true },
        )
    }
}
