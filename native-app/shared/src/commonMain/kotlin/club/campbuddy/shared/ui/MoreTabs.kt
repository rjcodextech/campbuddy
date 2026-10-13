package club.campbuddy.shared.ui

import androidx.compose.foundation.background
import androidx.compose.foundation.border
import androidx.compose.foundation.clickable
import androidx.compose.foundation.layout.Arrangement
import androidx.compose.foundation.layout.Box
import androidx.compose.foundation.layout.Column
import androidx.compose.foundation.layout.PaddingValues
import androidx.compose.foundation.layout.Row
import androidx.compose.foundation.layout.fillMaxSize
import androidx.compose.foundation.layout.fillMaxWidth
import androidx.compose.foundation.layout.height
import androidx.compose.foundation.layout.padding
import androidx.compose.foundation.layout.size
import androidx.compose.foundation.layout.statusBarsPadding
import androidx.compose.foundation.lazy.LazyColumn
import androidx.compose.foundation.lazy.items
import androidx.compose.foundation.shape.RoundedCornerShape
import androidx.compose.material3.FilterChip
import androidx.compose.material3.LinearProgressIndicator
import androidx.compose.material3.MaterialTheme
import androidx.compose.material3.OutlinedTextField
import androidx.compose.material3.Text
import androidx.compose.runtime.Composable
import androidx.compose.runtime.getValue
import androidx.compose.runtime.mutableStateOf
import androidx.compose.runtime.remember
import androidx.compose.runtime.saveable.rememberSaveable
import androidx.compose.runtime.setValue
import androidx.compose.ui.Alignment
import androidx.compose.ui.Modifier
import androidx.compose.ui.platform.LocalClipboardManager
import androidx.compose.ui.semantics.Role
import androidx.compose.ui.semantics.contentDescription
import androidx.compose.ui.semantics.role
import androidx.compose.ui.semantics.semantics
import androidx.compose.ui.text.AnnotatedString
import androidx.compose.ui.text.input.KeyboardType
import androidx.compose.ui.unit.dp
import androidx.compose.foundation.text.KeyboardOptions
import club.campbuddy.shared.data.Profile

/** A list screen that loads, shows a saved copy when offline, and can retry. */
@Composable
private fun <T> Listing(
    title: String,
    loader: Loader<List<T>>,
    emptyText: String,
    header: (@Composable () -> Unit)? = null,
    row: @Composable (T) -> Unit,
) {
    val state by loader.state

    LazyColumn(Modifier.fillMaxSize(), contentPadding = PaddingValues(16.dp), verticalArrangement = Arrangement.spacedBy(10.dp)) {
        if (title.isNotBlank()) item { Text(title, style = MaterialTheme.typography.headlineSmall) }
        if (header != null) item { header() }
        when (val s = state) {
            LoadState.Loading -> item { Loading() }
            is LoadState.Failed -> item { ErrorState(s.message, loader.reload) }
            is LoadState.Ready -> {
                if (s.fromCache) item { OfflineNote() }
                if (s.value.isEmpty()) item { Text(emptyText, style = MaterialTheme.typography.bodyMedium) }
                items(s.value.size) { index -> row(s.value[index]) }
            }
        }
    }
}

// ---- Quest -----------------------------------------------------------------------------------------

@Composable
fun QuestTab(slug: String) {
    val env = LocalEnv.current
    val words = LocalWords.current
    val brand = LocalBrand.current
    val loader = rememberLoader("quests:$slug") { env.repository.quests(slug) }
    val done = env.user.completedQuests(slug)
    val total = (loader.state.value as? LoadState.Ready)?.value?.size ?: 0

    Listing(words("tabs.quest"), loader, "No quests yet.", header = {
        if (total > 0) {
            Column(verticalArrangement = Arrangement.spacedBy(6.dp)) {
                Text("${done.size} of $total done", style = MaterialTheme.typography.labelMedium)
                LinearProgressIndicator(progress = { if (total == 0) 0f else done.size.toFloat() / total }, color = brand.accent, trackColor = brand.line, modifier = Modifier.fillMaxWidth().height(8.dp))
            }
        }
    }) { quest ->
        val checked = quest.id in done
        Card(onClick = { env.user.toggleQuest(slug, quest.id) }) {
            Row(horizontalArrangement = Arrangement.spacedBy(12.dp), verticalAlignment = Alignment.Top, modifier = Modifier.semantics { role = Role.Checkbox; contentDescription = quest.title + if (checked) ", done" else "" }) {
                Box(Modifier.size(24.dp).background(if (checked) brand.primary else brand.surface, RoundedCornerShape(6.dp)).border(1.5.dp, if (checked) brand.primary else brand.muted, RoundedCornerShape(6.dp)), contentAlignment = Alignment.Center) {
                    if (checked) Text("✓", style = MaterialTheme.typography.labelMedium.copy(color = androidx.compose.ui.graphics.Color.White))
                }
                Column(Modifier.weight(1f)) {
                    Text(quest.title, style = MaterialTheme.typography.titleMedium)
                    quest.description?.takeIf { it.isNotBlank() }?.let { Text(it, style = MaterialTheme.typography.bodySmall) }
                }
            }
        }
    }
}

