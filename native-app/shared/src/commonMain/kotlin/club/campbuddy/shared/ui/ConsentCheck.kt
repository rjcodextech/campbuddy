package club.campbuddy.shared.ui

import androidx.compose.foundation.background
import androidx.compose.foundation.border
import androidx.compose.foundation.clickable
import androidx.compose.foundation.layout.Arrangement
import androidx.compose.foundation.layout.Box
import androidx.compose.foundation.layout.Column
import androidx.compose.foundation.layout.Row
import androidx.compose.foundation.layout.fillMaxWidth
import androidx.compose.foundation.layout.heightIn
import androidx.compose.foundation.layout.padding
import androidx.compose.foundation.layout.size
import androidx.compose.foundation.shape.RoundedCornerShape
import androidx.compose.material3.MaterialTheme
import androidx.compose.material3.Text
import androidx.compose.runtime.Composable
import androidx.compose.runtime.getValue
import androidx.compose.runtime.mutableStateOf
import androidx.compose.runtime.remember
import androidx.compose.runtime.setValue
import androidx.compose.ui.Alignment
import androidx.compose.ui.Modifier
import androidx.compose.ui.graphics.Color
import androidx.compose.ui.semantics.Role
import androidx.compose.ui.semantics.contentDescription
import androidx.compose.ui.semantics.semantics
import androidx.compose.ui.semantics.stateDescription
import androidx.compose.ui.unit.dp

/** The words of the consent tick — the same as the website's (attendee/partials/consent-check.blade.php). */
fun consentWords(what: String): String =
    "I agree to share $what, as the Privacy notice explains, and I'm 18 or older (or my parent or guardian agrees). I can leave and delete it any time."

/**
 * The consent tick before anything is shared (DPDP Act 2023 s.6; s.9 for under-18s). The server needs it
 * (App\Support\Consent) and keeps a record with the notice version. One row: box, words, and the notice.
 */
@Composable
fun ConsentCheck(what: String, checked: Boolean, onChange: (Boolean) -> Unit) {
    val brand = LocalBrand.current
    var notice by remember { mutableStateOf(false) }
    val shape = Radii.input
    Column(verticalArrangement = Arrangement.spacedBy(2.dp)) {
        Row(
            Modifier.fillMaxWidth().heightIn(min = 44.dp)
                .background(if (checked) brand.background else brand.surface, shape)
                .border(1.dp, if (checked) brand.primary else brand.line, shape)
                .clickable(role = Role.Checkbox) { onChange(!checked) }
                .semantics { stateDescription = if (checked) "Ticked" else "Not ticked"; contentDescription = consentWords(what) }
                .padding(12.dp),
            horizontalArrangement = Arrangement.spacedBy(12.dp),
            verticalAlignment = Alignment.Top,
        ) {
            Box(
                Modifier.padding(top = 2.dp).size(20.dp)
                    .background(if (checked) brand.primary else Color.White, RoundedCornerShape(5.dp))
                    .border(1.5.dp, if (checked) brand.primary else brand.muted, RoundedCornerShape(5.dp)),
                contentAlignment = Alignment.Center,
            ) { if (checked) Text("✓", style = MaterialTheme.typography.labelMedium.copy(color = Color.White)) }
            Text(consentWords(what), style = MaterialTheme.typography.bodyMedium.copy(color = brand.ink))
        }
        LinkButton("Read the Privacy notice", { notice = true })
    }
    if (notice) LegalSheet(slug = "privacy", onClose = { notice = false })
}
