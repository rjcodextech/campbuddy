package club.campbuddy.shared.domain

import club.campbuddy.shared.data.Profile

/** The rules of "Make CampBuddy yours", the same as the website (profile-sync.js, onboarding.js). */
object Onboarding {
    const val STEPS = 5
    const val MAX_TAGS = 5

    /** The one list the onboarding and attendee discovery both offer (a server test keeps them equal). */
    val TAGS = listOf(
        "developer", "designer", "content creator", "site builder",
        "community organizer", "marketer", "business owner", "blogger",
        "translator", "speaker", "student", "mentor",
    )

    /** Adds or removes a tag; a sixth is refused (discovery takes at most five). */
    fun toggleTag(current: List<String>, tag: String): List<String> = when {
        tag in current -> current - tag
        current.size >= MAX_TAGS -> current
        else -> current + tag
    }

    /** "profiles.wordpress.org/jane" or a full link becomes "jane"; a bare username stays; junk becomes null. */
    fun wporgUsername(value: String?): String? {
        val v = value?.trim()?.takeIf { it.isNotEmpty() } ?: return null
        Regex("profiles\\.wordpress\\.org/([^/?#\\s]+)", RegexOption.IGNORE_CASE).find(v)?.let { return it.groupValues[1] }

        return v.takeIf { Regex("^[\\w.@-]+$").matches(it) }
    }

    /** What is saved: only known tags in order, trimmed text, a clean username. */
    fun clean(draft: Profile): Profile = draft.copy(
        interests = TAGS.filter { it in draft.interests.map { t -> t.trim().lowercase() } }.take(MAX_TAGS),
        profession = draft.profession?.trim()?.takeIf { it.isNotEmpty() },
        whoToMeet = draft.whoToMeet?.trim()?.takeIf { it.isNotEmpty() },
        wporg = wporgUsername(draft.wporg),
    )

    /** What the answers will do, in plain words (the closing screen). Never empty. */
    fun summary(profile: Profile): List<String> {
        val lines = mutableListOf<String>()
        if (profile.interests.isNotEmpty()) lines += "Talks, Contributor Day teams and people for ${profile.interests.take(3).joinToString(", ")} come first."
        when (profile.firstWordCamp) {
            "yes" -> lines += "Beginner-friendly talks are flagged and the start-here guide is ready."
            "no" -> lines += "The first-timer guide stays out of your way."
        }
        if (profile.attendingContributorDay == "yes") lines += "The Contribute screen starts with Contributor Day set up."
        if (profile.whoToMeet != null || profile.profession != null || profile.wporg != null) lines += "Your attendee profile and Camp Card start pre-filled. Nothing is shared until you choose to join."
        if (lines.isEmpty()) lines += "No problem. You can add these any time in your Camp Card."

        return lines
    }
}
