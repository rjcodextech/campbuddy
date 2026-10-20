package club.campbuddy.shared.ui

import androidx.compose.foundation.background
import androidx.compose.foundation.border
import androidx.compose.foundation.clickable
import androidx.compose.foundation.layout.Arrangement
import androidx.compose.foundation.layout.Box
import androidx.compose.foundation.layout.Column
import androidx.compose.foundation.layout.ColumnScope
import androidx.compose.foundation.layout.Row
import androidx.compose.foundation.layout.Spacer
import androidx.compose.foundation.layout.fillMaxWidth
import androidx.compose.foundation.layout.height
import androidx.compose.foundation.layout.padding
import androidx.compose.foundation.layout.size
import androidx.compose.foundation.layout.width
import androidx.compose.foundation.shape.CircleShape
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
import androidx.compose.ui.draw.clip
import androidx.compose.ui.graphics.Color
import androidx.compose.ui.text.font.FontWeight
import androidx.compose.ui.unit.dp
import androidx.compose.ui.unit.sp
import club.campbuddy.shared.model.LegalDoc

/**
 * Direct in-app native viewer for Privacy Notice and Terms of Use.
 * Renders compliance documents directly inside Compose with seamless switching,
 * DPDP Act 2023 visual cards, and offline fallback.
 */
@Composable
fun LegalSheet(
    slug: String, // "privacy" or "terms"
    onClose: () -> Unit,
) {
    val env = LocalEnv.current
    val brand = LocalBrand.current
    var activeSlug by remember(slug) { mutableStateOf(slug) }
    val loader = rememberLoader("legal:$activeSlug") { env.repository.legal(activeSlug) }
    val readyDoc = (loader.state.value as? LoadState.Ready)?.value

    val defaultTitle = if (activeSlug == "privacy") "Privacy notice" else "Terms of use"
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

        Column(verticalArrangement = Arrangement.spacedBy(14.dp)) {
            // Document Tab Switcher (Fast Toggle between Privacy & Terms)
            Row(
                modifier = Modifier
                    .fillMaxWidth()
                    .clip(RoundedCornerShape(999.dp))
                    .background(brand.ink.copy(alpha = 0.05f))
                    .border(1.dp, brand.line, RoundedCornerShape(999.dp))
                    .padding(3.dp),
                horizontalArrangement = Arrangement.spacedBy(4.dp),
            ) {
                Box(
                    modifier = Modifier
                        .weight(1f)
                        .clip(RoundedCornerShape(999.dp))
                        .background(if (activeSlug == "privacy") brand.primary else Color.Transparent)
                        .clickable { activeSlug = "privacy" }
                        .padding(vertical = 8.dp),
                    contentAlignment = Alignment.Center,
                ) {
                    Text(
                        "Privacy notice",
                        style = MaterialTheme.typography.labelMedium.copy(
                            fontWeight = FontWeight.Bold,
                            color = if (activeSlug == "privacy") Color.White else brand.muted,
                        ),
                    )
                }

                Box(
                    modifier = Modifier
                        .weight(1f)
                        .clip(RoundedCornerShape(999.dp))
                        .background(if (activeSlug == "terms") brand.primary else Color.Transparent)
                        .clickable { activeSlug = "terms" }
                        .padding(vertical = 8.dp),
                    contentAlignment = Alignment.Center,
                ) {
                    Text(
                        "Terms of use",
                        style = MaterialTheme.typography.labelMedium.copy(
                            fontWeight = FontWeight.Bold,
                            color = if (activeSlug == "terms") Color.White else brand.muted,
                        ),
                    )
                }
            }

            // Compliance Badge & Meta Subtitle
            Row(
                verticalAlignment = Alignment.CenterVertically,
                horizontalArrangement = Arrangement.spacedBy(8.dp),
            ) {
                if (activeSlug == "privacy") {
                    Box(
                        modifier = Modifier
                            .clip(RoundedCornerShape(999.dp))
                            .background(brand.success.copy(alpha = 0.12f))
                            .border(1.dp, brand.success.copy(alpha = 0.25f), RoundedCornerShape(999.dp))
                            .padding(horizontal = 10.dp, vertical = 4.dp),
                    ) {
                        Text(
                            "🛡️ DPDP Act 2023 Compliant",
                            style = MaterialTheme.typography.labelSmall.copy(
                                fontWeight = FontWeight.Bold,
                                color = brand.success,
                            ),
                        )
                    }
                } else {
                    Box(
                        modifier = Modifier
                            .clip(RoundedCornerShape(999.dp))
                            .background(brand.peach.copy(alpha = 0.5f))
                            .border(1.dp, brand.primary.copy(alpha = 0.2f), RoundedCornerShape(999.dp))
                            .padding(horizontal = 10.dp, vertical = 4.dp),
                    ) {
                        Text(
                            "📜 Platform Terms & Code of Conduct",
                            style = MaterialTheme.typography.labelSmall.copy(
                                fontWeight = FontWeight.Bold,
                                color = brand.ink,
                            ),
                        )
                    }
                }

                Text(
                    "Last updated $updated",
                    style = MaterialTheme.typography.labelSmall.copy(color = brand.muted),
                )
            }

            // Custom Content (Admin-edited) or Rich Default Layout
            if (doc != null && doc.hasCustomContent && !doc.content.isNullOrBlank()) {
                CustomLegalContent(
                    htmlOrMarkdown = doc.content,
                    fallbackEmail = doc.grievanceEmail?.takeIf { it.isNotBlank() } ?: GRIEVANCE_EMAIL,
                )
            } else {
                if (activeSlug == "privacy") {
                    DefaultPrivacyContent(doc?.grievanceEmail?.takeIf { it.isNotBlank() } ?: GRIEVANCE_EMAIL)
                } else {
                    DefaultTermsContent()
                }
            }
        }
    }
}

