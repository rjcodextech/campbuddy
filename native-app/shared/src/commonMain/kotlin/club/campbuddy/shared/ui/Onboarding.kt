package club.campbuddy.shared.ui

import androidx.compose.foundation.layout.Arrangement
import androidx.compose.foundation.border
import androidx.compose.foundation.clickable
import androidx.compose.foundation.layout.Box
import androidx.compose.foundation.layout.fillMaxHeight
import androidx.compose.foundation.layout.Spacer
import androidx.compose.foundation.layout.heightIn
import androidx.compose.foundation.layout.size
import androidx.compose.foundation.layout.wrapContentHeight
import androidx.compose.foundation.shape.CircleShape
import androidx.compose.ui.draw.clip
import androidx.compose.ui.draw.shadow
import androidx.compose.ui.semantics.Role
import androidx.compose.ui.semantics.selected
import androidx.compose.ui.semantics.semantics
import androidx.compose.ui.text.font.FontWeight
import androidx.compose.ui.text.style.TextAlign
import androidx.compose.ui.text.style.TextDecoration
import androidx.compose.ui.unit.sp
import androidx.compose.foundation.layout.Column
import androidx.compose.foundation.layout.ExperimentalLayoutApi
import androidx.compose.foundation.layout.FlowRow
import androidx.compose.foundation.layout.Row
import androidx.compose.foundation.layout.fillMaxWidth
import androidx.compose.foundation.layout.height
import androidx.compose.foundation.layout.padding
import androidx.compose.foundation.layout.width
import androidx.compose.foundation.background
import androidx.compose.foundation.text.KeyboardOptions
import androidx.compose.foundation.shape.RoundedCornerShape
import androidx.compose.material3.FilterChip
import androidx.compose.material3.MaterialTheme
import androidx.compose.material3.OutlinedTextField
import androidx.compose.material3.Text
import androidx.compose.material3.TextButton
import androidx.compose.runtime.Composable
import androidx.compose.runtime.getValue
import androidx.compose.runtime.mutableStateOf
import androidx.compose.runtime.remember
import androidx.compose.runtime.setValue
import androidx.compose.ui.Alignment
import androidx.compose.ui.Modifier
import androidx.compose.ui.unit.dp
import club.campbuddy.shared.data.Profile
import club.campbuddy.shared.domain.Onboarding
import kotlinx.datetime.Clock

/**
 * "Make CampBuddy yours": one question at a time, every step skippable, Back and Next, and at the end
 * a plain list of what the answers will do. The answers stay on this phone (same fields as the website).
 */