// ---- Contribute ----------------------------------------------------------------------------------------

@Composable
fun ContributeTab(slug: String) {
    val env = LocalEnv.current
    val words = LocalWords.current
    val loader = rememberLoader("tables:$slug") { env.repository.tables(slug) }
    val profile = env.user.profile

    Listing(words("tabs.contribute"), loader, "Contributor Day tables are not announced yet.", header = {
        Card {
            Column(verticalArrangement = Arrangement.spacedBy(8.dp)) {
                Text("Joining Contributor Day?", style = MaterialTheme.typography.titleMedium)
                Text("A hands-on day improving WordPress. Beginners welcome.", style = MaterialTheme.typography.bodyMedium)
                Row(horizontalArrangement = Arrangement.spacedBy(8.dp)) {
                    listOf("yes" to "Yes", "no" to "Not this time").forEach { (value, label) ->
                        CbChip(
                            selected = profile.attendingContributorDay == value,
                            onClick = { env.user.saveProfile(profile.copy(attendingContributorDay = if (profile.attendingContributorDay == value) null else value)) },
                            label = { Text(label) },
                        )
                    }
                }
            }
        }
    }) { table ->
        Card {
            Column(verticalArrangement = Arrangement.spacedBy(4.dp)) {
                Text(table.name.ifBlank { table.team ?: "Table" }, style = MaterialTheme.typography.titleMedium)
                if (table.place.isNotBlank()) Text(table.place, style = MaterialTheme.typography.bodyMedium)
                if (table.leads.isNotEmpty()) Text("Led by " + table.leads.joinToString(", "), style = MaterialTheme.typography.bodySmall)
                table.note?.takeIf { it.isNotBlank() }?.let { Text(it, style = MaterialTheme.typography.bodySmall) }
            }
        }
    }
}

// ---- Explore: people, sponsors, deals, free steals ---------------------------------------------------------------

@Composable
fun ExploreTab(slug: String) {
    val words = LocalWords.current
    var section by rememberSaveable(slug) { mutableStateOf("people") }

    Column(Modifier.fillMaxSize()) {
        Column(Modifier.padding(start = 16.dp, end = 16.dp, top = 16.dp)) {
            Text(words("tabs.explore"), style = MaterialTheme.typography.headlineSmall)
            Row(horizontalArrangement = Arrangement.spacedBy(8.dp), modifier = Modifier.padding(top = 8.dp)) {
                listOf("people" to "People", "sponsors" to "Sponsors", "deals" to "Deals", "steals" to "Free steals").forEach { (key, label) ->
                    CbChip(selected = section == key, onClick = { section = key }, label = { Text(label) })
                }
            }
        }
        Box(Modifier.weight(1f)) {
            when (section) {
                "people" -> PeopleList(slug)
                "sponsors" -> SponsorList(slug)
                "deals" -> DealList(slug)
                else -> StealList(slug)
            }
        }
    }
}