@Composable
private fun DefaultPrivacyContent(grievanceEmail: String) {
    val brand = LocalBrand.current
    val env = LocalEnv.current

    Column(verticalArrangement = Arrangement.spacedBy(14.dp)) {
        // The Short Version Highlight Card
        Box(
            Modifier.fillMaxWidth()
                .clip(RoundedCornerShape(14.dp))
                .background(brand.peach.copy(alpha = 0.45f))
                .border(1.dp, brand.primary.copy(alpha = 0.25f), RoundedCornerShape(14.dp))
                .padding(14.dp),
        ) {
            Column(verticalArrangement = Arrangement.spacedBy(8.dp)) {
                Row(
                    modifier = Modifier.fillMaxWidth(),
                    horizontalArrangement = Arrangement.SpaceBetween,
                    verticalAlignment = Alignment.CenterVertically,
                ) {
                    Text(
                        "The short version",
                        style = MaterialTheme.typography.titleMedium.copy(fontWeight = FontWeight.Bold, color = brand.ink),
                    )
                    Box(
                        modifier = Modifier
                            .clip(RoundedCornerShape(999.dp))
                            .background(brand.primary)
                            .padding(horizontal = 8.dp, vertical = 2.dp),
                    ) {
                        Text(
                            "KEY HIGHLIGHTS",
                            style = MaterialTheme.typography.labelSmall.copy(
                                fontWeight = FontWeight.Bold,
                                color = Color.White,
                                fontSize = 9.sp,
                            ),
                        )
                    }
                }
                CheckBullet("You do not need an account. Your saved sessions, quests, notes, Camp Card and answers stay on your phone. We never receive them.")
                CheckBullet("Something reaches our servers only when you choose to: join attendee discovery, share your Camp Card, send a deal form, or switch on notifications.")
                CheckBullet("We do not sell your data, show ads, or build a profile of you. Nothing you type is sent to analytics.")
                CheckBullet("You can take it back at any time, inside the app or by writing to $grievanceEmail.")
            }
        }

        // Section 1: Who is responsible
        LegalCard {
            LegalSectionHeader("1", "Who is responsible")
            Text(
                "CampBuddy is run by WP Simplified (the Data Fiduciary under India's DPDP Act, 2023). CampBuddy is an independent companion for WordCamp attendees. It is not run by WordPress.org, the WordPress Foundation or any WordCamp organizing team.",
                style = MaterialTheme.typography.bodyMedium.copy(color = brand.ink, lineHeight = 21.sp),
            )
            Text(
                "Contact: $grievanceEmail",
                style = MaterialTheme.typography.bodyMedium.copy(color = brand.primary, fontWeight = FontWeight.Bold),
            )
        }

        // Section 2: What we keep, why, and for how long
        LegalCard {
            LegalSectionHeader("2", "What we keep, why, and for how long")
            Text(
                "We collect only what is strictly necessary to run features you opt into:",
                style = MaterialTheme.typography.bodyMedium.copy(color = brand.muted),
            )

            DataCategoryItem(
                title = "Your own data (Sessions, Notes, Camp Card, Quests)",
                badge = "Phone only · Never sent to server",
                isLocal = true,
                why = "To give you your day plan, schedule, and personal event memories.",
                retention = "Only on this phone. Deleted when you clear data or uninstall.",
            )

            DataCategoryItem(
                title = "Attendee Discovery Profile (Optional)",
                badge = "Opt-in Server · 90-day auto purge",
                isLocal = false,
                why = "So people with shared interests can find each other at the event.",
                retention = "Wiped ~90 days post-event or instantly when you leave discovery.",
            )

            DataCategoryItem(
                title = "Shared Camp Card (Optional)",
                badge = "Opt-in Server · 90-day auto purge",
                isLocal = false,
                why = "Shown to attendees who view your card in Explore → People.",
                retention = "Removed when toggled off, or wiped ~90 days post-event.",
            )

            DataCategoryItem(
                title = "Deal inquiries (Sponsor forms)",
                badge = "Passed directly to sponsor",
                isLocal = false,
                why = "So the sponsor of that deal can send you the offer details.",
                retention = "Our server copy is wiped ~90 days after the event.",
            )

            DataCategoryItem(
                title = "Push notifications (Reminders & updates)",
                badge = "Anonymous device token",
                isLocal = false,
                why = "To send reminders for sessions you bookmarked and venue alerts.",
                retention = "Kept only while active. Wiped ~90 days post-event.",
            )
        }

        // Section 3: Post-event retention choice
        LegalCard {
            LegalSectionHeader("3", "Post-event retention choice")
            Text(
                "At the end of each WordCamp, CampBuddy confirms whether you want to keep your schedule and memories on your phone indefinitely or have them automatically cleaned up after 30 days.",
                style = MaterialTheme.typography.bodyMedium.copy(color = brand.ink, lineHeight = 21.sp),
            )
        }

        // Section 4: Your Rights under DPDP Act 2023
        LegalCard {
            LegalSectionHeader("4", "Your rights under DPDP Act")
            Text(
                "Under the DPDP Act 2023, you have full control over your personal data:",
                style = MaterialTheme.typography.bodyMedium.copy(color = brand.muted),
            )

            RightCard("Right to Access & Know", "Know what data we hold and receive a full copy.")
            RightCard("Right to Correction", "Correct, update, or complete any inaccurate details.")
            RightCard("Right to Erasure", "Permanently erase and wipe your data from servers.")
            RightCard("Withdraw Consent", "Opt out at any time as easily as you opted in.")
            RightCard("Grievance Redressal", "File a complaint and have it answered within 7 days.")
            RightCard("Right to Nominate", "Appoint someone to manage your rights if incapacitated.")

            Text(
                "Instant controls inside this app:",
                style = MaterialTheme.typography.titleSmall.copy(fontWeight = FontWeight.Bold, color = brand.ink),
                modifier = Modifier.padding(top = 4.dp),
            )
            BulletRow("My Day → Your data: Export all data or wipe your phone copy.")
            BulletRow("Explore → People: Leave discovery or toggle Camp Card sharing off.")
        }

        // Section 5: Grievance Redressal Officer Contact Card
        Box(
            Modifier.fillMaxWidth()
                .clip(RoundedCornerShape(14.dp))
                .background(brand.surface)
                .border(2.dp, brand.peach, RoundedCornerShape(14.dp))
                .padding(14.dp),
        ) {
            Column(verticalArrangement = Arrangement.spacedBy(8.dp)) {
                Text(
                    "Grievance Redressal Officer",
                    style = MaterialTheme.typography.titleMedium.copy(fontWeight = FontWeight.Bold, color = brand.ink),
                )
                Text(
                    "Have a data request, grievance, or privacy question? Our Grievance Officer guarantees a response within 7 working days.",
                    style = MaterialTheme.typography.bodySmall.copy(color = brand.muted, lineHeight = 18.sp),
                )
                Text(
                    "If unsatisfied, you may appeal to the Data Protection Board of India.",
                    style = MaterialTheme.typography.labelSmall.copy(color = brand.muted),
                )
                PrimaryButton(
                    text = "Contact Grievance Officer ($grievanceEmail)",
                    onClick = { env.deps.openUrl("mailto:$grievanceEmail?subject=CampBuddy%20Data%20Request") },
                    modifier = Modifier.fillMaxWidth().padding(top = 4.dp),
                )
            }
        }

        // Section 6: Hindi Summary (सारांश)
        Box(
            Modifier.fillMaxWidth()
                .clip(RoundedCornerShape(14.dp))
                .background(brand.peach.copy(alpha = 0.25f))
                .border(1.dp, brand.accent.copy(alpha = 0.35f), RoundedCornerShape(14.dp))
                .padding(14.dp),
        ) {
            Column(verticalArrangement = Arrangement.spacedBy(6.dp)) {
                Row(
                    verticalAlignment = Alignment.CenterVertically,
                    horizontalArrangement = Arrangement.spacedBy(6.dp),
                ) {
                    Text("🇮🇳", fontSize = 16.sp)
                    Text(
                        "सारांश (Hindi Summary)",
                        style = MaterialTheme.typography.titleSmall.copy(fontWeight = FontWeight.Bold, color = brand.ink),
                    )
                }
                Text(
                    "CampBuddy के लिए आपको खाता नहीं बनाना पड़ता। आपके सेव किए सत्र, नोट्स, Camp Card और जवाब सिर्फ़ आपके फ़ोन में रहते हैं। हमारे सर्वर तक डेटा तभी पहुँचता है जब आप ख़ुद डिस्कवरी चालू करें, कार्ड शेयर करें, या नोटिफ़िकेशन ऑन करें। हम डेटा बेचते नहीं, विज्ञापन नहीं दिखाते। कार्यक्रम के 90 दिन बाद सर्वर डेटा अपने-आप मिटा दिया जाता है।",
                    style = MaterialTheme.typography.bodySmall.copy(color = brand.ink, lineHeight = 19.sp),
                )
            }
        }
    }
}

