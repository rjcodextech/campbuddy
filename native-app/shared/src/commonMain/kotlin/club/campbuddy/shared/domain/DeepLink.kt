package club.campbuddy.shared.domain

/** A link into the app: an event, and optionally one of its tabs. [id] changes with every new link so the same one can open twice. */
class DeepLink(val slug: String, val tab: String?, val id: Int = 0) {
    companion object {
        private val TABS = setOf("my-day", "quest", "contribute", "explore", "camp-card", "memories")

        /**
         * https://campbuddy.club/event/{slug}[/{tab}] and campbuddy://event/{slug}[/{tab}] open that event.
         * Anything else (other hosts, other paths) is not ours and gives null.
         */
        fun parse(scheme: String?, host: String?, path: String?, id: Int = 0): DeepLink? {
            val parts = path.orEmpty().trim('/').split('/').filter { it.isNotEmpty() }
            val segments = when {
                scheme == "https" && (host == "campbuddy.club" || host == "www.campbuddy.club") -> parts
                scheme == "campbuddy" && host != null -> listOf(host) + parts
                else -> return null
            }
            if (segments.size < 2 || segments[0] != "event") return null
            val slug = segments[1]
            if (!Regex("[a-z0-9][a-z0-9-]{0,120}").matches(slug)) return null

            return DeepLink(slug, segments.getOrNull(2)?.takeIf { it in TABS }, id)
        }
    }
}