@Composable
fun OnboardingCard(onDone: () -> Unit) {
    val env = LocalEnv.current
    val words = LocalWords.current
    val brand = LocalBrand.current
    var started by remember { mutableStateOf(false) }
    var step by remember { mutableStateOf(0) }
    var done by remember { mutableStateOf<Profile?>(null) }
    var draft by remember { mutableStateOf(Profile()) }
    val last = Onboarding.STEPS - 1

    fun finish(skipAll: Boolean = false) {
        val profile = Onboarding.clean(draft).copy(completedAt = Clock.System.now().toEpochMilliseconds())
        env.user.saveProfile(profile)
        if (skipAll) onDone() else done = profile
    }

    // The website's card: paper surface, thin border, a 3 dp brand-coloured top edge.
    val shape = RoundedCornerShape(brand.radius.dp)
    Column(
        Modifier.fillMaxWidth().shadow(3.dp, shape, clip = false).clip(shape).background(brand.surface).border(1.dp, brand.line, shape),
    ) {
        Box(Modifier.fillMaxWidth().height(3.dp).background(brand.primary))
        Column(Modifier.padding(20.dp), verticalArrangement = Arrangement.spacedBy(12.dp)) {
            when {
                done != null -> {
                    StepIcon("party", gold = true)
                    Text("You are set", style = MaterialTheme.typography.titleLarge)
                    Column(verticalArrangement = Arrangement.spacedBy(6.dp)) {
                        Onboarding.summary(done!!).forEach {
                            Row(horizontalArrangement = Arrangement.spacedBy(8.dp)) {
                                Text("•", style = MaterialTheme.typography.bodyMedium.copy(color = brand.primaryText, fontWeight = FontWeight.Bold))
                                Text(it, style = MaterialTheme.typography.bodyMedium, modifier = Modifier.weight(1f))
                            }
                        }
                    }
                    PrimaryButton(words("picker.choose") + " →", onDone, Modifier.fillMaxWidth())
                }
                !started -> {
                    Text(
                        "Optional · 30 seconds",
                        style = MaterialTheme.typography.labelMedium.copy(color = brand.primaryText, fontWeight = FontWeight.Bold),
                        modifier = Modifier.background(brand.peach, RoundedCornerShape(50)).padding(horizontal = 10.dp, vertical = 2.dp),
                    )
                    Text(words("onboarding.title"), style = MaterialTheme.typography.titleLarge)
                    Text(words("onboarding.lead"), style = MaterialTheme.typography.bodyMedium.copy(color = brand.muted))
                    Row(Modifier.fillMaxWidth().padding(top = 4.dp), horizontalArrangement = Arrangement.spacedBy(12.dp)) {
                        QuietButton(words("onboarding.not_now"), { finish(skipAll = true) }, Modifier.weight(1f))
                        PrimaryButton(words("onboarding.start") + " →", { started = true }, Modifier.weight(1f))
                    }
                }
                else -> {
                    Row(Modifier.fillMaxWidth(), horizontalArrangement = Arrangement.SpaceBetween, verticalAlignment = Alignment.CenterVertically) {
                        Row(horizontalArrangement = Arrangement.spacedBy(8.dp), verticalAlignment = Alignment.CenterVertically) {
                            repeat(Onboarding.STEPS) { index ->
                                val color = if (index == step) brand.primary else if (index < step) brand.accent else brand.line
                                Box(Modifier.width(if (index == step) 36.dp else 22.dp).height(6.dp).background(color, RoundedCornerShape(3.dp)))
                            }
                        }
                        Text("Step ${step + 1} of ${Onboarding.STEPS}", style = MaterialTheme.typography.labelMedium.copy(color = brand.muted, fontWeight = FontWeight.Bold))
                    }
                    StepBody(step, draft) { draft = it }
                    Row(Modifier.fillMaxWidth().padding(top = 4.dp), verticalAlignment = Alignment.CenterVertically, horizontalArrangement = Arrangement.spacedBy(8.dp)) {
                        if (step > 0) QuietButton("‹  " + words("common.back"), { step-- })
                        Text(
                            words("common.skip"),
                            style = MaterialTheme.typography.bodyMedium.copy(color = brand.muted, textDecoration = TextDecoration.Underline),
                            modifier = Modifier.heightIn(min = 44.dp).clickable(role = Role.Button) { if (step == last) finish() else step++ }.padding(horizontal = 8.dp).wrapContentHeight(Alignment.CenterVertically),
                        )
                        Spacer(Modifier.weight(1f))
                        PrimaryButton(if (step == last) "Finish" else words("common.next") + "  ›", { if (step == last) finish() else step++ })
                    }
                    Text(
                        "Skip all questions",
                        style = MaterialTheme.typography.bodyMedium.copy(color = brand.muted, textDecoration = TextDecoration.Underline),
                        textAlign = TextAlign.Center,
                        modifier = Modifier.fillMaxWidth().heightIn(min = 44.dp).clickable(role = Role.Button) { finish(skipAll = true) }.wrapContentHeight(Alignment.CenterVertically),
                    )
                }
            }
        }
    }
}

/** The round peach icon above each question (gold on the last screen). */
@Composable
private fun StepIcon(name: String, gold: Boolean = false) {
    val brand = LocalBrand.current
    Box(Modifier.size(48.dp).background(if (gold) brand.accent else brand.peach, CircleShape), contentAlignment = Alignment.Center) {
        LineIcon(name, if (gold) brand.ink else brand.primary, size = 24.dp)
    }
}