@Composable
private fun DefaultTermsContent() {
    val brand = LocalBrand.current
    val env = LocalEnv.current

    Column(verticalArrangement = Arrangement.spacedBy(14.dp)) {
        // Welcome / Agreement Card
        Box(
            Modifier.fillMaxWidth()
                .clip(RoundedCornerShape(14.dp))
                .background(brand.peach.copy(alpha = 0.45f))
                .border(1.dp, brand.primary.copy(alpha = 0.25f), RoundedCornerShape(14.dp))
                .padding(14.dp),
        ) {
            Column(verticalArrangement = Arrangement.spacedBy(6.dp)) {
                Row(
                    modifier = Modifier.fillMaxWidth(),
                    horizontalArrangement = Arrangement.SpaceBetween,
                    verticalAlignment = Alignment.CenterVertically,
                ) {
                    Text(
                        "Welcome to CampBuddy",
                        style = MaterialTheme.typography.titleMedium.copy(fontWeight = FontWeight.Bold, color = brand.ink),
                    )
                    Box(
                        modifier = Modifier
                            .clip(RoundedCornerShape(999.dp))
                            .background(brand.ink)
                            .padding(horizontal = 8.dp, vertical = 2.dp),
                    ) {
                        Text(
                            "AGREEMENT",
                            style = MaterialTheme.typography.labelSmall.copy(
                                fontWeight = FontWeight.Bold,
                                color = Color.White,
                                fontSize = 9.sp,
                            ),
                        )
                    }
                }
                Text(
                    "CampBuddy is a free companion for WordCamp attendees, run by WP Simplified. By using the app or website, you agree to these terms.",
                    style = MaterialTheme.typography.bodyMedium.copy(color = brand.ink, lineHeight = 20.sp),
                )
            }
        }

        // 1. What CampBuddy is
        LegalCard {
            LegalSectionHeader("1", "What CampBuddy is")
            Text(
                "CampBuddy shows schedules, speakers, sponsors, deals and details of WordCamp events. It is an independent community project and is not run, sponsored, or endorsed by WordPress.org, the WordPress Foundation, or Automattic Inc.",
                style = MaterialTheme.typography.bodyMedium.copy(color = brand.ink, lineHeight = 21.sp),
            )
        }

        // 2. Information can change
        LegalCard {
            LegalSectionHeader("2", "Information can change")
            Text(
                "Times, rooms, speakers, and venue details are synced from the event and can change. While we keep them updated, check with local organizers for critical matters like travel or ticketing.",
                style = MaterialTheme.typography.bodyMedium.copy(color = brand.ink, lineHeight = 21.sp),
            )
        }

        // 3. Deals & Sponsors
        LegalCard {
            LegalSectionHeader("3", "Deals, free tools & sponsors")
            Text(
                "Offers, promo codes, and steals belong directly to the companies that make them. CampBuddy does not sell, endorse, or guarantee them.",
                style = MaterialTheme.typography.bodyMedium.copy(color = brand.ink, lineHeight = 21.sp),
            )
        }

        // 4. Meeting people & Code of Conduct
        LegalCard {
            LegalSectionHeader("4", "Meeting people & Code of Conduct")
            Text(
                "Attendee discovery, waves, and shared Camp Cards are optional. Treat fellow community members kindly:",
                style = MaterialTheme.typography.bodyMedium.copy(color = brand.muted),
            )
            BulletRow("Do not harass, spam, impersonate or deceive anyone, and do not share other people's information.")
            BulletRow("Do not use CampBuddy to collect attendee details for marketing or commercial scraping.")
            BulletRow("Meet in public conference areas and abide by the official WordCamp Code of Conduct.")
            Text(
                "Profiles violating these rules may be suspended or removed immediately.",
                style = MaterialTheme.typography.bodySmall.copy(color = brand.danger, fontWeight = FontWeight.Bold),
                modifier = Modifier.padding(top = 4.dp),
            )
        }

        // 5. Using the service
        LegalCard {
            LegalSectionHeader("5", "Using the service")
            Text(
                "Do not attempt to compromise, overload, or bulk-scrape CampBuddy by automated means. We reserve the right to modify or restrict service to maintain stability.",
                style = MaterialTheme.typography.bodyMedium.copy(color = brand.ink, lineHeight = 21.sp),
            )
        }

        // 6. No warranty, limited liability
        LegalCard {
            LegalSectionHeader("6", "No warranty, limited liability")
            Text(
                "CampBuddy is provided 'as is' and completely free of charge. To the extent allowed by law, we are not liable for losses from reliance on schedule data or third-party sponsor offerings.",
                style = MaterialTheme.typography.bodyMedium.copy(color = brand.ink, lineHeight = 21.sp),
            )
        }

        // 7. Governing Law
        LegalCard {
            LegalSectionHeader("7", "Governing Law")
            Text(
                "These terms and any disputes are governed exclusively by the laws of India.",
                style = MaterialTheme.typography.bodyMedium.copy(color = brand.ink, lineHeight = 21.sp),
            )
        }

        // 8. Contact
        Box(
            Modifier.fillMaxWidth()
                .clip(RoundedCornerShape(14.dp))
                .background(brand.surface)
                .border(1.dp, brand.line, RoundedCornerShape(14.dp))
                .padding(14.dp),
        ) {
            Column(verticalArrangement = Arrangement.spacedBy(8.dp)) {
                Text(
                    "Questions about these terms?",
                    style = MaterialTheme.typography.titleMedium.copy(fontWeight = FontWeight.Bold, color = brand.ink),
                )
                Text(
                    "Reach out to our team directly with any inquiries or feedback.",
                    style = MaterialTheme.typography.bodySmall.copy(color = brand.muted),
                )
                QuietButton(
                    text = "Contact Team (hello@wpsimplified.in)",
                    onClick = { env.deps.openUrl("mailto:hello@wpsimplified.in?subject=CampBuddy%20Terms") },
                    modifier = Modifier.fillMaxWidth(),
                )
            }
        }
    }
}

