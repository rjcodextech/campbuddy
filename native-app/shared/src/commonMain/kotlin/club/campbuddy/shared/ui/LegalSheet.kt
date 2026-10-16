package club.campbuddy.shared.ui

import androidx.compose.foundation.background
import androidx.compose.foundation.border
import androidx.compose.foundation.clickable
import androidx.compose.foundation.layout.Arrangement
import androidx.compose.foundation.layout.Box
import androidx.compose.foundation.layout.Column
import androidx.compose.foundation.layout.Row
import androidx.compose.foundation.layout.fillMaxWidth
import androidx.compose.foundation.layout.padding
import androidx.compose.foundation.shape.RoundedCornerShape
import androidx.compose.material3.MaterialTheme
import androidx.compose.material3.Text
import androidx.compose.runtime.Composable
import androidx.compose.ui.Alignment
import androidx.compose.ui.Modifier
import androidx.compose.ui.draw.clip
import androidx.compose.ui.text.font.FontWeight
import androidx.compose.ui.unit.dp
import androidx.compose.ui.unit.sp
import club.campbuddy.shared.model.LegalDoc

/**
 * Direct in-app native viewer for Privacy Notice and Terms of Use.
 * Renders compliance documents directly inside Compose without bouncing to an external browser.
 * Supports server-driven custom content edited from the Admin panel with offline fallback.
 */
@Composable
fun LegalSheet(
    slug: String, // "privacy" or "terms"
    onClose: () -> Unit,
) {
    val env = LocalEnv.current
    val brand = LocalBrand.current
    val loader = rememberLoader("legal:$slug") { env.repository.legal(slug) }
    val readyDoc = (loader.state.value as? LoadState.Ready)?.value

    val defaultTitle = if (slug == "privacy") "Privacy notice" else "Terms of use"
    val title = readyDoc?.title?.takeIf { it.isNotBlank() } ?: defaultTitle

    Sheet(
        title = title,
        onClose = onClose,
        actions = {
            SheetActions(
                primary = "Understood",
                onPrimary = onClose,
            )
        },
    ) {
        val doc = readyDoc
        val updated = doc?.updated?.takeIf { it.isNotBlank() } ?: "16 October 2026"

        Text(
            if (slug == "privacy") "Last updated $updated · India's DPDP Act, 2023"
            else "Last updated $updated · Standard Platform Terms",
            style = MaterialTheme.typography.labelMedium.copy(color = brand.muted),
            modifier = Modifier.padding(bottom = 4.dp),
        )

        if (doc != null && doc.hasCustomContent && !doc.content.isNullOrBlank()) {
            CustomLegalContent(doc.content)
        } else {
            if (slug == "privacy") {
                DefaultPrivacyContent(doc?.grievanceEmail?.takeIf { it.isNotBlank() } ?: GRIEVANCE_EMAIL)
            } else {
                DefaultTermsContent()
            }
        }
    }
}

@Composable
private fun CustomLegalContent(htmlOrMarkdown: String) {
    val brand = LocalBrand.current
    val paragraphs = parseContentToBlocks(htmlOrMarkdown)

    Column(verticalArrangement = Arrangement.spacedBy(10.dp)) {
        paragraphs.forEach { block ->
            when (block.type) {
                BlockType.HEADING -> {
                    Text(
                        block.text,
                        style = MaterialTheme.typography.titleMedium.copy(
                            fontWeight = FontWeight.Bold,
                            color = brand.ink,
                        ),
                        modifier = Modifier.padding(top = 8.dp),
                    )
                }
                BlockType.LIST_ITEM -> {
                    Row(
                        modifier = Modifier.fillMaxWidth().padding(start = 4.dp),
                        horizontalArrangement = Arrangement.spacedBy(8.dp),
                    ) {
                        Text("•", style = MaterialTheme.typography.bodyMedium.copy(color = brand.primary, fontWeight = FontWeight.Bold))
                        Text(block.text, style = MaterialTheme.typography.bodyMedium.copy(color = brand.ink))
                    }
                }
                BlockType.BOX -> {
                    Box(
                        Modifier.fillMaxWidth()
                            .clip(RoundedCornerShape(8.dp))
                            .background(brand.peach.copy(alpha = 0.35f))
                            .border(1.dp, brand.primary.copy(alpha = 0.2f), RoundedCornerShape(8.dp))
                            .padding(12.dp)
                    ) {
                        Text(block.text, style = MaterialTheme.typography.bodyMedium.copy(color = brand.ink))
                    }
                }
                BlockType.PARAGRAPH -> {
                    Text(
                        block.text,
                        style = MaterialTheme.typography.bodyMedium.copy(color = brand.ink, lineHeight = 20.sp),
                    )
                }
            }
        }
    }
}

