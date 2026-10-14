package club.campbuddy.shared.domain

import club.campbuddy.shared.model.GuideMoment
import club.campbuddy.shared.model.Session

/** Recognising the moments of a WordCamp day (registration, lunch, keynote...) in a real schedule: the website's moments.js. */
object Moments {
    /** True when the title contains one of the words as a whole word (plurals allowed: "breaks"; "breakfast" is not a "break"). */
    fun titleHasWord(title: String?, words: List<String>): Boolean {
        val lower = title.orEmpty().lowercase()

        return words.any { w -> Regex("(^|[^a-z])" + Regex.escape(w.lowercase()) + "(s|es)?([^a-z]|$)").containsMatchIn(lower) }
    }

    /** A talk, rather than a break, meal or activity: WordCamp marks those "custom"; with no type at all, talks have speakers. */
    fun isTalk(session: Session): Boolean = when {
        session.sessionType == "session" -> true
        session.sessionType != null -> false
        else -> session.speakerIds.isNotEmpty()
    }

    /** A moment that is not a talk ("break", "lunch") never matches a talk, so "Don't Break Your Site" is not a coffee break. */
    fun matches(match: List<String>, talk: Boolean, session: Session): Boolean {
        if (match.isEmpty()) return false
        if (!talk && isTalk(session)) return false

        return titleHasWord(session.title, match)
    }

    fun momentFor(session: Session, moments: List<GuideMoment>): GuideMoment? = moments.firstOrNull { matches(it.match, it.talk, session) }

    /** Onboarding's "what describes you" answers -> words that suggest a session is for them (home.js INTEREST_WORDS). */
    val INTEREST_WORDS = mapOf(
        "developer" to listOf("develop", "code", "coding", "block", "plugin", "api", "php", "javascript", "react", "performance", "headless", "git"),
        "designer" to listOf("design", "theme", "ux", "ui", "accessib", "css", "typograph", "colour", "color"),
        "content creator" to listOf("content", "writ", "blog", "seo", "video", "podcast", "story", "newsletter"),
        "site builder" to listOf("site", "builder", "no-code", "page", "theme", "woocommerce", "template", "pattern"),
        "community organizer" to listOf("community", "meetup", "contribut", "volunteer", "organiz", "diversity", "open source"),
        "marketer" to listOf("marketing", "seo", "social", "growth", "brand", "analytics", "email", "conversion"),
        "student" to listOf("beginner", "introduct", "getting started", "101", "first", "career", "learn", "student", "freelanc", "job"),
        "business owner" to listOf("business", "freelanc", "agency", "client", "ecommerce", "woocommerce", "pricing", "sales", "career"),
        "blogger" to listOf("blog", "writ", "content", "seo", "story", "newsletter", "audience"),
        "translator" to listOf("translat", "polyglot", "i18n", "locali", "language", "multilingual"),
        "speaker" to listOf("speak", "talk", "present", "story", "community"),
        "mentor" to listOf("mentor", "career", "teach", "learn", "community", "contribut", "beginner"),
    )

    fun interestScore(session: Session, interests: List<String>): Int {
        if (interests.isEmpty()) return 0
        val haystack = (listOf(session.title) + session.categoryNames + listOf(session.description.orEmpty())).joinToString(" ").lowercase()

        return interests.sumOf { interest ->
            val words = INTEREST_WORDS[interest] ?: listOf(interest.substringBefore(' '))
            if (words.any { haystack.contains(it) }) 1 else 0 as Int
        }
    }

    fun isBeginnerFriendly(session: Session): Boolean = session.categoryNames.any { Regex("beginner|introduct|getting started|101", RegexOption.IGNORE_CASE).containsMatchIn(it) }
}
