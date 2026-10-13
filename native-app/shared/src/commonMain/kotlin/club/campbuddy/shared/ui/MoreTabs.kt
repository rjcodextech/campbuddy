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
import kotlinx.coroutines.launch
import campbuddy_native.shared.generated.resources.wordmark
import androidx.compose.ui.graphics.Brush
import androidx.compose.runtime.rememberCoroutineScope
import androidx.compose.foundation.shape.CircleShape
import androidx.compose.foundation.lazy.rememberLazyListState
import androidx.compose.foundation.horizontalScroll
import org.jetbrains.compose.resources.painterResource
import campbuddy_native.shared.generated.resources.app_icon
import campbuddy_native.shared.generated.resources.Res
import androidx.compose.ui.draw.clip
import androidx.compose.foundation.lazy.LazyRow
import androidx.compose.foundation.Image
import club.campbuddy.shared.model.ContributorTable
import club.campbuddy.shared.domain.ContribTeam
import club.campbuddy.shared.domain.CONTRIB_TEAMS
import club.campbuddy.shared.domain.CONTRIB_QUESTION_TAGS
import androidx.compose.ui.text.withStyle
import androidx.compose.ui.text.buildAnnotatedString
import androidx.compose.ui.text.SpanStyle
import androidx.compose.material3.AlertDialog
import androidx.compose.foundation.verticalScroll
import androidx.compose.foundation.rememberScrollState
import androidx.compose.foundation.layout.FlowRow
import androidx.compose.foundation.layout.ExperimentalLayoutApi
import androidx.compose.ui.unit.sp
import androidx.compose.ui.text.style.TextAlign
import androidx.compose.ui.text.font.FontWeight
import androidx.compose.ui.text.font.FontFamily
import androidx.compose.ui.graphics.Color
import androidx.compose.foundation.layout.heightIn
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

/** The quests the website shows as "Things to do" cards, by title: icon and where the extra button goes. */
private class QuestMeta(val icon: String, val tab: String? = null, val label: String = "Open")

private val QUEST_META = mapOf(
    "First Hello" to QuestMeta("hand"),
    "Beyond My City" to QuestMeta("globe"),
    "Speaker Hello" to QuestMeta("mic"),
    "Contribution Curious" to QuestMeta("wrench", "contribute", "Go to Contribute"),
    "Sponsor Explore" to QuestMeta("tag", "explore", "View sponsors"),
    "Asked Something" to QuestMeta("help-circle"),
    "Keep The Connection" to QuestMeta("users", "explore", "Find people"),
    "Share Camp Card" to QuestMeta("id-card", "camp-card", "Open Camp Card"),
    "Career Chat" to QuestMeta("briefcase", "explore", "See who's here"),
)

@Composable
fun QuestTab(slug: String, onTab: (String) -> Unit = {}) {
    val env = LocalEnv.current
    val words = LocalWords.current
    val brand = LocalBrand.current
    val loader = rememberLoader("quests:$slug") { env.repository.quests(slug) }
    val done = env.user.completedQuests(slug)
    val state by loader.state

    LazyColumn(Modifier.fillMaxSize(), contentPadding = PaddingValues(16.dp), verticalArrangement = Arrangement.spacedBy(12.dp)) {
        when (val st = state) {
            LoadState.Loading -> {
                item { Text(words("tabs.quest"), style = MaterialTheme.typography.headlineSmall) }
                item { Loading() }
            }
            is LoadState.Failed -> {
                item { Text(words("tabs.quest"), style = MaterialTheme.typography.headlineSmall) }
                item { ErrorState(st.message, loader.reload) }
            }
            is LoadState.Ready -> {
                val quests = st.value
                val total = quests.size
                val count = quests.count { it.id in done }
                val things = quests.filter { QUEST_META.containsKey(it.title) }
                val checklist = quests.filter { !QUEST_META.containsKey(it.title) }

                item {
                    Column(verticalArrangement = Arrangement.spacedBy(12.dp)) {
                        Row(Modifier.fillMaxWidth(), horizontalArrangement = Arrangement.SpaceBetween, verticalAlignment = Alignment.Bottom) {
                            Text(words("tabs.quest"), style = MaterialTheme.typography.headlineSmall)
                            if (total > 0) Text("$count/$total complete", style = MaterialTheme.typography.bodyLarge.copy(color = brand.muted))
                        }
                        if (total > 0) {
                            LinearProgressIndicator(
                                progress = { count.toFloat() / total },
                                color = brand.accent, trackColor = brand.line,
                                modifier = Modifier.fillMaxWidth().height(12.dp),
                            )
                        }
                    }
                }
                if (st.fromCache) item { OfflineNote() }
                if (total == 0) item { Text("No quests yet.", style = MaterialTheme.typography.bodyMedium) }

                if (things.isNotEmpty()) {
                    item { Text("Things to do", style = MaterialTheme.typography.titleLarge) }
                    items(things.size, key = { things[it].id }) { i ->
                        val quest = things[i]
                        val meta = QUEST_META.getValue(quest.title)
                        val checked = quest.id in done
                        Card {
                            Row(horizontalArrangement = Arrangement.spacedBy(16.dp), verticalAlignment = Alignment.Top) {
                                Box(
                                    Modifier.size(52.dp).background(brand.peach, RoundedCornerShape(brand.radius.dp)),
                                    contentAlignment = Alignment.Center,
                                ) { LineIcon(meta.icon, brand.primary, size = 28.dp) }
                                Column(Modifier.weight(1f), verticalArrangement = Arrangement.spacedBy(8.dp)) {
                                    Text(quest.title, style = MaterialTheme.typography.titleLarge)
                                    quest.description?.takeIf { it.isNotBlank() }?.let { Text(it, style = MaterialTheme.typography.bodyLarge.copy(color = brand.muted)) }
                                    Row(horizontalArrangement = Arrangement.spacedBy(8.dp), verticalAlignment = Alignment.CenterVertically) {
                                        ActionButton(if (checked) "Done ✓" else "Mark done", filled = !checked) { env.user.toggleQuest(slug, quest.id) }
                                        meta.tab?.let { tab -> ActionButton(meta.label, filled = false) { onTab(tab) } }
                                    }
                                }
                            }
                        }
                    }
                }

                if (checklist.isNotEmpty()) {
                    item { Text("Checklist", style = MaterialTheme.typography.titleLarge) }
                    items(checklist.size, key = { checklist[it].id }) { i ->
                        val quest = checklist[i]
                        val checked = quest.id in done
                        Card(onClick = { env.user.toggleQuest(slug, quest.id) }) {
                            Row(
                                horizontalArrangement = Arrangement.spacedBy(12.dp), verticalAlignment = Alignment.Top,
                                modifier = Modifier.semantics { role = Role.Checkbox; contentDescription = quest.title + if (checked) ", done" else "" },
                            ) {
                                Box(
                                    Modifier.size(24.dp).background(if (checked) brand.primary else brand.surface, RoundedCornerShape(6.dp))
                                        .border(1.5.dp, if (checked) brand.primary else brand.muted, RoundedCornerShape(6.dp)),
                                    contentAlignment = Alignment.Center,
                                ) { if (checked) Text("✓", style = MaterialTheme.typography.labelMedium.copy(color = androidx.compose.ui.graphics.Color.White)) }
                                Column(Modifier.weight(1f)) {
                                    Text(quest.title, style = MaterialTheme.typography.titleMedium)
                                    quest.description?.takeIf { it.isNotBlank() }?.let { Text(it, style = MaterialTheme.typography.bodySmall) }
                                }
                            }
                        }
                    }
                }
            }
        }
    }
}

