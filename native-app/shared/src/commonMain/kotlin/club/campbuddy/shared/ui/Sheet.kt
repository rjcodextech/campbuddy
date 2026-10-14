package club.campbuddy.shared.ui

import androidx.compose.foundation.background
import androidx.compose.foundation.clickable
import androidx.compose.foundation.interaction.MutableInteractionSource
import androidx.compose.foundation.layout.Arrangement
import androidx.compose.foundation.layout.Box
import androidx.compose.foundation.layout.Column
import androidx.compose.foundation.layout.ColumnScope
import androidx.compose.foundation.layout.Row
import androidx.compose.foundation.layout.fillMaxSize
import androidx.compose.foundation.layout.fillMaxWidth
import androidx.compose.foundation.layout.height
import androidx.compose.foundation.layout.imePadding
import androidx.compose.foundation.layout.navigationBarsPadding
import androidx.compose.foundation.layout.padding
import androidx.compose.foundation.layout.size
import androidx.compose.foundation.layout.widthIn
import androidx.compose.foundation.rememberScrollState
import androidx.compose.foundation.shape.RoundedCornerShape
import androidx.compose.foundation.verticalScroll
import androidx.compose.material3.MaterialTheme
import androidx.compose.material3.Text
import androidx.compose.runtime.Composable
import androidx.compose.runtime.remember
import androidx.compose.ui.Alignment
import androidx.compose.ui.Modifier
import androidx.compose.ui.draw.clip
import androidx.compose.ui.semantics.Role
import androidx.compose.ui.unit.dp
import androidx.compose.ui.window.Dialog
import androidx.compose.ui.window.DialogProperties

/**
 * Every popup in the app, drawn like the website's: a paper-coloured sheet that rises from the bottom (16 dp top corners,
 * at most 480 dp wide), a header with a close button, the content, and a row of buttons. Replaces the stock Material
 * dialogs, which look nothing like the website.
 */
@Composable
fun Sheet(
    onClose: () -> Unit,
    head: @Composable () -> Unit,
    grab: Boolean = false,
    actions: (@Composable () -> Unit)? = null,
    content: @Composable ColumnScope.() -> Unit,
) {
    val brand = LocalBrand.current
    Dialog(onDismissRequest = onClose, properties = DialogProperties(usePlatformDefaultWidth = false, decorFitsSystemWindows = false)) {
        Box(
            Modifier.fillMaxSize().clickable(interactionSource = remember { MutableInteractionSource() }, indication = null, onClick = onClose),
            contentAlignment = Alignment.BottomCenter,
        ) {
            Column(
                Modifier.widthIn(max = 480.dp).fillMaxWidth().imePadding()
                    .clip(RoundedCornerShape(topStart = 16.dp, topEnd = 16.dp))
                    .background(brand.surface)
                    .clickable(interactionSource = remember { MutableInteractionSource() }, indication = null, onClick = {})
                    .navigationBarsPadding()
                    .padding(start = 16.dp, end = 16.dp, top = if (grab) 8.dp else 20.dp, bottom = 16.dp),
                verticalArrangement = Arrangement.spacedBy(12.dp),
            ) {
                if (grab) Box(Modifier.align(Alignment.CenterHorizontally).size(width = 40.dp, height = 5.dp).background(brand.line, RoundedCornerShape(3.dp)))
                Row(Modifier.fillMaxWidth(), horizontalArrangement = Arrangement.spacedBy(12.dp), verticalAlignment = Alignment.Top) {
                    Box(Modifier.weight(1f).padding(top = 4.dp)) { head() }
                    Box(Modifier.size(40.dp).clip(RoundedCornerShape(50)).clickable(role = Role.Button, onClickLabel = "Close", onClick = onClose), contentAlignment = Alignment.Center) {
                        LineIcon("x", brand.muted, size = 20.dp)
                    }
                }
                Column(Modifier.weight(1f, fill = false).verticalScroll(rememberScrollState()), verticalArrangement = Arrangement.spacedBy(12.dp), content = content)
                actions?.invoke()
            }
        }
    }
}

/** A sheet whose header is just a title. */
@Composable
fun Sheet(
    title: String,
    onClose: () -> Unit,
    actions: (@Composable () -> Unit)? = null,
    content: @Composable ColumnScope.() -> Unit,
) = Sheet(onClose, head = { Text(title, style = MaterialTheme.typography.titleLarge) }, actions = actions, content = content)

/** The button row at the bottom of a sheet: an outline button on the left, the main one on the right (the website's order). */
@Composable
fun SheetActions(primary: String, onPrimary: () -> Unit, secondary: String? = null, onSecondary: () -> Unit = {}, enabled: Boolean = true) {
    Row(Modifier.fillMaxWidth(), horizontalArrangement = Arrangement.spacedBy(12.dp), verticalAlignment = Alignment.CenterVertically) {
        if (secondary != null) QuietButton(secondary, onSecondary, Modifier.weight(1f))
        PrimaryButton(primary, onPrimary, Modifier.weight(1f), enabled = enabled)
    }
}