// ---------------------------------------------------------------------------
// Reusable UI Helpers for Legal Cards & Content
// ---------------------------------------------------------------------------

@Composable
private fun LegalCard(content: @Composable ColumnScope.() -> Unit) {
    val brand = LocalBrand.current
    Box(
        Modifier.fillMaxWidth()
            .clip(RoundedCornerShape(14.dp))
            .background(brand.surface)
            .border(1.dp, brand.line, RoundedCornerShape(14.dp))
            .padding(14.dp),
    ) {
        Column(verticalArrangement = Arrangement.spacedBy(8.dp), content = content)
    }
}

@Composable
private fun LegalSectionHeader(number: String, title: String) {
    val brand = LocalBrand.current
    Row(
        verticalAlignment = Alignment.CenterVertically,
        horizontalArrangement = Arrangement.spacedBy(8.dp),
        modifier = Modifier.padding(bottom = 2.dp),
    ) {
        Box(
            modifier = Modifier
                .size(24.dp)
                .clip(CircleShape)
                .background(brand.peach),
            contentAlignment = Alignment.Center,
        ) {
            Text(
                number,
                style = MaterialTheme.typography.labelSmall.copy(
                    fontWeight = FontWeight.Bold,
                    color = brand.primary,
                    fontSize = 11.sp,
                ),
            )
        }
        Text(
            title,
            style = MaterialTheme.typography.titleMedium.copy(
                fontWeight = FontWeight.Bold,
                color = brand.ink,
            ),
        )
    }
}