@Composable
private fun PeopleList(slug: String) {
    val env = LocalEnv.current
    val brand = LocalBrand.current
    val loader = rememberLoader("roster:$slug") { env.repository.roster(slug) }
    val state by loader.state
    var query by rememberSaveable { mutableStateOf("") }
    var role by rememberSaveable { mutableStateOf("") }

    LazyColumn(Modifier.fillMaxSize(), contentPadding = PaddingValues(16.dp), verticalArrangement = Arrangement.spacedBy(8.dp)) {
        when (val s = state) {
            LoadState.Loading -> item { Loading() }
            is LoadState.Failed -> item { ErrorState(s.message, loader.reload) }
            is LoadState.Ready -> {
                if (s.fromCache) item { OfflineNote() }
                val roles = s.value.flatMap { it.roles }.distinct().sorted()
                item {
                    Column(verticalArrangement = Arrangement.spacedBy(8.dp)) {
                        Text("${s.value.size} attendees", style = MaterialTheme.typography.labelMedium)
                        OutlinedTextField(query, { query = it }, Modifier.fillMaxWidth(), placeholder = { Text("Search attendees") }, singleLine = true, shape = RoundedCornerShape(brand.radius.dp))
                        if (roles.isNotEmpty()) {
                            Row(horizontalArrangement = Arrangement.spacedBy(8.dp)) {
                                CbChip(selected = role == "", onClick = { role = "" }, label = { Text("All") })
                                roles.take(4).forEach { r -> CbChip(selected = role == r, onClick = { role = r }, label = { Text(r.replaceFirstChar { it.uppercase() }) }) }
                            }
                        }
                    }
                }
                val shown = s.value.filter { (query.isBlank() || it.name.contains(query, ignoreCase = true)) && (role.isBlank() || role in it.roles) }
                if (shown.isEmpty()) item { Text("Nobody matches.", style = MaterialTheme.typography.bodyMedium) }
                items(shown.take(300).size) { index ->
                    val person = shown[index]
                    Card {
                        Row(horizontalArrangement = Arrangement.spacedBy(12.dp), verticalAlignment = Alignment.CenterVertically) {
                            Column(Modifier.weight(1f)) {
                                Text(person.name, style = MaterialTheme.typography.titleMedium)
                                if (person.roles.isNotEmpty()) Text(person.roles.joinToString(" · ") { it.replaceFirstChar { c -> c.uppercase() } }, style = MaterialTheme.typography.bodySmall)
                                person.talks.firstOrNull()?.let { Text(it, style = MaterialTheme.typography.bodySmall) }
                            }
                            if (person.openToMeet) Text("Open to meet", style = MaterialTheme.typography.labelMedium.copy(color = brand.primary))
                        }
                    }
                }
            }
        }
    }
}

@Composable
private fun SponsorList(slug: String) {
    val env = LocalEnv.current
    val loader = rememberLoader("sponsors:$slug") { env.repository.sponsors(slug) }

    Listing("", loader, "No sponsors listed yet.") { sponsor ->
        Card(onClick = sponsor.website?.let { site -> { env.deps.openUrl(site) } }) {
            Column(verticalArrangement = Arrangement.spacedBy(4.dp)) {
                Text(sponsor.name, style = MaterialTheme.typography.titleMedium)
                if (sponsor.tierNames.isNotEmpty()) Text(sponsor.tierNames.joinToString(" · "), style = MaterialTheme.typography.labelMedium)
                sponsor.description?.takeIf { it.isNotBlank() }?.let { Text(it.take(220), style = MaterialTheme.typography.bodySmall) }
            }
        }
    }
}

@Composable
private fun DealList(slug: String) {
    val env = LocalEnv.current
    val brand = LocalBrand.current
    val clipboard = LocalClipboardManager.current
    var copied by remember { mutableStateOf<String?>(null) }
    val loader = rememberLoader("deals:$slug") { env.repository.deals(slug) }

    Listing("", loader, "No deals right now.") { deal ->
        Card {
            Column(verticalArrangement = Arrangement.spacedBy(6.dp)) {
                Text(deal.brand.ifBlank { deal.title }, style = MaterialTheme.typography.labelMedium)
                Text(deal.title, style = MaterialTheme.typography.titleMedium)
                deal.highlight?.takeIf { it.isNotBlank() }?.let { Text(it, style = MaterialTheme.typography.bodyMedium.copy(color = brand.primary)) }
                deal.description?.takeIf { it.isNotBlank() }?.let { Text(it, style = MaterialTheme.typography.bodySmall) }
                deal.couponCode?.takeIf { it.isNotBlank() }?.let { code ->
                    QuietButton(if (copied == code) "Copied: $code" else "Copy code $code", { clipboard.setText(AnnotatedString(code)); copied = code })
                }
                deal.url?.let { url -> PrimaryButton(deal.ctaLabel?.takeIf { it.isNotBlank() } ?: "Open deal", { env.deps.openUrl(url) }) }
                deal.terms?.takeIf { it.isNotBlank() }?.let { Text(it, style = MaterialTheme.typography.bodySmall) }
            }
        }
    }
}