/** The website's square-cornered button: filled brand colour, or an outline once the thing is done. */
@Composable
fun ActionButton(text: String, filled: Boolean, onClick: () -> Unit) {
    val brand = LocalBrand.current
    val shape = RoundedCornerShape(brand.radius.dp)
    Box(
        Modifier.heightIn(min = 48.dp)
            .background(if (filled) brand.primary else androidx.compose.ui.graphics.Color.Transparent, shape)
            .border(1.dp, if (filled) brand.primary else brand.line, shape)
            .clickable(role = Role.Button, onClick = onClick)
            .padding(horizontal = 20.dp),
        contentAlignment = Alignment.Center,
    ) {
        Text(text, style = MaterialTheme.typography.labelLarge.copy(color = if (filled) androidx.compose.ui.graphics.Color.White else brand.ink))
    }
}

// ---- Contribute ----------------------------------------------------------------------------------------

private val CONTRIBUTOR_DAY_NOTES = mapOf(
    "yes" to "Nice. Pick what you enjoy below to see which tables to head for. Many WordCamps ask you to register for Contributor Day separately, so check that you have.",
    "no" to "No problem. Have a look anyway: most teams also work online, any time you like.",
    "" to "It's a hands-on day improving WordPress. You don't need to code, and every table welcomes beginners.",
)

/** "Floor 2 · Hall B · Leads: Asha, Ravi" for one team's tables (the website's wording). */
private fun whereText(tables: List<ContributorTable>): String =
    tables.map { t -> listOfNotNull(t.place.takeIf { it.isNotBlank() }, t.leads.takeIf { it.isNotEmpty() }?.let { "Leads: " + it.joinToString(", ") }).joinToString(" · ") }
        .filter { it.isNotBlank() }.joinToString(" | ")