@Composable
private fun CheckBullet(text: String) {
    val brand = LocalBrand.current
    Row(
        modifier = Modifier.fillMaxWidth().padding(start = 2.dp),
        horizontalArrangement = Arrangement.spacedBy(8.dp),
        verticalAlignment = Alignment.Top,
    ) {
        Text("✓", style = MaterialTheme.typography.bodyMedium.copy(color = brand.success, fontWeight = FontWeight.Bold))
        Text(text, style = MaterialTheme.typography.bodyMedium.copy(color = brand.ink, lineHeight = 20.sp))
    }
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
        Text(text, style = MaterialTheme.typography.bodyMedium.copy(color = brand.ink, lineHeight = 20.sp))
    }
}

@Composable
private fun DataCategoryItem(
    title: String,
    badge: String,
    isLocal: Boolean,
    why: String,
    retention: String,
) {
    val brand = LocalBrand.current
    Box(
        modifier = Modifier
            .fillMaxWidth()
            .clip(RoundedCornerShape(10.dp))
            .background(brand.peach.copy(alpha = 0.2f))
            .border(1.dp, brand.line, RoundedCornerShape(10.dp))
            .padding(10.dp),
    ) {
        Column(verticalArrangement = Arrangement.spacedBy(4.dp)) {
            Row(
                modifier = Modifier.fillMaxWidth(),
                horizontalArrangement = Arrangement.SpaceBetween,
                verticalAlignment = Alignment.CenterVertically,
            ) {
                Text(
                    title,
                    style = MaterialTheme.typography.labelMedium.copy(fontWeight = FontWeight.Bold, color = brand.ink),
                    modifier = Modifier.weight(1f),
                )
                Box(
                    modifier = Modifier
                        .clip(RoundedCornerShape(999.dp))
                        .background(if (isLocal) brand.success.copy(alpha = 0.15f) else brand.primary.copy(alpha = 0.12f))
                        .padding(horizontal = 6.dp, vertical = 2.dp),
                ) {
                    Text(
                        badge,
                        style = MaterialTheme.typography.labelSmall.copy(
                            fontWeight = FontWeight.Bold,
                            color = if (isLocal) brand.success else brand.primary,
                            fontSize = 9.sp,
                        ),
                    )
                }
            }
            Text("Why: $why", style = MaterialTheme.typography.bodySmall.copy(color = brand.muted, fontSize = 12.sp))
            Text("Retention: $retention", style = MaterialTheme.typography.bodySmall.copy(color = brand.ink, fontSize = 12.sp, fontWeight = FontWeight.Medium))
        }
    }
}

@Composable
private fun RightCard(title: String, description: String) {
    val brand = LocalBrand.current
    Box(
        modifier = Modifier
            .fillMaxWidth()
            .clip(RoundedCornerShape(8.dp))
            .background(brand.surface)
            .border(1.dp, brand.line, RoundedCornerShape(8.dp))
            .padding(horizontal = 10.dp, vertical = 8.dp),
    ) {
        Column(verticalArrangement = Arrangement.spacedBy(2.dp)) {
            Text(title, style = MaterialTheme.typography.labelMedium.copy(fontWeight = FontWeight.Bold, color = brand.ink))
            Text(description, style = MaterialTheme.typography.bodySmall.copy(color = brand.muted, fontSize = 12.sp))
        }
    }
}