@Composable
private fun StealList(slug: String) {
    val env = LocalEnv.current
    val loader = rememberLoader("steals:$slug") { env.repository.freeSteals(slug) }

    Listing("", loader, "Nothing here yet.") { steal ->
        Card(onClick = steal.url?.let { url -> { env.deps.openUrl(url) } }) {
            Column(verticalArrangement = Arrangement.spacedBy(4.dp)) {
                Text(steal.name, style = MaterialTheme.typography.titleMedium)
                listOfNotNull(steal.maker, steal.category).takeIf { it.isNotEmpty() }?.let { Text(it.joinToString(" · "), style = MaterialTheme.typography.labelMedium) }
                steal.description?.takeIf { it.isNotBlank() }?.let { Text(it, style = MaterialTheme.typography.bodySmall) }
                Text(steal.ctaLabel?.takeIf { it.isNotBlank() } ?: "Get it free", style = MaterialTheme.typography.labelMedium.copy(color = LocalBrand.current.primary))
            }
        }
    }
}

// ---- Memories ------------------------------------------------------------------------------------------------------

@Composable
fun MemoriesTab(slug: String) {
    val env = LocalEnv.current
    val words = LocalWords.current
    val loader = rememberLoader("memories:$slug") { env.repository.memories(slug) }
    val state by loader.state

    LazyColumn(Modifier.fillMaxSize(), contentPadding = PaddingValues(16.dp), verticalArrangement = Arrangement.spacedBy(10.dp)) {
        item { Text(words("tabs.memories"), style = MaterialTheme.typography.headlineSmall) }
        when (val s = state) {
            LoadState.Loading -> item { Loading() }
            is LoadState.Failed -> item { ErrorState(s.message, loader.reload) }
            is LoadState.Ready -> {
                val memories = s.value
                if (memories.photos == null && memories.videos == null) item { Text("Photos and talk recordings show up here after the event.", style = MaterialTheme.typography.bodyMedium) }
                memories.photos?.let { url -> item { Card(onClick = { env.deps.openUrl(url) }) { Text("Photo album", style = MaterialTheme.typography.titleMedium) } } }
                memories.videos?.let { url -> item { Card(onClick = { env.deps.openUrl(url) }) { Text("Talks on WordPress.tv", style = MaterialTheme.typography.titleMedium) } } }
            }
        }
    }
}

// ---- Camp Card ----------------------------------------------------------------------------------------------------------

private val CARD_FIELDS = listOf(
    "name" to "Your name",
    "role" to "Role",
    "company" to "Company or community",
    "wordpressOrg" to "WordPress.org username",
    "linkedin" to "LinkedIn link",
    "website" to "Website",
)