@OptIn(ExperimentalLayoutApi::class)
@Composable
fun ContributeTab(slug: String) {
    val env = LocalEnv.current
    val brand = LocalBrand.current
    val profile = env.user.profile
    val tablesLoader = rememberLoader("tables:$slug") { env.repository.tables(slug) }
    val questsLoader = rememberLoader("quests:$slug") { env.repository.quests(slug) }
    val tables = (tablesLoader.state.value as? LoadState.Ready)?.value.orEmpty()
    val questId = (questsLoader.state.value as? LoadState.Ready)?.value
        ?.firstOrNull { it.source == "contributor_day" || (it.source == "default" && it.title == "Contribution Curious") }?.id
    val byTeam = tables.filter { it.team != null && it.team != "other" }.groupBy { it.team }

    var selected by rememberSaveable { mutableStateOf(setOf<String>()) }
    var results by rememberSaveable { mutableStateOf<List<String>?>(null) }
    var open by remember { mutableStateOf<ContribTeam?>(null) }
    val day = profile.attendingContributorDay ?: ""

    fun show(team: ContribTeam) {
        open = team
        if (questId != null && questId !in env.user.completedQuests(slug)) env.user.toggleQuest(slug, questId)
    }

    @Composable
    fun TeamCard(team: ContribTeam) {
        val where = whereText(byTeam[team.id].orEmpty())
        Card(onClick = { show(team) }) {
            Row(horizontalArrangement = Arrangement.spacedBy(14.dp), verticalAlignment = Alignment.Top) {
                Box(Modifier.size(48.dp).background(brand.peach, RoundedCornerShape(brand.radius.dp)), contentAlignment = Alignment.Center) {
                    LineIcon(team.icon, brand.primary, size = 26.dp)
                }
                Column(Modifier.weight(1f), verticalArrangement = Arrangement.spacedBy(4.dp)) {
                    Text(team.name, style = MaterialTheme.typography.titleLarge)
                    Text(team.description, style = MaterialTheme.typography.bodyLarge.copy(color = brand.muted))
                    if (where.isNotBlank()) Text("Table: $where", style = MaterialTheme.typography.bodyMedium.copy(color = brand.primary, fontWeight = FontWeight.SemiBold))
                }
            }
        }
    }

    open?.let { team ->
        val where = whereText(byTeam[team.id].orEmpty())
        AlertDialog(
            onDismissRequest = { open = null },
            confirmButton = { LinkButton("Got it", { open = null }) },
            title = { Text(team.name) },
            text = {
                Column(Modifier.verticalScroll(rememberScrollState()), verticalArrangement = Arrangement.spacedBy(8.dp)) {
                    Text(if (team.technical) "TECHNICAL BACKGROUND HELPS" else "NO TECHNICAL BACKGROUND NEEDED", style = MaterialTheme.typography.labelMedium.copy(color = brand.primary, fontWeight = FontWeight.Bold))
                    Text(team.description, style = MaterialTheme.typography.bodyLarge)
                    Text("Who it suits", style = MaterialTheme.typography.titleMedium)
                    Text(team.whoItSuits, style = MaterialTheme.typography.bodyMedium)
                    Text("A beginner-friendly task", style = MaterialTheme.typography.titleMedium)
                    Text(team.beginnerTask, style = MaterialTheme.typography.bodyMedium)
                    Text("At their table", style = MaterialTheme.typography.titleMedium)
                    Text(team.atTheTable, style = MaterialTheme.typography.bodyMedium)
                    if (where.isNotBlank()) {
                        Text("Find the table", style = MaterialTheme.typography.titleMedium)
                        Text(where, style = MaterialTheme.typography.bodyMedium)
                    }
                }
            },
        )
    }

    LazyColumn(Modifier.fillMaxSize(), contentPadding = PaddingValues(16.dp), verticalArrangement = Arrangement.spacedBy(14.dp)) {
        item {
            Card {
                Column(verticalArrangement = Arrangement.spacedBy(10.dp)) {
                    Text(
                        "CONTRIBUTE",
                        style = MaterialTheme.typography.labelMedium.copy(color = Color.White, fontWeight = FontWeight.Bold, letterSpacing = 1.sp),
                        modifier = Modifier.background(brand.primary, RoundedCornerShape(50)).padding(horizontal = 14.dp, vertical = 6.dp),
                    )
                    Text("Where could you help?", style = MaterialTheme.typography.headlineSmall.copy(fontSize = 28.sp))
                    Text(
                        buildAnnotatedString {
                            append("You don't need to code. Writers, designers, translators and people who are just curious all have a table. Answer a few quick questions to find your team. ")
                            withStyle(SpanStyle(fontWeight = FontWeight.Bold)) { append("Students:") }
                            append(" your contributions show on your WordPress.org profile, which you can put on your CV.")
                        },
                        style = MaterialTheme.typography.bodyLarge.copy(color = brand.muted),
                    )
                }
            }
        }
        item {
            Card {
                Column(verticalArrangement = Arrangement.spacedBy(10.dp)) {
                    Text("Going to Contributor Day?", style = MaterialTheme.typography.titleLarge)
                    Text(CONTRIBUTOR_DAY_NOTES.getValue(if (day in CONTRIBUTOR_DAY_NOTES) day else ""), style = MaterialTheme.typography.bodyLarge.copy(color = brand.muted))
                    FlowRow(horizontalArrangement = Arrangement.spacedBy(8.dp), verticalArrangement = Arrangement.spacedBy(8.dp)) {
                        listOf("yes" to "Yes", "" to "Not sure yet", "no" to "Not this time").forEach { (value, label) ->
                            ChoicePill(label, selected = day == value) { env.user.saveProfile(profile.copy(attendingContributorDay = value.ifEmpty { null })) }
                        }
                    }
                }
            }
        }

        val shown = results
        if (shown == null) {
            item {
                Card {
                    Column(verticalArrangement = Arrangement.spacedBy(10.dp)) {
                        Text("What kind of work do you enjoy?", style = MaterialTheme.typography.titleLarge)
                        Text("Pick as many as you like, or skip straight to the matches.", style = MaterialTheme.typography.bodyLarge.copy(color = brand.muted))
                        FlowRow(horizontalArrangement = Arrangement.spacedBy(8.dp), verticalArrangement = Arrangement.spacedBy(8.dp)) {
                            CONTRIB_QUESTION_TAGS.forEach { tag ->
                                ChoicePill(tag.label, selected = tag.key in selected) { selected = if (tag.key in selected) selected - tag.key else selected + tag.key }
                            }
                        }
                        PrimaryButton("See my matches", { results = selected.toList() }, Modifier.fillMaxWidth())
                    }
                }
            }
        } else {
            item {
                Card {
                    Column(verticalArrangement = Arrangement.spacedBy(6.dp)) {
                        Text("Your opening line. Really, this is enough:", style = MaterialTheme.typography.bodyMedium.copy(color = brand.muted))
                        Text("\"Hi, this is my first Contributor Day. Can you help me get started?\"", style = MaterialTheme.typography.titleLarge)
                    }
                }
            }
            val ranked = CONTRIB_TEAMS.sortedByDescending { t -> t.matches.count { it in shown } }
            items((if (shown.isNotEmpty()) ranked else CONTRIB_TEAMS).take(3), key = { "match-" + it.id }) { TeamCard(it) }
            item { QuietButton("Answer again", { results = null }, Modifier.fillMaxWidth()) }
        }

        if (tables.isNotEmpty()) {
            item {
                Card {
                    Column(verticalArrangement = Arrangement.spacedBy(8.dp)) {
                        Text("Tables at this WordCamp", style = MaterialTheme.typography.titleLarge)
                        Text("Where each team sits on Contributor Day, and who to say hi to.", style = MaterialTheme.typography.bodyMedium.copy(color = brand.muted))
                        tables.forEach { table ->
                            Column {
                                Text(table.name.ifBlank { table.team ?: "Table" }, style = MaterialTheme.typography.titleMedium)
                                if (table.place.isNotBlank()) Text(table.place, style = MaterialTheme.typography.bodyMedium)
                                if (table.leads.isNotEmpty()) Text((if (table.leads.size == 1) "Table lead: " else "Table leads: ") + table.leads.joinToString(", "), style = MaterialTheme.typography.bodyMedium.copy(color = brand.muted))
                                table.note?.takeIf { it.isNotBlank() }?.let { Text(it, style = MaterialTheme.typography.bodyMedium.copy(color = brand.muted)) }
                            }
                        }
                    }
                }
            }
        }

        item { Text("All contributor teams", style = MaterialTheme.typography.titleLarge, modifier = Modifier.padding(top = 10.dp)) }
        items(CONTRIB_TEAMS, key = { "team-" + it.id }) { TeamCard(it) }
    }
}