@OptIn(ExperimentalLayoutApi::class)
@Composable
private fun StepBody(step: Int, draft: Profile, change: (Profile) -> Unit) {
    val brand = LocalBrand.current
    val shape = RoundedCornerShape(brand.radius.dp)
    val question = MaterialTheme.typography.titleMedium.copy(fontSize = 18.sp, fontWeight = FontWeight.Bold)

    when (step) {
        0 -> Choice("sprout", "Is this your first WordCamp?", "First-timers get beginner-friendly talks flagged and a short start-here guide.", listOf(Triple("yes", "Yes, first one!", "sprout"), Triple("no", "No, I've been before", "compass")), draft.firstWordCamp) { change(draft.copy(firstWordCamp = it)) }
        1 -> Column(verticalArrangement = Arrangement.spacedBy(8.dp)) {
            StepIcon("sparkles")
            Text("What describes you?", style = question)
            Text("We use this to suggest talks, Contributor Day teams and people to meet.", style = MaterialTheme.typography.bodyMedium.copy(color = brand.muted))
            FlowRow(horizontalArrangement = Arrangement.spacedBy(8.dp), verticalArrangement = Arrangement.spacedBy(8.dp), modifier = Modifier.padding(top = 4.dp)) {
                Onboarding.TAGS.forEach { tag ->
                    ChoicePill(tag.replaceFirstChar { it.uppercase() }, selected = tag in draft.interests) {
                        change(draft.copy(interests = Onboarding.toggleTag(draft.interests, tag)))
                    }
                }
            }
            Text("${draft.interests.size} of ${Onboarding.MAX_TAGS} picked", style = MaterialTheme.typography.bodyMedium.copy(color = brand.muted, fontWeight = FontWeight.Medium))
        }
        2 -> Choice("wrench", "Joining Contributor Day?", "A hands-on day improving WordPress. Beginners welcome. We will set up the Contribute page for you.", listOf(Triple("yes", "Yes", "wrench"), Triple("no", "Not this time", "coffee")), draft.attendingContributorDay) { change(draft.copy(attendingContributorDay = it)) }
        3 -> Column(verticalArrangement = Arrangement.spacedBy(8.dp)) {
            StepIcon("users")
            Text("Who would you like to meet?", style = question)
            Text("It pre-fills your attendee profile and Camp Card. Nothing is shared unless you join discovery.", style = MaterialTheme.typography.bodyMedium.copy(color = brand.muted))
            OutlinedTextField(draft.whoToMeet.orEmpty(), { change(draft.copy(whoToMeet = it.take(255))) }, Modifier.fillMaxWidth(), label = { Text("Looking to meet") }, placeholder = { Text("e.g. other plugin developers") }, singleLine = true, shape = shape)
            OutlinedTextField(draft.profession.orEmpty(), { change(draft.copy(profession = it.take(100))) }, Modifier.fillMaxWidth(), label = { Text("Your profession") }, placeholder = { Text("e.g. WordPress Engineer") }, singleLine = true, shape = shape)
        }
        else -> Column(verticalArrangement = Arrangement.spacedBy(8.dp)) {
            StepIcon("link")
            Text("Your WordPress.org name", style = question)
            Text("So people can find your profile from your Camp Card. Pasting the profile link works too.", style = MaterialTheme.typography.bodyMedium.copy(color = brand.muted))
            OutlinedTextField(draft.wporg.orEmpty(), { change(draft.copy(wporg = it.take(120))) }, Modifier.fillMaxWidth(), label = { Text("WordPress.org username") }, placeholder = { Text("e.g. yourname") }, singleLine = true, keyboardOptions = KeyboardOptions.Default, shape = shape)
        }
    }
}

/** A question with two big square answers side by side, like the website's. */
@Composable
private fun Choice(icon: String, title: String, why: String, options: List<Triple<String, String, String>>, selected: String?, pick: (String?) -> Unit) {
    val brand = LocalBrand.current
    val shape = RoundedCornerShape(brand.radius.dp)
    Column(verticalArrangement = Arrangement.spacedBy(8.dp)) {
        StepIcon(icon)
        Text(title, style = MaterialTheme.typography.titleMedium.copy(fontSize = 18.sp, fontWeight = FontWeight.Bold))
        Text(why, style = MaterialTheme.typography.bodyMedium.copy(color = brand.muted))
        Row(Modifier.fillMaxWidth().height(androidx.compose.foundation.layout.IntrinsicSize.Min).padding(top = 4.dp), horizontalArrangement = Arrangement.spacedBy(12.dp)) {
            options.forEach { (value, label, art) ->
                val on = selected == value
                Column(
                    Modifier.weight(1f).fillMaxHeight().heightIn(min = 96.dp)
                        .background(if (on) brand.peach else brand.surface, shape)
                        .border(if (on) 2.dp else 1.dp, if (on) brand.primary else brand.line, shape)
                        .clickable(role = Role.Button) { pick(if (on) null else value) }
                        .semantics { this.selected = on }
                        .padding(horizontal = 12.dp, vertical = 16.dp),
                    horizontalAlignment = Alignment.CenterHorizontally, verticalArrangement = Arrangement.Center,
                ) {
                    LineIcon(art, brand.primary, size = 26.dp)
                    Text(label, style = MaterialTheme.typography.labelLarge.copy(fontWeight = FontWeight.Bold), textAlign = TextAlign.Center, modifier = Modifier.padding(top = 8.dp))
                }
            }
        }
    }
}