private enum class BlockType { HEADING, PARAGRAPH, LIST_ITEM, BOX }
private data class ContentBlock(val type: BlockType, val text: String)

private fun parseContentToBlocks(raw: String): List<ContentBlock> {
    val clean = raw
        .replace("<br>", "\n")
        .replace("<br/>", "\n")
        .replace("<br />", "\n")

    val blocks = mutableListOf<ContentBlock>()
    val lines = clean.split("\n")

    var inBox = false
    val boxLines = mutableListOf<String>()

    for (rawLine in lines) {
        val line = rawLine.trim()
        if (line.isEmpty()) continue

        if (line.contains("class=\"legal__box\"") || line.contains("class='legal__box'")) {
            inBox = true
            continue
        }
        if (inBox && line.contains("</div>")) {
            inBox = false
            if (boxLines.isNotEmpty()) {
                blocks.add(ContentBlock(BlockType.BOX, stripHtml(boxLines.joinToString("\n"))))
                boxLines.clear()
            }
            continue
        }
        if (inBox) {
            boxLines.add(line)
            continue
        }

        val stripped = stripHtml(line)
        if (stripped.isBlank()) continue

        when {
            line.startsWith("<h1") || line.startsWith("<h2") || line.startsWith("<h3") || line.startsWith("##") -> {
                blocks.add(ContentBlock(BlockType.HEADING, stripped))
            }
            line.startsWith("<li") || line.startsWith("- ") || line.startsWith("* ") -> {
                blocks.add(ContentBlock(BlockType.LIST_ITEM, stripped.removePrefix("•").trim()))
            }
            else -> {
                blocks.add(ContentBlock(BlockType.PARAGRAPH, stripped))
            }
        }
    }

    if (boxLines.isNotEmpty()) {
        blocks.add(ContentBlock(BlockType.BOX, stripHtml(boxLines.joinToString("\n"))))
    }

    return blocks
}

private fun stripHtml(html: String): String {
    return html.replace(Regex("<[^>]*>"), " ")
        .replace("&amp;", "&")
        .replace("&quot;", "\"")
        .replace("&#039;", "'")
        .replace("&lt;", "<")
        .replace("&gt;", ">")
        .replace("&nbsp;", " ")
        .replace(Regex("\\s+"), " ")
        .trim()
}