// ---- Explore: people, sponsors, deals, free steals ---------------------------------------------------------------

@Composable
fun ExploreTab(slug: String) {
    val words = LocalWords.current
    var section by rememberSaveable(slug) { mutableStateOf("people") }

    Column(Modifier.fillMaxSize()) {
        Column(Modifier.padding(start = 16.dp, end = 16.dp, top = 16.dp), verticalArrangement = Arrangement.spacedBy(12.dp)) {
            Text(words("tabs.explore"), style = MaterialTheme.typography.headlineSmall)
            Row(horizontalArrangement = Arrangement.spacedBy(8.dp)) {
                listOf(
                    Triple("people", "People", "users"),
                    Triple("sponsors", "Sponsors", "heart"),
                    Triple("deals", "Deals", "tag"),
                    Triple("steals", "Free Steals", "sparkles"),
                ).forEach { (key, label, icon) ->
                    ExploreTile(label, icon, selected = section == key, modifier = Modifier.weight(1f)) { section = key }
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

/** One of the website's Explore tiles: an icon over its name; the open one is peach. */
@Composable
private fun ExploreTile(label: String, icon: String, selected: Boolean, modifier: Modifier, onClick: () -> Unit) {
    val brand = LocalBrand.current
    val shape = RoundedCornerShape(brand.radius.dp)
    Column(
        modifier.heightIn(min = 76.dp)
            .background(if (selected) brand.peach else brand.surface, shape)
            .border(1.dp, if (selected) brand.peach else brand.line, shape)
            .clickable(role = Role.Tab, onClick = onClick)
            .padding(horizontal = 4.dp, vertical = 10.dp),
        horizontalAlignment = Alignment.CenterHorizontally,
        verticalArrangement = Arrangement.spacedBy(6.dp, Alignment.CenterVertically),
    ) {
        LineIcon(icon, brand.ink, size = 26.dp)
        Text(label, style = MaterialTheme.typography.labelMedium.copy(color = brand.ink, fontWeight = FontWeight.SemiBold), textAlign = TextAlign.Center, maxLines = 2)
    }
}

@Composable
private fun PeopleList(slug: String) {
    val env = LocalEnv.current
    val scope = rememberCoroutineScope()
    val brand = LocalBrand.current
    val loader = rememberLoader("roster:$slug") { env.repository.roster(slug) }
    val state by loader.state
    var query by rememberSaveable { mutableStateOf("") }
    var role by rememberSaveable { mutableStateOf("") }
    val model = remember(slug) { DiscoveryModel(slug, env, scope) }

    LazyColumn(Modifier.fillMaxSize(), contentPadding = PaddingValues(16.dp), verticalArrangement = Arrangement.spacedBy(8.dp)) {
        discoveryItems(model, (state as? LoadState.Ready)?.value.orEmpty())
        when (val s = state) {
            LoadState.Loading -> item { Loading() }
            is LoadState.Failed -> item { ErrorState(s.message, loader.reload) }
            is LoadState.Ready -> {
                if (s.fromCache) item { OfflineNote() }
                val roles = s.value.flatMap { it.roles }.distinct().sorted()
                item {
                    Column(verticalArrangement = Arrangement.spacedBy(8.dp)) {
                        SearchField(query, "Search attendees…") { query = it }
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
                Text(sponsor.name, style = MaterialTheme.typography.titleLarge)
                if (sponsor.tierNames.isNotEmpty()) Text(sponsor.tierNames.joinToString(" · ").uppercase(), style = MaterialTheme.typography.labelMedium.copy(letterSpacing = 1.sp))
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

    Listing("", loader, "No deals right now.", header = {
        Text("Special offers for this WordCamp's attendees, from its sponsors and friends of the community.", style = MaterialTheme.typography.bodyLarge.copy(color = brand.muted))
    }) { deal ->
        var open by remember { mutableStateOf(false) }
        val hasMore = !deal.description.isNullOrBlank() || !deal.terms.isNullOrBlank()
        Card {
            Column(verticalArrangement = Arrangement.spacedBy(12.dp)) {
                Row(horizontalArrangement = Arrangement.spacedBy(14.dp), verticalAlignment = Alignment.CenterVertically) {
                    Box(
                        Modifier.size(56.dp).background(Color.White, RoundedCornerShape(brand.radius.dp)).border(1.dp, brand.line, RoundedCornerShape(brand.radius.dp)),
                        contentAlignment = Alignment.Center,
                    ) { Text((deal.brand.ifBlank { deal.title }).take(1).uppercase(), style = MaterialTheme.typography.titleLarge.copy(color = brand.primary)) }
                    Text(deal.brand.ifBlank { deal.title }, style = MaterialTheme.typography.titleLarge, modifier = Modifier.weight(1f))
                }
                Text(deal.highlight?.takeIf { it.isNotBlank() } ?: deal.title, style = MaterialTheme.typography.headlineSmall)
                if (hasMore) {
                    Row(Modifier.fillMaxWidth(), horizontalArrangement = Arrangement.Center) {
                        val pill = RoundedCornerShape(50)
                        Row(
                            Modifier.heightIn(min = 48.dp).background(brand.surface, pill).border(1.dp, brand.line, pill)
                                .clickable(role = Role.Button) { open = !open }.padding(horizontal = 24.dp),
                            horizontalArrangement = Arrangement.spacedBy(8.dp), verticalAlignment = Alignment.CenterVertically,
                        ) {
                            Text("Details", style = MaterialTheme.typography.labelLarge.copy(color = brand.primary))
                            LineIcon("chevron", brand.primary, size = 18.dp)
                        }
                    }
                }
                if (open) {
                    deal.description?.takeIf { it.isNotBlank() }?.let { Text(it, style = MaterialTheme.typography.bodyLarge) }
                    deal.terms?.takeIf { it.isNotBlank() }?.let { Text(it, style = MaterialTheme.typography.bodyMedium.copy(color = brand.muted)) }
                }
                deal.couponCode?.takeIf { it.isNotBlank() }?.let { code ->
                    val box = RoundedCornerShape(brand.radius.dp)
                    Row(
                        Modifier.fillMaxWidth().background(brand.peach.copy(alpha = 0.5f), box).border(1.dp, brand.line, box).padding(12.dp),
                        horizontalArrangement = Arrangement.spacedBy(12.dp), verticalAlignment = Alignment.CenterVertically,
                    ) {
                        Text("Code", style = MaterialTheme.typography.bodyLarge.copy(color = brand.muted))
                        Text(code, style = MaterialTheme.typography.titleLarge.copy(fontFamily = FontFamily.Monospace, fontWeight = FontWeight.Bold), modifier = Modifier.weight(1f))
                        ActionButton(if (copied == code) "Copied" else "Copy", filled = false) { clipboard.setText(AnnotatedString(code)); copied = code }
                    }
                }
                deal.url?.let { url ->
                    PrimaryButton((deal.ctaLabel?.takeIf { it.isNotBlank() } ?: "Get the deal") + " →", { env.deps.openUrl(url) }, Modifier.fillMaxWidth())
                }
            }
        }
    }
}

@Composable
private fun StealList(slug: String) {
    val env = LocalEnv.current
    val brand = LocalBrand.current
    val loader = rememberLoader("steals:$slug") { env.repository.freeSteals(slug) }

    Listing("", loader, "Nothing here yet.") { steal ->
        Card {
            Column(verticalArrangement = Arrangement.spacedBy(8.dp)) {
                Text(steal.name, style = MaterialTheme.typography.titleLarge)
                listOfNotNull(steal.maker, steal.category).takeIf { it.isNotEmpty() }?.let { Text(it.joinToString(" · "), style = MaterialTheme.typography.bodyMedium.copy(color = brand.muted, fontWeight = FontWeight.SemiBold)) }
                steal.description?.takeIf { it.isNotBlank() }?.let { Text(it, style = MaterialTheme.typography.bodyLarge) }
                steal.url?.let { url -> PrimaryButton((steal.ctaLabel?.takeIf { it.isNotBlank() } ?: "Get it free") + " →", { env.deps.openUrl(url) }, Modifier.fillMaxWidth()) }
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
    "interests" to "Interests (separate with commas)",
    "wordpressOrg" to "WordPress.org username",
    "linkedin" to "LinkedIn link",
    "website" to "Website",
)

private val TICKET_RED = Color(0xFF6E1111)
private val INK_DARK = Color(0xFF1F1B1C)

/** The tags typed in the interests box: tidy, no repeats, at most five. */
private fun interestTags(text: String?): List<String> =
    text.orEmpty().split(',').map { it.trim() }.filter { it.isNotEmpty() }.distinctBy { it.lowercase() }.take(5)

@OptIn(ExperimentalLayoutApi::class)
@Composable
fun CampCardTab(eventName: String = "WordCamp") {
    val env = LocalEnv.current
    val words = LocalWords.current
    val brand = LocalBrand.current
    val saved = env.user.card()
    val profile = env.user.profile
    var fields by remember {
        mutableStateOf(
            CARD_FIELDS.associate { (key, _) ->
                key to (saved[key] ?: if (key == "role") profile.profession.orEmpty() else if (key == "wordpressOrg") profile.wporg.orEmpty() else "")
            },
        )
    }
    var message by remember { mutableStateOf<String?>(null) }
    val name = fields["name"].orEmpty()
    val role = listOf("role", "company").mapNotNull { fields[it]?.takeIf(String::isNotBlank) }.joinToString(" · ")
    val tags = interestTags(fields["interests"])

    LazyColumn(Modifier.fillMaxSize(), contentPadding = PaddingValues(16.dp), verticalArrangement = Arrangement.spacedBy(12.dp)) {
        item { Text(words("tabs.camp_card"), style = MaterialTheme.typography.headlineSmall) }
        item {
            Text(
                if (name.isBlank()) "This is a preview. Fill in the form below to make it yours." else "Your card stays on this phone until you choose to share it.",
                style = MaterialTheme.typography.bodyLarge.copy(color = brand.muted),
            )
        }
        item {
            LazyRow(horizontalArrangement = Arrangement.spacedBy(12.dp)) {
                item {
                    Card(Modifier.fillParentMaxWidth(0.92f)) {
                        Column(verticalArrangement = Arrangement.spacedBy(12.dp)) {
                            Text("Ticket", style = MaterialTheme.typography.titleMedium.copy(color = brand.muted))
                            TicketArt(eventName, name, role, tags)
                        }
                    }
                }
                item {
                    Card(Modifier.fillParentMaxWidth(0.92f)) {
                        Column(verticalArrangement = Arrangement.spacedBy(12.dp)) {
                            Text("Classic", style = MaterialTheme.typography.titleMedium.copy(color = brand.muted))
                            ClassicArt(eventName, name, role, tags)
                        }
                    }
                }
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

/** The "Ticket" layout: a deep-red header with the event, then the person's name on a cream ticket. */
@OptIn(ExperimentalLayoutApi::class)
@Composable
private fun TicketArt(event: String, name: String, role: String, tags: List<String>) {
    val brand = LocalBrand.current
    val shape = RoundedCornerShape(16.dp)
    Column(Modifier.fillMaxWidth().clip(shape).background(brand.surface)) {
        Box(Modifier.fillMaxWidth().height(160.dp).background(TICKET_RED), contentAlignment = Alignment.Center) {
            Column(horizontalAlignment = Alignment.CenterHorizontally, verticalArrangement = Arrangement.spacedBy(12.dp)) {
                Box(
                    Modifier.size(76.dp).background(Color.White, RoundedCornerShape(18.dp)).border(3.dp, brand.accent, RoundedCornerShape(18.dp)),
                    contentAlignment = Alignment.Center,
                ) { Image(painterResource(Res.drawable.app_icon), contentDescription = null, modifier = Modifier.size(56.dp)) }
                Text(
                    event.uppercase(),
                    style = MaterialTheme.typography.labelLarge.copy(color = Color(0xFFF4E4C8), letterSpacing = 3.sp, fontWeight = FontWeight.Bold),
                    textAlign = TextAlign.Center, modifier = Modifier.padding(horizontal = 16.dp),
                )
            }
        }
        Box(Modifier.fillMaxWidth().height(2.dp).background(brand.line))
        Column(
            Modifier.fillMaxWidth().heightIn(min = 280.dp).padding(20.dp),
            horizontalAlignment = Alignment.CenterHorizontally, verticalArrangement = Arrangement.Center,
        ) {
            Text("HI, I'M", style = MaterialTheme.typography.labelLarge.copy(color = brand.accent, letterSpacing = 4.sp, fontWeight = FontWeight.Bold))
            Text(
                name.ifBlank { "Your name" },
                style = MaterialTheme.typography.headlineSmall.copy(color = TICKET_RED, fontSize = 30.sp, lineHeight = 36.sp, fontWeight = FontWeight.Bold),
                textAlign = TextAlign.Center, modifier = Modifier.padding(top = 6.dp),
            )
            Box(Modifier.padding(vertical = 12.dp).size(width = 44.dp, height = 4.dp).background(brand.accent, RoundedCornerShape(2.dp)))
            if (role.isNotBlank()) Text(role, style = MaterialTheme.typography.titleMedium.copy(color = brand.muted, fontWeight = FontWeight.Normal), textAlign = TextAlign.Center)
            if (tags.isNotEmpty()) {
                FlowRow(Modifier.padding(top = 12.dp), horizontalArrangement = Arrangement.spacedBy(8.dp, Alignment.CenterHorizontally), verticalArrangement = Arrangement.spacedBy(8.dp)) {
                    tags.forEach { tag ->
                        Text(
                            tag, style = MaterialTheme.typography.labelLarge.copy(color = TICKET_RED),
                            modifier = Modifier.background(brand.peach.copy(alpha = 0.6f), RoundedCornerShape(50)).border(1.dp, brand.line, RoundedCornerShape(50)).padding(horizontal = 14.dp, vertical = 6.dp),
                        )
                    }
                }
            }
        }
    }
}

/** The "Classic" layout: dark card, gold accent. */
@OptIn(ExperimentalLayoutApi::class)
@Composable
private fun ClassicArt(event: String, name: String, role: String, tags: List<String>) {
    val brand = LocalBrand.current
    val shape = RoundedCornerShape(16.dp)
    Column(
        Modifier.fillMaxWidth().clip(shape).background(INK_DARK).heightIn(min = 400.dp).padding(24.dp),
        verticalArrangement = Arrangement.spacedBy(10.dp),
    ) {
        Text(event.uppercase(), style = MaterialTheme.typography.labelMedium.copy(color = brand.accent, letterSpacing = 2.sp, fontWeight = FontWeight.Bold))
        Box(Modifier.size(width = 44.dp, height = 4.dp).background(brand.accent, RoundedCornerShape(2.dp)))
        Text(name.ifBlank { "Your name" }, style = MaterialTheme.typography.headlineSmall.copy(color = Color.White, fontSize = 30.sp, lineHeight = 36.sp, fontWeight = FontWeight.Bold), modifier = Modifier.padding(top = 80.dp))
        if (role.isNotBlank()) Text(role, style = MaterialTheme.typography.titleMedium.copy(color = Color(0xFFD9CFC6), fontWeight = FontWeight.Normal))
        if (tags.isNotEmpty()) {
            FlowRow(horizontalArrangement = Arrangement.spacedBy(8.dp), verticalArrangement = Arrangement.spacedBy(8.dp)) {
                tags.forEach { tag ->
                    Text(tag, style = MaterialTheme.typography.labelLarge.copy(color = brand.accent), modifier = Modifier.border(1.dp, brand.accent, RoundedCornerShape(50)).padding(horizontal = 14.dp, vertical = 6.dp))
                }
            }
        }
    }
}

// ---- Guide ---------------------------------------------------------------------------------------------------------------------

@OptIn(ExperimentalLayoutApi::class)
@Composable
fun GuideScreen(slug: String, onBack: () -> Unit) {
    val env = LocalEnv.current
    val brand = LocalBrand.current
    val loader = rememberLoader("guide:$slug") { env.repository.guide(slug) }
    val eventLoader = rememberLoader("event:$slug") { env.repository.event(slug) }
    val state by loader.state
    val tag = (eventLoader.state.value as? LoadState.Ready)?.value?.shortName?.takeIf { it.isNotBlank() }?.let { if (it.startsWith("#")) it else "#$it" }
    val listState = rememberLazyListState()
    val scope = rememberCoroutineScope()

    club.campbuddy.shared.PlatformBackHandler(true, onBack)

    Column(Modifier.fillMaxSize().background(brand.background).statusBarsPadding()) {
        Row(Modifier.fillMaxWidth().padding(horizontal = 16.dp, vertical = 8.dp), horizontalArrangement = Arrangement.SpaceBetween, verticalAlignment = Alignment.CenterVertically) {
            Image(painterResource(Res.drawable.wordmark), contentDescription = "CampBuddy", modifier = Modifier.height(40.dp))
            Box(
                Modifier.size(44.dp).background(brand.primary, CircleShape).clickable(role = Role.Button, onClickLabel = "Back", onClick = onBack),
                contentAlignment = Alignment.Center,
            ) { Text("✕", style = MaterialTheme.typography.titleMedium.copy(color = Color.White)) }
        }
        Box(Modifier.fillMaxWidth().height(1.dp).background(brand.line))

        LazyColumn(Modifier.fillMaxSize(), state = listState, contentPadding = PaddingValues(16.dp), verticalArrangement = Arrangement.spacedBy(12.dp)) {
            item {
                Column(
                    Modifier.fillMaxWidth().clip(RoundedCornerShape(16.dp))
                        .background(Brush.linearGradient(listOf(Color(0xFF5B1212), Color(0xFF7A2A16), Color(0xFF4F1313))))
                        .padding(24.dp),
                    verticalArrangement = Arrangement.spacedBy(14.dp),
                ) {
                    Row(
                        Modifier.background(Color.White.copy(alpha = 0.12f), RoundedCornerShape(50)).padding(start = 6.dp, end = 16.dp, top = 6.dp, bottom = 6.dp),
                        horizontalArrangement = Arrangement.spacedBy(10.dp), verticalAlignment = Alignment.CenterVertically,
                    ) {
                        Image(painterResource(Res.drawable.app_icon), contentDescription = null, modifier = Modifier.size(32.dp).clip(CircleShape))
                        Text("WORDCAMP 101", style = MaterialTheme.typography.labelLarge.copy(color = Color.White, fontWeight = FontWeight.Bold, letterSpacing = 1.sp))
                    }
                    Text(
                        "New to ${tag ?: "WordCamp"}? Start here.",
                        style = MaterialTheme.typography.headlineSmall.copy(color = Color.White, fontSize = 34.sp, lineHeight = 40.sp, fontWeight = FontWeight.ExtraBold),
                    )
                    Text("How the day runs, the words people throw around, and how to start talking to people you don't know yet.", style = MaterialTheme.typography.bodyLarge.copy(color = Color(0xFFF1DFD2)))
                    Row(horizontalArrangement = Arrangement.spacedBy(8.dp)) {
                        listOf("clock" to "5-minute read", "wifi" to "Works offline").forEach { (icon, label) ->
                            Row(
                                Modifier.background(brand.accent, RoundedCornerShape(50)).padding(horizontal = 12.dp, vertical = 8.dp),
                                horizontalArrangement = Arrangement.spacedBy(6.dp), verticalAlignment = Alignment.CenterVertically,
                            ) {
                                LineIcon(icon, Color(0xFF3A1A00), size = 16.dp)
                                Text(label, style = MaterialTheme.typography.labelMedium.copy(color = Color(0xFF3A1A00), fontWeight = FontWeight.Bold))
                            }
                        }
                    }
                }
            }
            when (val s = state) {
                LoadState.Loading -> item { Loading() }
                is LoadState.Failed -> item { ErrorState(s.message, loader.reload) }
                is LoadState.Ready -> {
                    val guide = s.value
                    val sections = buildList {
                        add("Your day")
                        if (guide.tips.isNotEmpty()) add("Good to know")
                        if (guide.bring.isNotEmpty()) add("What to bring")
                        if (guide.glossary.isNotEmpty()) add("Words you'll hear")
                        if (guide.faq.isNotEmpty()) add("Questions")
                    }
                    // list positions: 0 hero, 1 pills, then each section is a heading followed by its cards
                    val starts = mutableMapOf<String, Int>()
                    var at = 2
                    sections.forEach { name ->
                        starts[name] = at
                        at += 1 + when (name) {
                            "Your day" -> guide.day.size
                            "Good to know" -> guide.tips.size
                            "What to bring" -> 1
                            "Words you'll hear" -> guide.glossary.size
                            else -> guide.faq.size
                        }
                    }
                    item {
                        Row(Modifier.horizontalScroll(rememberScrollState()), horizontalArrangement = Arrangement.spacedBy(10.dp)) {
                            sections.forEach { name ->
                                val pill = RoundedCornerShape(50)
                                Text(
                                    name, style = MaterialTheme.typography.titleMedium.copy(color = brand.ink),
                                    modifier = Modifier.background(brand.surface, pill).border(1.dp, brand.line, pill)
                                        .clickable(role = Role.Button) { scope.launch { listState.animateScrollToItem(starts.getValue(name) + if (s.fromCache) 1 else 0) } }
                                        .padding(horizontal = 22.dp, vertical = 14.dp),
                                )
                            }
                        }
                    }
                    if (s.fromCache) item { OfflineNote() }
                    item { Text("How the day runs", style = MaterialTheme.typography.headlineSmall, modifier = Modifier.padding(top = 12.dp)) }
                    items(guide.day.size) { i ->
                        val d = guide.day[i]
                        Card {
                            Row(horizontalArrangement = Arrangement.spacedBy(14.dp), verticalAlignment = Alignment.Top) {
                                Box(Modifier.size(44.dp).background(brand.peach, RoundedCornerShape(brand.radius.dp)), contentAlignment = Alignment.Center) { LineIcon(d.icon, brand.primary, size = 24.dp) }
                                Column(Modifier.weight(1f), verticalArrangement = Arrangement.spacedBy(4.dp)) {
                                    Text(d.title, style = MaterialTheme.typography.titleLarge)
                                    Text(d.what, style = MaterialTheme.typography.bodyLarge)
                                    if (d.tip.isNotBlank()) Text("Tip: " + d.tip, style = MaterialTheme.typography.bodyMedium.copy(color = brand.muted))
                                }
                            }
                        }
                    }
                    if (guide.tips.isNotEmpty()) {
                        item { Text("Good to know", style = MaterialTheme.typography.headlineSmall, modifier = Modifier.padding(top = 12.dp)) }
                        items(guide.tips.size) { i ->
                            val t = guide.tips[i]
                            Card {
                                Row(horizontalArrangement = Arrangement.spacedBy(14.dp), verticalAlignment = Alignment.Top) {
                                    Box(Modifier.size(44.dp).background(brand.peach, RoundedCornerShape(brand.radius.dp)), contentAlignment = Alignment.Center) { LineIcon(t.icon, brand.primary, size = 24.dp) }
                                    Column(Modifier.weight(1f), verticalArrangement = Arrangement.spacedBy(4.dp)) {
                                        Text(t.title, style = MaterialTheme.typography.titleLarge)
                                        Text(t.text, style = MaterialTheme.typography.bodyLarge)
                                    }
                                }
                            }
                        }
                    }
                    if (guide.bring.isNotEmpty()) {
                        item { Text("What to bring", style = MaterialTheme.typography.headlineSmall, modifier = Modifier.padding(top = 12.dp)) }
                        item { Card { Column(verticalArrangement = Arrangement.spacedBy(6.dp)) { guide.bring.forEach { Text("• $it", style = MaterialTheme.typography.bodyLarge) } } } }
                    }
                    if (guide.glossary.isNotEmpty()) {
                        item { Text("Words you'll hear", style = MaterialTheme.typography.headlineSmall, modifier = Modifier.padding(top = 12.dp)) }
                        items(guide.glossary.size) { i ->
                            val g = guide.glossary[i]
                            Card { Column(verticalArrangement = Arrangement.spacedBy(2.dp)) { Text(g.term, style = MaterialTheme.typography.titleLarge); Text(g.meaning, style = MaterialTheme.typography.bodyLarge) } }
                        }
                    }
                    if (guide.faq.isNotEmpty()) {
                        item { Text("Questions", style = MaterialTheme.typography.headlineSmall, modifier = Modifier.padding(top = 12.dp)) }
                        items(guide.faq.size) { i ->
                            val f = guide.faq[i]
                            Card { Column(verticalArrangement = Arrangement.spacedBy(4.dp)) { Text(f.q, style = MaterialTheme.typography.titleLarge); Text(f.a, style = MaterialTheme.typography.bodyLarge) } }
                        }
                    }
                }
            }
        }
    }
}

