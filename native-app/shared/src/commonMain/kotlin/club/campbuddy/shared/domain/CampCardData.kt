package club.campbuddy.shared.domain

/** What a Camp Card holds and the website's rules for it (camp-card.js): links, handles, interests, what shows on the card. */
class CampCard(
    val name: String = "",
    val role: String = "",
    val company: String = "",
    val city: String = "",
    val interests: List<String> = emptyList(),
    val askMeAbout: String = "",
    val linkedin: String = "",
    val website: String = "",
    val wordpressOrg: String = "",
    val twitter: String = "",
    val qrField: String? = null,
    val visible: Set<String> = DEFAULT_VISIBLE,
) {
    fun value(field: String): String = when (field) {
        "linkedin" -> linkedin; "website" -> website; "wordpressOrg" -> wordpressOrg; "twitter" -> twitter
        "role" -> role; "company" -> company; "city" -> city; "askMeAbout" -> askMeAbout; else -> ""
    }

    /** The link a field gives (an X handle becomes its address). */
    fun link(field: String): String? = resolveLink(field, value(field))

    /** The field whose link the QR opens: the chosen one, else the first filled in. */
    val qr: String? get() = (listOfNotNull(qrField) + LINK_FIELDS).firstOrNull { link(it) != null }

    val qrLink: String? get() = qr?.let { link(it) }

    /** A card can be made once it has a name and a link for its QR. */
    val complete: Boolean get() = name.isNotBlank() && qrLink != null

    /** Stored on the phone as plain strings (interests one per line, what shows as a comma list). */
    fun toMap(): Map<String, String> = mapOf(
        "name" to name, "role" to role, "company" to company, "city" to city, "interests" to interests.joinToString("\n"), "askMeAbout" to askMeAbout,
        "linkedin" to linkedin, "website" to website, "wordpressOrg" to wordpressOrg, "twitter" to twitter,
        "qrField" to qrField.orEmpty(), "visible" to visible.joinToString(","),
    ).filterValues { it.isNotBlank() }

    companion object {
        val LINK_FIELDS = listOf("linkedin", "website", "wordpressOrg", "twitter")
        val DEFAULT_VISIBLE = setOf("role", "interests")
        val VISIBLE_FIELDS = listOf("role" to "Role", "company" to "Company", "city" to "City", "interests" to "Interests", "askMeAbout" to "Ask me about")
        const val MAX_INTERESTS = 8
        val SUGGESTED_INTERESTS = listOf("Gutenberg", "Block themes", "WooCommerce", "Performance", "Accessibility", "Design", "Security", "SEO", "Plugins", "Community")

        fun fromMap(map: Map<String, String>, seed: CampCard = CampCard()): CampCard = CampCard(
            name = map["name"] ?: seed.name, role = map["role"] ?: seed.role, company = map["company"] ?: seed.company, city = map["city"].orEmpty(),
            // Older cards kept interests as one comma-separated line.
            interests = (map["interests"] ?: "").split('\n', ',').map { it.trim() }.filter { it.isNotEmpty() }.distinct().take(MAX_INTERESTS),
            askMeAbout = map["askMeAbout"].orEmpty(),
            linkedin = map["linkedin"].orEmpty(), website = map["website"].orEmpty(),
            wordpressOrg = map["wordpressOrg"] ?: seed.wordpressOrg, twitter = map["twitter"].orEmpty(),
            qrField = map["qrField"]?.takeIf { it.isNotBlank() },
            visible = map["visible"]?.split(',')?.filter { it.isNotBlank() }?.toSet() ?: DEFAULT_VISIBLE,
        )

        fun resolveLink(field: String, value: String): String? {
            val v = value.trim()
            if (v.isEmpty()) return null
            if (field == "twitter") {
                val handle = normalizeHandle(v) ?: return null

                return "https://x.com/$handle"
            }

            return tidyLink(field, v)
        }

        /** "yoursite.com" -> "https://yoursite.com"; a bare WordPress.org username becomes its profile; anything that is not a web address is null. */
        fun tidyLink(field: String, raw: String): String? {
            val v = raw.trim()
            if (v.isEmpty()) return null
            if (field == "wordpressOrg" && Regex("[A-Za-z0-9][A-Za-z0-9._@-]*").matches(v.removePrefix("@"))) return "https://profiles.wordpress.org/${v.removePrefix("@").lowercase()}/"
            val withScheme = if (Regex("^https?://", RegexOption.IGNORE_CASE).containsMatchIn(v)) v else "https://$v"

            return withScheme.takeIf { Regex("^https?://[^\\s/]+\\.[^\\s/]{2,}(/\\S*)?$", RegexOption.IGNORE_CASE).matches(it) }
        }

        /** "@asha", "x.com/asha" and "asha" all give "asha"; anything else is null. */
        fun normalizeHandle(raw: String): String? {
            var v = raw.trim()
            Regex("^(?:https?://)?(?:www\\.)?(?:x|twitter)\\.com/@?([A-Za-z0-9_]{1,15})/?(?:[?#].*)?$", RegexOption.IGNORE_CASE).find(v)?.let { v = it.groupValues[1] }
            v = v.removePrefix("@")

            return v.takeIf { Regex("[A-Za-z0-9_]{1,15}").matches(it) }
        }
    }
}