@Composable
fun CampCardTab() {
    val env = LocalEnv.current
    val words = LocalWords.current
    val brand = LocalBrand.current
    val saved = env.user.card()
    val profile = env.user.profile
    var fields by remember { mutableStateOf(CARD_FIELDS.associate { (key, _) -> key to (saved[key] ?: if (key == "role") profile.profession.orEmpty() else if (key == "wordpressOrg") profile.wporg.orEmpty() else "") }) }
    var message by remember { mutableStateOf<String?>(null) }

    LazyColumn(Modifier.fillMaxSize(), contentPadding = PaddingValues(16.dp), verticalArrangement = Arrangement.spacedBy(10.dp)) {
        item { Text(words("tabs.camp_card"), style = MaterialTheme.typography.headlineSmall) }
        item { Text("Your card stays on this phone until you choose to share it.", style = MaterialTheme.typography.bodyMedium) }
        item {
            Column(
                Modifier.fillMaxWidth().background(brand.primary, RoundedCornerShape(brand.radius.dp)).padding(20.dp),
                verticalArrangement = Arrangement.spacedBy(4.dp),
            ) {
                Text(fields["name"].orEmpty().ifBlank { "Your name" }, style = MaterialTheme.typography.titleLarge.copy(color = androidx.compose.ui.graphics.Color.White))
                listOf("role", "company").mapNotNull { fields[it]?.takeIf(String::isNotBlank) }.takeIf { it.isNotEmpty() }
                    ?.let { Text(it.joinToString(" · "), style = MaterialTheme.typography.bodyMedium.copy(color = brand.background)) }
                profile.interests.takeIf { it.isNotEmpty() }?.let { Text(it.joinToString(" · ") { t -> t.replaceFirstChar { c -> c.uppercase() } }, style = MaterialTheme.typography.bodySmall.copy(color = brand.accent)) }
            }
        }
        CARD_FIELDS.forEach { (key, label) ->
            item(key = key) {
                OutlinedTextField(
                    value = fields[key].orEmpty(),
                    onValueChange = { fields = fields + (key to it.take(120)) },
                    modifier = Modifier.fillMaxWidth(),
                    label = { Text(label) },
                    singleLine = true,
                    keyboardOptions = KeyboardOptions(keyboardType = if (key == "linkedin" || key == "website") KeyboardType.Uri else KeyboardType.Text),
                    shape = RoundedCornerShape(brand.radius.dp),
                )
            }
        }
        item {
            PrimaryButton("Save my card", { env.user.saveCard(fields); message = "Saved on this phone." }, Modifier.fillMaxWidth())
            message?.let { Text(it, style = MaterialTheme.typography.bodyMedium, modifier = Modifier.padding(top = 8.dp)) }
        }
    }
}

// ---- Guide ---------------------------------------------------------------------------------------------------------------------

@Composable
fun GuideScreen(slug: String, onBack: () -> Unit) {
    val env = LocalEnv.current
    val brand = LocalBrand.current
    val loader = rememberLoader("guide:$slug") { env.repository.guide(slug) }
    val state by loader.state

    club.campbuddy.shared.PlatformBackHandler(true, onBack)

    LazyColumn(
        Modifier.fillMaxSize().background(brand.background).statusBarsPadding(),
        contentPadding = PaddingValues(16.dp),
        verticalArrangement = Arrangement.spacedBy(10.dp),
    ) {
        item {
            Text("First WordCamp? Start here", style = MaterialTheme.typography.headlineSmall)
            QuietButton("Back", onBack, Modifier.padding(top = 8.dp))
        }
        when (val s = state) {
            LoadState.Loading -> item { Loading() }
            is LoadState.Failed -> item { ErrorState(s.message, loader.reload) }
            is LoadState.Ready -> {
                val guide = s.value
                if (s.fromCache) item { OfflineNote() }
                item { SectionTitle("How the day runs") }
                items(guide.day.size) { i -> guide.day[i].let { d -> Card { Column(verticalArrangement = Arrangement.spacedBy(4.dp)) { Text(d.title, style = MaterialTheme.typography.titleMedium); Text(d.what, style = MaterialTheme.typography.bodyMedium); if (d.tip.isNotBlank()) Text("Tip: " + d.tip, style = MaterialTheme.typography.bodySmall) } } } }
                item { SectionTitle("Good to know") }
                items(guide.tips.size) { i -> guide.tips[i].let { t -> Card { Column(verticalArrangement = Arrangement.spacedBy(4.dp)) { Text(t.title, style = MaterialTheme.typography.titleMedium); Text(t.text, style = MaterialTheme.typography.bodyMedium) } } } }
                if (guide.bring.isNotEmpty()) {
                    item { SectionTitle("What to bring") }
                    item { Card { Column(verticalArrangement = Arrangement.spacedBy(4.dp)) { guide.bring.forEach { Text("• $it", style = MaterialTheme.typography.bodyMedium) } } } }
                }
                item { SectionTitle("Words you will hear") }
                items(guide.glossary.size) { i -> guide.glossary[i].let { g -> Card { Column { Text(g.term, style = MaterialTheme.typography.titleMedium); Text(g.meaning, style = MaterialTheme.typography.bodyMedium) } } } }
                item { SectionTitle("Questions") }
                items(guide.faq.size) { i -> guide.faq[i].let { f -> Card { Column(verticalArrangement = Arrangement.spacedBy(4.dp)) { Text(f.q, style = MaterialTheme.typography.titleMedium); Text(f.a, style = MaterialTheme.typography.bodyMedium) } } } }
            }
        }
    }
}