@Composable
private fun CustomLegalContent(
    htmlOrMarkdown: String,
    fallbackEmail: String = GRIEVANCE_EMAIL,
) {
    val brand = LocalBrand.current
    val env = LocalEnv.current
    val doc = remember(htmlOrMarkdown, fallbackEmail) { parseHtmlToLegalDoc(htmlOrMarkdown, fallbackEmail) }

    Column(verticalArrangement = Arrangement.spacedBy(14.dp)) {
        // 1. Key Highlights Card (if present)
        doc.highlightBox?.let { box ->
            Box(
                Modifier.fillMaxWidth()
                    .clip(RoundedCornerShape(14.dp))
                    .background(brand.peach.copy(alpha = 0.45f))
                    .border(1.dp, brand.primary.copy(alpha = 0.25f), RoundedCornerShape(14.dp))
                    .padding(14.dp),
            ) {
                Column(verticalArrangement = Arrangement.spacedBy(8.dp)) {
                    Row(
                        modifier = Modifier.fillMaxWidth(),
                        horizontalArrangement = Arrangement.SpaceBetween,
                        verticalAlignment = Alignment.CenterVertically,
                    ) {
                        Text(
                            box.title,
                            style = MaterialTheme.typography.titleMedium.copy(fontWeight = FontWeight.Bold, color = brand.ink),
                        )
                        Box(
                            modifier = Modifier
                                .clip(RoundedCornerShape(999.dp))
                                .background(brand.primary)
                                .padding(horizontal = 8.dp, vertical = 2.dp),
                        ) {
                            Text(
                                if (box.title.contains("Terms", ignoreCase = true)) "AT A GLANCE" else "KEY HIGHLIGHTS",
                                style = MaterialTheme.typography.labelSmall.copy(
                                    fontWeight = FontWeight.Bold,
                                    color = Color.White,
                                    fontSize = 9.sp,
                                ),
                            )
                        }
                    }
                    box.bullets.forEach { bullet ->
                        CheckBullet(bullet)
                    }
                }
            }
        }

        // 2. Intro Paragraphs (if any)
        doc.introParagraphs.forEach { intro ->
            LegalCard {
                Text(
                    intro,
                    style = MaterialTheme.typography.bodyMedium.copy(color = brand.ink, lineHeight = 21.sp),
                )
            }
        }

        // 3. Numbered Sections
        doc.sections.forEach { section ->
            val isHindi = section.title.contains("Hindi", ignoreCase = true) || section.title.contains("सारांश")

            if (isHindi) {
                // Hindi Summary Card
                Box(
                    Modifier.fillMaxWidth()
                        .clip(RoundedCornerShape(14.dp))
                        .background(brand.peach.copy(alpha = 0.25f))
                        .border(1.dp, brand.accent.copy(alpha = 0.35f), RoundedCornerShape(14.dp))
                        .padding(14.dp),
                ) {
                    Column(verticalArrangement = Arrangement.spacedBy(6.dp)) {
                        Row(
                            verticalAlignment = Alignment.CenterVertically,
                            horizontalArrangement = Arrangement.spacedBy(6.dp),
                        ) {
                            Text("🇮🇳", fontSize = 16.sp)
                            Text(
                                section.title,
                                style = MaterialTheme.typography.titleSmall.copy(fontWeight = FontWeight.Bold, color = brand.ink),
                            )
                        }
                        section.items.forEach { item ->
                            if (item is LegalItem.Paragraph) {
                                Text(
                                    item.text,
                                    style = MaterialTheme.typography.bodySmall.copy(color = brand.ink, lineHeight = 19.sp),
                                )
                            }
                        }
                    }
                }
            } else {
                // Standard Section Card
                LegalCard {
                    if (section.number != null) {
                        LegalSectionHeader(section.number, section.title)
                    } else {
                        Text(
                            section.title,
                            style = MaterialTheme.typography.titleMedium.copy(
                                fontWeight = FontWeight.Bold,
                                color = brand.ink,
                            ),
                        )
                    }

                    section.items.forEach { item ->
                        when (item) {
                            is LegalItem.Paragraph -> {
                                Text(
                                    item.text,
                                    style = MaterialTheme.typography.bodyMedium.copy(color = brand.ink, lineHeight = 21.sp),
                                )
                            }
                            is LegalItem.Subheading -> {
                                Text(
                                    item.title,
                                    style = MaterialTheme.typography.titleSmall.copy(
                                        fontWeight = FontWeight.Bold,
                                        color = brand.ink,
                                    ),
                                    modifier = Modifier.padding(top = 4.dp),
                                )
                            }
                            is LegalItem.Bullet -> {
                                BulletRow(item.text)
                            }
                            is LegalItem.Check -> {
                                CheckBullet(item.text)
                            }
                            is LegalItem.DataTableItem -> {
                                DataCategoryItem(
                                    title = item.title,
                                    badge = item.badge,
                                    isLocal = item.isLocal,
                                    why = item.why,
                                    retention = item.retention,
                                )
                            }
                            is LegalItem.ContactAction -> {
                                QuietButton(
                                    text = item.label,
                                    onClick = { env.deps.openUrl(item.url) },
                                    modifier = Modifier.fillMaxWidth().padding(top = 2.dp),
                                )
                            }
                        }
                    }
                }
            }
        }
    }
}

private data class ParsedDoc(
    val highlightBox: HighlightBox?,
    val introParagraphs: List<String>,
    val sections: List<LegalSection>,
)

private data class HighlightBox(val title: String, val bullets: List<String>)

private data class LegalSection(
    val number: String?,
    val title: String,
    val items: List<LegalItem>,
)