@Composable
private fun DefaultPrivacyContent(grievanceEmail: String) {
    val brand = LocalBrand.current
    val env = LocalEnv.current

    Column(verticalArrangement = Arrangement.spacedBy(12.dp)) {
        Box(
            Modifier.fillMaxWidth()
                .clip(RoundedCornerShape(8.dp))
                .background(brand.peach.copy(alpha = 0.35f))
                .border(1.dp, brand.primary.copy(alpha = 0.2f), RoundedCornerShape(8.dp))
                .padding(12.dp)
        ) {
            Column(verticalArrangement = Arrangement.spacedBy(6.dp)) {
                Text("The short version", style = MaterialTheme.typography.titleMedium.copy(fontWeight = FontWeight.Bold, color = brand.ink))
                BulletRow("You do not need an account. Your saved sessions, quests, Camp Card and answers stay on your phone. We never receive them.")
                BulletRow("Something reaches our servers only when you choose to: join attendee discovery, share your Camp Card, send a deal form, or switch on notifications.")
                BulletRow("We do not sell your data, show ads, or build an attendee profile. Nothing you type is sent to analytics.")
                BulletRow("You can take it back at any time, inside the app or by writing to $grievanceEmail.")
            }
        }

        LegalSectionTitle("1. Who is responsible")
        Text(
            "CampBuddy is run by WP Simplified (the Data Fiduciary under the DPDP Act). CampBuddy is an independent companion for WordCamp attendees. It is not run by WordPress.org, the WordPress Foundation or any WordCamp organizing team.",
            style = MaterialTheme.typography.bodyMedium,
        )

        LegalSectionTitle("2. What we keep, why, and for how long")
        BulletRow("Saved sessions & answers: Kept on this phone only. Deleted when you clear data or uninstall.")
        BulletRow("Attendee discovery profile: Kept on the server while you choose to be discoverable. Automatically wiped about 90 days after the event or immediately upon leaving.")
        BulletRow("Deal inquiries: Passed directly to the chosen sponsor. Our server copy is purged about 90 days post-event.")
        BulletRow("Push notifications: Anonymous device token. Kept only while notifications are switched on.")

        LegalSectionTitle("3. Post-event retention choice")
        Text(
            "At the end of each WordCamp, CampBuddy confirms whether you want to keep your schedule and memories on your phone indefinitely or have them automatically cleaned up after 30 days.",
            style = MaterialTheme.typography.bodyMedium,
        )

        LegalSectionTitle("4. Children's data")
        Text(
            "CampBuddy does not track children, profile minors, or serve targeted advertising. WordCamp attendees under 18 can browse schedules without sharing personal data.",
            style = MaterialTheme.typography.bodyMedium,
        )

        LegalSectionTitle("5. Grievance redressal & rights")
        Text(
            "Under India's DPDP Act 2023, you have the right to access, rectify, or erase your data, and withdraw consent at any time.",
            style = MaterialTheme.typography.bodyMedium,
        )

        QuietButton(
            "Contact Grievance Officer ($grievanceEmail)",
            { env.deps.openUrl("mailto:$grievanceEmail?subject=CampBuddy%20Data%20Request") },
            Modifier.fillMaxWidth().padding(top = 4.dp),
        )
    }
}

@Composable
private fun DefaultTermsContent() {
    Column(verticalArrangement = Arrangement.spacedBy(12.dp)) {
        LegalSectionTitle("1. Welcome to CampBuddy")
        Text(
            "CampBuddy is an independent companion app for WordCamp conference attendees. By using CampBuddy on the web or mobile app, you agree to these terms.",
            style = MaterialTheme.typography.bodyMedium,
        )

        LegalSectionTitle("2. Offline-first & local data")
        Text(
            "CampBuddy is designed to operate locally on your device. Your notes, bookmarks, and day plans are stored on your phone. You are responsible for keeping local backups if desired.",
            style = MaterialTheme.typography.bodyMedium,
        )

        LegalSectionTitle("3. Code of conduct & community etiquette")
        Text(
            "Attendees using attendee discovery and wave messaging agree to treat fellow community members with dignity, kindness, and respect in accordance with the WordPress Community Code of Conduct. Harassment or spam is strictly prohibited and grounds for immediate removal.",
            style = MaterialTheme.typography.bodyMedium,
        )

        LegalSectionTitle("4. Sponsor deals & third parties")
        Text(
            "Discounts and free steals provided by WordPress ecosystem sponsors are offered directly by their makers. CampBuddy provides access without warranty or endorsement.",
            style = MaterialTheme.typography.bodyMedium,
        )

        LegalSectionTitle("5. Independent project")
        Text(
            "CampBuddy is built independently for the WordPress community and is not affiliated with the WordPress Foundation or Automattic Inc.",
            style = MaterialTheme.typography.bodyMedium,
        )
    }
}

@Composable
private fun LegalSectionTitle(title: String) {
    val brand = LocalBrand.current
    Text(
        title,
        style = MaterialTheme.typography.titleMedium.copy(
            fontWeight = FontWeight.Bold,
            color = brand.ink,
        ),
        modifier = Modifier.padding(top = 4.dp),
    )
}

@Composable
private fun BulletRow(text: String) {
    val brand = LocalBrand.current
    Row(
        modifier = Modifier.fillMaxWidth().padding(start = 2.dp),
        horizontalArrangement = Arrangement.spacedBy(8.dp),
        verticalAlignment = Alignment.Top,
    ) {
        Text("•", style = MaterialTheme.typography.bodyMedium.copy(color = brand.primary, fontWeight = FontWeight.Bold))
        Text(text, style = MaterialTheme.typography.bodyMedium.copy(color = brand.ink))
    }
}
