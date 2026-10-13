package club.campbuddy.shared.domain

import club.campbuddy.shared.model.DiscoveryCard

/** What discovery matching does on the phone: the fixed tag list and who suits whom. */
object Discovery {
    /** The curated tags the server accepts (never free text). */
    val TAGS = listOf(
        "developer", "designer", "content creator", "site builder",
        "community organizer", "marketer", "business owner", "blogger",
        "translator", "speaker", "student", "mentor",
    )

    const val MAX_TAGS = 5

    val REPORT_REASONS = listOf(
        "spam" to "Spam or selling",
        "abusive" to "Rude or abusive",
        "impersonation" to "Pretending to be someone",
        "other" to "Something else",
    )

    fun shared(mine: List<String>, theirs: List<String>): List<String> = theirs.filter { t -> mine.any { it.equals(t, ignoreCase = true) } }

    /**
     * Best matches first (most shared tags, then the people with a name), skipping this phone's own
     * profile and everyone it hid. People with nothing in common stay at the end, never hidden.
     */
    fun rank(cards: List<DiscoveryCard>, myTags: List<String>, myId: String?, hidden: Set<String>): List<DiscoveryCard> =
        cards.filter { it.discoveryId != myId && it.discoveryId !in hidden }
            .sortedWith(compareByDescending<DiscoveryCard> { shared(myTags, it.fields.tags).size }.thenByDescending { it.name != null })

    /** The line under a reply box when it is not possible to send. */
    fun whyNot(reason: String?, max: Int): String? = when (reason) {
        null -> null
        "closed" -> "Messages open during the event."
        "limit" -> "You have sent all $max of today's messages. Swap Camp Cards to keep talking."
        "waiting" -> "Wait for their reply before sending another."
        "not_mutual" -> "You can message each other once you have both waved."
        else -> "You cannot send a message right now."
    }
}