private sealed interface LegalItem {
    data class Paragraph(val text: String) : LegalItem
    data class Subheading(val title: String) : LegalItem
    data class Bullet(val text: String) : LegalItem
    data class Check(val text: String) : LegalItem
    data class DataTableItem(
        val title: String,
        val badge: String,
        val isLocal: Boolean,
        val why: String,
        val retention: String,
    ) : LegalItem
    data class ContactAction(val label: String, val url: String) : LegalItem
}

private fun parseHtmlToLegalDoc(raw: String, fallbackEmail: String): ParsedDoc {
    var working = raw
        .replace("\r\n", "\n")
        .replace("\r", "\n")

    // 1. Extract Highlight Box if present
    var highlightBox: HighlightBox? = null
    val boxRegex = Regex("""<div\s+class=["'][^"']*legal[_-]+box[^"']*["']>([\s\S]*?)<\/div>""", RegexOption.IGNORE_CASE)
    val boxMatch = boxRegex.find(working)
    if (boxMatch != null) {
        val boxInner = boxMatch.groupValues[1]
        val titleMatch = Regex("""<strong>([\s\S]*?)<\/strong>""", RegexOption.IGNORE_CASE).find(boxInner)
        val title = titleMatch?.let { stripHtml(it.groupValues[1]) }?.takeIf { it.isNotBlank() } ?: "Key Highlights"

        val bulletMatches = Regex("""<li[^>]*>([\s\S]*?)<\/li>""", RegexOption.IGNORE_CASE).findAll(boxInner)
        val bullets = bulletMatches.map { stripHtml(it.groupValues[1]) }.filter { it.isNotBlank() }.toList()

        if (bullets.isNotEmpty()) {
            highlightBox = HighlightBox(title, bullets)
        }
        working = working.removeRange(boxMatch.range)
    }

    // 2. Locate all <h2> tags
    val h2Regex = Regex("""<h2[^>]*>([\s\S]*?)<\/h2>""", RegexOption.IGNORE_CASE)
    val h2Matches = h2Regex.findAll(working).toList()

    val introParagraphs = mutableListOf<String>()
    val sections = mutableListOf<LegalSection>()

    if (h2Matches.isEmpty()) {
        val items = parseSectionBody(working, fallbackEmail)
        if (items.isNotEmpty()) {
            sections.add(LegalSection(null, "Information", items))
        }
        return ParsedDoc(highlightBox, emptyList(), sections)
    }

    // Text before first <h2> is intro
    val introChunk = working.substring(0, h2Matches.first().range.first)
    val pMatches = Regex("""<p[^>]*>([\s\S]*?)<\/p>""", RegexOption.IGNORE_CASE).findAll(introChunk)
    for (p in pMatches) {
        val text = stripHtml(p.groupValues[1])
        if (text.isNotBlank()) {
            introParagraphs.add(text)
        }
    }

    // Parse each section
    for (i in h2Matches.indices) {
        val currentMatch = h2Matches[i]
        val headingRaw = currentMatch.groupValues[1]
        val headingText = stripHtml(headingRaw)

        val numMatch = Regex("""^(\d+)[\.\)]\s*(.*)""").find(headingText)
        val (num, secTitle) = if (numMatch != null) {
            numMatch.groupValues[1] to numMatch.groupValues[2].trim()
        } else {
            null to headingText
        }

        val bodyStart = currentMatch.range.last + 1
        val bodyEnd = if (i + 1 < h2Matches.size) h2Matches[i + 1].range.first else working.length
        val sectionBody = working.substring(bodyStart, bodyEnd)

        val items = parseSectionBody(sectionBody, fallbackEmail)
        sections.add(LegalSection(num, secTitle, items))
    }

    return ParsedDoc(highlightBox, introParagraphs, sections)
}

