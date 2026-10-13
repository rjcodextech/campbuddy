package club.campbuddy.shared.ui

import androidx.compose.foundation.layout.Arrangement
import androidx.compose.foundation.layout.Column
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

    fun finish(skipAll: Boolean = false) {
        val profile = Onboarding.clean(draft).copy(completedAt = Clock.System.now().toEpochMilliseconds())
        env.user.saveProfile(profile)
        if (skipAll) onDone() else done = profile
    }

    Card {
        Column(verticalArrangement = Arrangement.spacedBy(10.dp)) {
            when {
                done != null -> {
                    Text("You are set", style = MaterialTheme.typography.titleLarge)
                    Onboarding.summary(done!!).forEach { Text("✓ $it", style = MaterialTheme.typography.bodyMedium) }
                    PrimaryButton(words("picker.choose"), onDone, Modifier.fillMaxWidth())
                }
                !started -> {
                    Text(words("onboarding.title"), style = MaterialTheme.typography.titleLarge)
                    Text(words("onboarding.lead"), style = MaterialTheme.typography.bodyMedium)
                    Row(horizontalArrangement = Arrangement.spacedBy(8.dp)) {
                        QuietButton(words("onboarding.not_now"), { finish(skipAll = true) })
                        PrimaryButton(words("onboarding.start"), { started = true })
                    }
                }
                else -> {
                    Row(horizontalArrangement = Arrangement.spacedBy(6.dp), verticalAlignment = Alignment.CenterVertically) {
                        repeat(Onboarding.STEPS) { index ->
                            Row(Modifier.width(if (index == step) 36.dp else 22.dp).height(6.dp).background(if (index <= step) brand.primary else brand.line, RoundedCornerShape(3.dp))) {}
                        }
                        Text("Step ${step + 1} of ${Onboarding.STEPS}", style = MaterialTheme.typography.labelMedium, modifier = Modifier.padding(start = 8.dp))
                    }
                    StepBody(step, draft) { draft = it }
                    Row(horizontalArrangement = Arrangement.spacedBy(8.dp), verticalAlignment = Alignment.CenterVertically) {
                        if (step > 0) QuietButton(words("common.back"), { step-- })
                        LinkButton(words("common.skip"), { if (step == Onboarding.STEPS - 1) finish() else step++ })
                        PrimaryButton(if (step == Onboarding.STEPS - 1) "Finish" else words("common.next"), { if (step == Onboarding.STEPS - 1) finish() else step++ })
                    }
                    LinkButton("Skip all questions", { finish(skipAll = true) }, Modifier.fillMaxWidth())
                }
            }
        }
    }
}

@Composable
private fun StepBody(step: Int, draft: Profile, change: (Profile) -> Unit) {
    val brand = LocalBrand.current
    val shape = RoundedCornerShape(brand.radius.dp)

    when (step) {
        0 -> Choice("Is this your first WordCamp?", "First-timers get beginner-friendly talks flagged and a short start-here guide.", listOf("yes" to "Yes, first one!", "no" to "No, I have been before"), draft.firstWordCamp) { change(draft.copy(firstWordCamp = it)) }
        1 -> Column(verticalArrangement = Arrangement.spacedBy(8.dp)) {
            Text("What describes you?", style = MaterialTheme.typography.titleMedium)
            Text("We use this to suggest talks, Contributor Day teams and people to meet.", style = MaterialTheme.typography.bodySmall)
            // A wrapping set of chips: rows of three.
            Onboarding.TAGS.chunked(3).forEach { rowTags ->
                Row(horizontalArrangement = Arrangement.spacedBy(6.dp)) {
                    rowTags.forEach { tag ->
                        CbChip(
                            selected = tag in draft.interests,
                            onClick = { change(draft.copy(interests = Onboarding.toggleTag(draft.interests, tag))) },
                            label = { Text(tag.replaceFirstChar { it.uppercase() }, maxLines = 1) },
                        )
                    }
                }
            }
            Text("${draft.interests.size} of ${Onboarding.MAX_TAGS} picked", style = MaterialTheme.typography.labelMedium)
        }
        2 -> Choice("Joining Contributor Day?", "A hands-on day improving WordPress. Beginners welcome.", listOf("yes" to "Yes", "no" to "Not this time"), draft.attendingContributorDay) { change(draft.copy(attendingContributorDay = it)) }
        3 -> Column(verticalArrangement = Arrangement.spacedBy(8.dp)) {
            Text("Who would you like to meet?", style = MaterialTheme.typography.titleMedium)
            Text("It pre-fills your profile and Camp Card. Nothing is shared unless you join discovery.", style = MaterialTheme.typography.bodySmall)
            OutlinedTextField(draft.whoToMeet.orEmpty(), { change(draft.copy(whoToMeet = it.take(255))) }, Modifier.fillMaxWidth(), label = { Text("Looking to meet") }, singleLine = true, shape = shape)
            OutlinedTextField(draft.profession.orEmpty(), { change(draft.copy(profession = it.take(100))) }, Modifier.fillMaxWidth(), label = { Text("Your profession") }, singleLine = true, shape = shape)
        }
        else -> Column(verticalArrangement = Arrangement.spacedBy(8.dp)) {
            Text("Your WordPress.org name", style = MaterialTheme.typography.titleMedium)
            Text("So people can find your profile from your Camp Card. Pasting the profile link works too.", style = MaterialTheme.typography.bodySmall)
            OutlinedTextField(draft.wporg.orEmpty(), { change(draft.copy(wporg = it.take(120))) }, Modifier.fillMaxWidth(), label = { Text("WordPress.org username") }, singleLine = true, keyboardOptions = KeyboardOptions.Default, shape = shape)
        }
    }
}

@Composable
private fun Choice(title: String, why: String, options: List<Pair<String, String>>, selected: String?, pick: (String?) -> Unit) {
    Column(verticalArrangement = Arrangement.spacedBy(8.dp)) {
        Text(title, style = MaterialTheme.typography.titleMedium)
        Text(why, style = MaterialTheme.typography.bodySmall)
        Row(horizontalArrangement = Arrangement.spacedBy(8.dp)) {
            options.forEach { (value, label) ->
                CbChip(selected = selected == value, onClick = { pick(if (selected == value) null else value) }, label = { Text(label) })
            }
        }
    }
}