private fun parseSectionBody(body: String, fallbackEmail: String): List<LegalItem> {
    val items = mutableListOf<LegalItem>()

    val blockRegex = Regex(
        """(<table[^>]*>[\s\S]*?<\/table>|<h3[^>]*>[\s\S]*?<\/h3>|<ul[^>]*>[\s\S]*?<\/ul>|<ol[^>]*>[\s\S]*?<\/ol>|<p[^>]*>[\s\S]*?<\/p>)""",
        RegexOption.IGNORE_CASE,
    )

    val matches = blockRegex.findAll(body).toList()

    if (matches.isEmpty()) {
        val lines = body.split("\n")
        for (line in lines) {
            val stripped = stripHtml(line)
            if (stripped.isBlank()) continue
            if (stripped.startsWith("- ") || stripped.startsWith("* ")) {
                items.add(LegalItem.Bullet(stripped.substring(2).trim()))
            } else {
                items.add(LegalItem.Paragraph(stripped))
            }
        }
        return items
    }

    for (match in matches) {
        val block = match.value.trim()

        when {
            block.startsWith("<table", ignoreCase = true) -> {
                val rowMatches = Regex("""<tr[^>]*>([\s\S]*?)<\/tr>""", RegexOption.IGNORE_CASE).findAll(block)
                for (rowMatch in rowMatches) {
                    val trHtml = rowMatch.groupValues[1]
                    if (trHtml.contains("<th", ignoreCase = true)) continue

                    val (what, why, retention) = extractTableRow(trHtml)
                    if (what.isNotBlank()) {
                        val titleMatch = Regex("""<strong>([\s\S]*?)<\/strong>""", RegexOption.IGNORE_CASE).find(what)
                        val title = titleMatch?.let { stripHtml(it.groupValues[1]) }?.removeSuffix(":")?.trim()
                            ?: stripHtml(what).take(50)

                        val cleanWhy = stripHtml(why)
                        val cleanRetention = stripHtml(retention)

                        val isLocal = cleanRetention.contains("phone", ignoreCase = true) ||
                            what.contains("own data", ignoreCase = true)

                        val badge = when {
                            isLocal -> "Phone only · Never sent to server"
                            title.contains("Discovery", ignoreCase = true) -> "Opt-in Server · 90-day auto purge"
                            title.contains("Card", ignoreCase = true) -> "Opt-in Server · 90-day auto purge"
                            title.contains("Deal", ignoreCase = true) -> "Passed directly to sponsor"
                            title.contains("Notification", ignoreCase = true) -> "Anonymous device token"
                            title.contains("Feedback", ignoreCase = true) -> "Opt-in · For organizers"
                            title.contains("Public attendee", ignoreCase = true) -> "Public roster mirror"
                            title.contains("Technical", ignoreCase = true) -> "Security logs"
                            title.contains("Analytics", ignoreCase = true) -> "Website only · App has none"
                            else -> "Opt-in · Limited retention"
                        }

                        items.add(
                            LegalItem.DataTableItem(
                                title = title,
                                badge = badge,
                                isLocal = isLocal,
                                why = cleanWhy,
                                retention = cleanRetention,
                            ),
                        )
                    }
                }
            }

            block.startsWith("<h3", ignoreCase = true) -> {
                val subTitle = stripHtml(block)
                if (subTitle.isNotBlank()) {
                    items.add(LegalItem.Subheading(subTitle))
                }
            }

            block.startsWith("<ul", ignoreCase = true) || block.startsWith("<ol", ignoreCase = true) -> {
                val liMatches = Regex("""<li[^>]*>([\s\S]*?)<\/li>""", RegexOption.IGNORE_CASE).findAll(block)
                for (liMatch in liMatches) {
                    val liText = stripHtml(liMatch.groupValues[1])
                    if (liText.isNotBlank()) {
                        items.add(LegalItem.Bullet(liText))
                    }
                }
            }

            block.startsWith("<p", ignoreCase = true) -> {
                val pText = stripHtml(block)
                if (pText.isNotBlank()) {
                    items.add(LegalItem.Paragraph(pText))
                }

                val mailRegex = Regex("""href=["']mailto:([^"'?]+)(?:\?subject=([^"']*))?["']""", RegexOption.IGNORE_CASE)
                val mailMatch = mailRegex.find(block)
                if (mailMatch != null) {
                    val email = mailMatch.groupValues[1].trim()
                    val subject = mailMatch.groupValues.getOrNull(2)?.trim() ?: ""
                    val url = "mailto:$email" + (if (subject.isNotBlank()) "?subject=$subject" else "")
                    items.add(LegalItem.ContactAction("Email: $email", url))
                }
            }
        }
    }

    return items
}

private fun extractTableRow(trHtml: String): Triple<String, String, String> {
    val whatByLabel = Regex("""data-label=["'][^"']*What[^"']*["'][^>]*>([\s\S]*?)<\/td>""", RegexOption.IGNORE_CASE).find(trHtml)?.groupValues?.get(1)
    val whyByLabel = Regex("""data-label=["'][^"']*Why[^"']*["'][^>]*>([\s\S]*?)<\/td>""", RegexOption.IGNORE_CASE).find(trHtml)?.groupValues?.get(1)
    val whereByLabel = Regex("""data-label=["'][^"']*Where[^"']*["'][^>]*>([\s\S]*?)<\/td>""", RegexOption.IGNORE_CASE).find(trHtml)?.groupValues?.get(1)

    if (whatByLabel != null && whyByLabel != null) {
        return Triple(whatByLabel, whyByLabel, whereByLabel ?: "")
    }

    val tdMatches = Regex("""<td[^>]*>([\s\S]*?)<\/td>""", RegexOption.IGNORE_CASE).findAll(trHtml).map { it.groupValues[1] }.toList()
    return when {
        tdMatches.size >= 3 -> Triple(tdMatches[0], tdMatches[1], tdMatches[2])
        tdMatches.size == 2 -> Triple(tdMatches[0], tdMatches[1], "")
        tdMatches.size == 1 -> Triple(tdMatches[0], "", "")
        else -> Triple("", "", "")
    }
}

private fun stripHtml(html: String): String {
    return html
        .replace("<br>", "\n")
        .replace("<br/>", "\n")
        .replace("<br />", "\n")
        .replace(Regex("<[^>]*>"), " ")
        .replace("&amp;", "&")
        .replace("&quot;", "\"")
        .replace("&#039;", "'")
        .replace("&lt;", "<")
        .replace("&gt;", ">")
        .replace("&nbsp;", " ")
        .replace("&rarr;", "→")
        .replace("\u2192", "→")
        .replace(Regex("[ \t]+"), " ")
        .replace(Regex("\n\\s+"), "\n")
        .trim()
}
