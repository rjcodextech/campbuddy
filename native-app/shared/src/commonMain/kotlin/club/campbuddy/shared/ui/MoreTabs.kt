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
import androidx.compose.foundation.layout.width
import androidx.compose.ui.graphics.rememberGraphicsLayer
import androidx.compose.ui.graphics.layer.drawLayer
import androidx.compose.ui.draw.drawWithContent
import kotlinx.coroutines.launch
import campbuddy_native.shared.generated.resources.wordmark
import androidx.compose.ui.graphics.Brush
import androidx.compose.runtime.rememberCoroutineScope
import androidx.compose.foundation.shape.CircleShape
import androidx.compose.foundation.lazy.rememberLazyListState
import androidx.compose.foundation.horizontalScroll
import org.jetbrains.compose.resources.painterResource
import campbuddy_native.shared.generated.resources.app_icon
import campbuddy_native.shared.generated.resources.pattern_event
import androidx.compose.ui.geometry.Offset
import androidx.compose.ui.draw.drawBehind
import campbuddy_native.shared.generated.resources.pattern_guide
import campbuddy_native.shared.generated.resources.pattern_contribute
import campbuddy_native.shared.generated.resources.pattern_icons
import campbuddy_native.shared.generated.resources.art_avatar
import campbuddy_native.shared.generated.resources.art_first_badge
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
                            Box(Modifier.fillMaxWidth().height(8.dp).background(brand.line, RoundedCornerShape(50))) {
                                if (count > 0) Box(Modifier.fillMaxWidth(count.toFloat() / total).height(8.dp).background(brand.accent, RoundedCornerShape(50)))
                            }
                        }
                    }
                }
                if (st.fromCache) item { OfflineNote() }
                if (total == 0) item { Text("No quests yet.", style = MaterialTheme.typography.bodyMedium) }

                if (things.isNotEmpty()) {
                    item { Text("Things to do", style = MaterialTheme.typography.headlineSmall) }
                    items(things.size, key = { things[it].id }) { i ->
                        val quest = things[i]
                        val meta = QUEST_META.getValue(quest.title)
                        val checked = quest.id in done
                        Card {
                            Row(horizontalArrangement = Arrangement.spacedBy(12.dp), verticalAlignment = Alignment.Top) {
                                Box(
                                    Modifier.size(44.dp).background(brand.peach, RoundedCornerShape(brand.radius.dp)),
                                    contentAlignment = Alignment.Center,
                                ) { LineIcon(meta.icon, brand.primary, size = 22.dp) }
                                Column(Modifier.weight(1f), verticalArrangement = Arrangement.spacedBy(8.dp)) {
                                    Text(quest.title, style = MaterialTheme.typography.titleLarge)
                                    quest.description?.takeIf { it.isNotBlank() }?.let { Text(it, style = MaterialTheme.typography.bodyMedium.copy(color = brand.muted)) }
                                    FlowRow(horizontalArrangement = Arrangement.spacedBy(8.dp), verticalArrangement = Arrangement.spacedBy(8.dp)) {
                                        meta.tab?.let { tab -> ActionButton(meta.label + "  →", filled = false) { onTab(tab) } }
                                        ActionButton(if (checked) "Done ✓" else "Mark done", filled = !checked) { env.user.toggleQuest(slug, quest.id) }
                                    }
                                }
                            }
                        }
                    }
                }

                if (checklist.isNotEmpty()) {
                    item { Text("Checklist", style = MaterialTheme.typography.headlineSmall) }
                    items(checklist.size, key = { checklist[it].id }) { i ->
                        val quest = checklist[i]
                        val checked = quest.id in done
                        Row(
                            Modifier.fillMaxWidth().background(brand.surface, RoundedCornerShape(brand.radius.dp)).border(1.dp, brand.line, RoundedCornerShape(brand.radius.dp))
                                .clickable(role = Role.Checkbox) { env.user.toggleQuest(slug, quest.id) }.padding(horizontal = 16.dp, vertical = 12.dp)
                                .semantics { contentDescription = quest.title + if (checked) ", done" else "" },
                            horizontalArrangement = Arrangement.spacedBy(12.dp), verticalAlignment = Alignment.Top,
                        ) {
                            Box(
                                Modifier.padding(top = 3.dp).size(18.dp).background(if (checked) brand.primary else Color.White, RoundedCornerShape(3.dp)).border(1.5.dp, if (checked) brand.primary else brand.muted, RoundedCornerShape(3.dp)),
                                contentAlignment = Alignment.Center,
                            ) { if (checked) Text("✓", style = MaterialTheme.typography.labelMedium.copy(color = Color.White, fontSize = 12.sp)) }
                            Column(Modifier.weight(1f)) {
                                Text(quest.title, style = MaterialTheme.typography.bodyLarge.copy(fontWeight = FontWeight.SemiBold))
                                quest.description?.takeIf { it.isNotBlank() }?.let { Text(it, style = MaterialTheme.typography.bodySmall) }
                            }
                        }
                    }
                }
            }
        }
    }
}

/** The website's compact button: 44 high, 4px corners, 14 bold; filled brand colour, or an outline once the thing is done. */
@Composable
fun ActionButton(text: String, filled: Boolean, onClick: () -> Unit) {
    val brand = LocalBrand.current
    val shape = RoundedCornerShape(4.dp)
    Box(
        Modifier.heightIn(min = 44.dp)
            .background(if (filled) brand.primary else androidx.compose.ui.graphics.Color.Transparent, shape)
            .border(1.dp, if (filled) brand.primary else brand.line, shape)
            .clickable(role = Role.Button, onClick = onClick)
            .padding(horizontal = 12.dp, vertical = 8.dp),
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
            Row(horizontalArrangement = Arrangement.spacedBy(14.dp), verticalAlignment = Alignment.CenterVertically) {
                LineIcon(team.icon, brand.primary, size = 24.dp)
                Column(Modifier.weight(1f), verticalArrangement = Arrangement.spacedBy(4.dp)) {
                    Text(team.name, style = MaterialTheme.typography.titleMedium)
                    Text(team.description, style = MaterialTheme.typography.bodyMedium.copy(color = brand.muted))
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
            Card(Modifier.clip(RoundedCornerShape(brand.radius.dp)).patternBackground(Res.drawable.pattern_contribute, brand.surface)) {
                Column(verticalArrangement = Arrangement.spacedBy(10.dp)) {
                    Text(
                        "CONTRIBUTE",
                        style = MaterialTheme.typography.labelMedium.copy(color = Color.White, fontWeight = FontWeight.Bold, letterSpacing = 1.sp),
                        modifier = Modifier.background(brand.primary, RoundedCornerShape(50)).padding(horizontal = 8.dp, vertical = 4.dp),
                    )
                    Text("Where could you help?", style = MaterialTheme.typography.headlineSmall.copy(fontSize = 24.sp, lineHeight = 27.6.sp, letterSpacing = (-0.48).sp))
                    Text(
                        buildAnnotatedString {
                            append("You don't need to code. Writers, designers, translators and people who are just curious all have a table. Answer a few quick questions to find your team. ")
                            withStyle(SpanStyle(fontWeight = FontWeight.Bold)) { append("Students:") }
                            append(" your contributions show on your WordPress.org profile, which you can put on your CV.")
                        },
                        style = MaterialTheme.typography.bodyMedium.copy(color = brand.muted),
                    )
                }
            }
        }
        item {
            Card {
                Column(verticalArrangement = Arrangement.spacedBy(10.dp)) {
                    Text("Going to Contributor Day?", style = MaterialTheme.typography.titleLarge)
                    Text(CONTRIBUTOR_DAY_NOTES.getValue(if (day in CONTRIBUTOR_DAY_NOTES) day else ""), style = MaterialTheme.typography.bodyMedium.copy(color = brand.muted))
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
                        Text("Pick as many as you like, or skip straight to the matches.", style = MaterialTheme.typography.bodyMedium.copy(color = brand.muted))
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

        item { Text("All contributor teams", style = MaterialTheme.typography.headlineSmall, modifier = Modifier.padding(top = 10.dp)) }
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
            Row(horizontalArrangement = Arrangement.spacedBy(6.dp)) {
                listOf(
                    Triple("people", "People", "users"),
                    Triple("sponsors", "Sponsors", "heart"),
                    Triple("deals", "Deals", "tag"),
                    Triple("steals", "Free Steals", "sparkles"),
                    Triple("info", "Info", "ticket"),
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
                "info" -> InfoList(slug)
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
        modifier.heightIn(min = 65.dp)
            .background(if (selected) brand.peach else Color.Transparent, RoundedCornerShape(4.dp))
            .border(1.dp, if (selected) Color.Transparent else brand.line, RoundedCornerShape(4.dp))
            .clickable(role = Role.Tab, onClick = onClick)
            .padding(horizontal = 2.dp, vertical = 8.dp),
        horizontalAlignment = Alignment.CenterHorizontally,
        verticalArrangement = Arrangement.spacedBy(2.dp, Alignment.CenterVertically),
    ) {
        LineIcon(icon, brand.ink, size = 23.dp)
        Text(label, style = MaterialTheme.typography.labelMedium.copy(color = brand.ink, lineHeight = 13.8.sp), textAlign = TextAlign.Center, maxLines = 2)
    }
}

@OptIn(ExperimentalLayoutApi::class)
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
                val roles = ROLE_ORDER.filter { r -> s.value.any { r in it.roles } }
                item {
                    Column(verticalArrangement = Arrangement.spacedBy(12.dp)) {
                        SearchField(query, "Search attendees…") { query = it }
                        if (roles.isNotEmpty()) {
                            FlowRow(horizontalArrangement = Arrangement.spacedBy(8.dp), verticalArrangement = Arrangement.spacedBy(8.dp)) {
                                ChoicePill("All ${s.value.size}", role == "") { role = "" }
                                roles.forEach { r ->
                                    ChoicePill((ROLE_FILTER_LABELS[r] ?: r) + " " + s.value.count { r in it.roles }, role == r) { role = r }
                                }
                            }
                        }
                    }
                }
                val shown = s.value.filter { (query.isBlank() || it.name.contains(query, ignoreCase = true)) && (role.isBlank() || role in it.roles) }
                if (shown.isEmpty()) item { Text("Nobody matches.", style = MaterialTheme.typography.bodyMedium) }
                item {
                    Card(Modifier.padding(top = 4.dp)) {
                        Column {
                            shown.take(300).forEachIndexed { index, person ->
                                Row(Modifier.fillMaxWidth().padding(vertical = 8.dp), horizontalArrangement = Arrangement.spacedBy(12.dp), verticalAlignment = Alignment.CenterVertically) {
                                    Box(Modifier.size(44.dp).clip(CircleShape).background(brand.peach).border(2.dp, brand.peach, CircleShape), contentAlignment = Alignment.Center) {
                                        RemoteImage(person.avatarUrl, Modifier.size(44.dp)) { Image(painterResource(Res.drawable.art_avatar), contentDescription = null, modifier = Modifier.size(44.dp)) }
                                    }
                                    Column(Modifier.weight(1f), verticalArrangement = Arrangement.spacedBy(2.dp)) {
                                        Text(person.name, style = MaterialTheme.typography.bodyMedium.copy(fontWeight = FontWeight.Bold))
                                        if (person.links.isNotEmpty()) {
                                            Row(horizontalArrangement = Arrangement.spacedBy(8.dp), modifier = Modifier.padding(top = 4.dp)) {
                                                person.links.take(4).forEach { link ->
                                                    Box(
                                                        Modifier.size(28.dp).background(brand.peach, CircleShape).clickable(role = Role.Button, onClickLabel = link.type ?: "Link") { env.deps.openUrl(link.url) },
                                                        contentAlignment = Alignment.Center,
                                                    ) {
                                                        if (link.type == "linkedin") Text("in", style = MaterialTheme.typography.labelLarge.copy(color = brand.ink, fontWeight = FontWeight.ExtraBold))
                                                        else if (link.type == "twitter" || link.type == "x") Text("𝕏", style = MaterialTheme.typography.labelLarge.copy(color = brand.ink, fontWeight = FontWeight.ExtraBold))
                                                        else LineIcon("globe", brand.ink, size = 14.dp)
                                                    }
                                                }
                                            }
                                        }
                                        if (person.openToMeet) {
                                            Row(
                                                Modifier.background(Color(0x1A049395), RoundedCornerShape(50)).padding(horizontal = 8.dp, vertical = 2.dp),
                                                horizontalArrangement = Arrangement.spacedBy(4.dp), verticalAlignment = Alignment.CenterVertically,
                                            ) {
                                                LineIcon("hand", Color(0xFF03676A), size = 14.dp)
                                                Text("Open to meet", style = MaterialTheme.typography.labelMedium.copy(color = Color(0xFF03676A)))
                                            }
                                        }
                                        val badges = ROLE_ORDER.filter { it in person.roles } + if (person.campCard != null && person.campCard !is kotlinx.serialization.json.JsonNull) listOf("camp_card") else emptyList()
                                        if (badges.isNotEmpty()) {
                                            FlowRow(horizontalArrangement = Arrangement.spacedBy(6.dp), verticalArrangement = Arrangement.spacedBy(4.dp)) {
                                                badges.forEach { r -> RoleBadge(r) }
                                            }
                                        }
                                        person.talks.firstOrNull()?.let { Text(it, style = MaterialTheme.typography.bodySmall) }
                                    }
                                    val met = person.id in env.user.met(slug)
                                    Text(
                                        if (met) "✓ Meeting" else "+ Meet",
                                        style = MaterialTheme.typography.labelLarge.copy(color = if (met) Color.White else brand.primary),
                                        modifier = Modifier.background(if (met) brand.primary else Color.White, RoundedCornerShape(50)).border(1.dp, brand.primary, RoundedCornerShape(50))
                                            .clickable(role = Role.Button, onClickLabel = "Want to meet ${person.name}") { env.user.toggleMet(slug, person.id) }.padding(horizontal = 14.dp, vertical = 5.dp),
                                    )
                                }
                                if (index < shown.take(300).lastIndex) Box(Modifier.fillMaxWidth().height(1.dp).background(brand.line))
                            }
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
                RemoteImage(sponsor.logoUrl, Modifier.heightIn(max = 56.dp).padding(bottom = 4.dp)) { }
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
                    ) {
                        RemoteImage(deal.logo, Modifier.size(48.dp)) { Text((deal.brand.ifBlank { deal.title }).take(1).uppercase(), style = MaterialTheme.typography.titleLarge.copy(color = brand.primary)) }
                    }
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
private fun InfoList(slug: String) {
    val env = LocalEnv.current
    val brand = LocalBrand.current
    val clipboard = LocalClipboardManager.current
    var copied by remember { mutableStateOf<String?>(null) }
    val loader = rememberLoader("info:$slug") { env.repository.info(slug) }
    val state by loader.state

    LazyColumn(Modifier.fillMaxSize(), contentPadding = PaddingValues(16.dp), verticalArrangement = Arrangement.spacedBy(14.dp)) {
        when (val s = state) {
            LoadState.Loading -> item { Loading() }
            is LoadState.Failed -> item { ErrorState(s.message, loader.reload) }
            is LoadState.Ready -> {
                if (s.fromCache) item { OfflineNote() }
                if (s.value.isEmpty()) item { Text("Event information hasn't been added yet.", style = MaterialTheme.typography.bodyLarge.copy(color = brand.muted)) }
                items(s.value.size) { i ->
                    val group = s.value[i]
                    Column(verticalArrangement = Arrangement.spacedBy(8.dp)) {
                        Text(group.title, style = MaterialTheme.typography.titleLarge)
                        Card {
                            Column(verticalArrangement = Arrangement.spacedBy(14.dp)) {
                                group.rows.forEach { row ->
                                    Row(horizontalArrangement = Arrangement.spacedBy(14.dp), verticalAlignment = Alignment.Top) {
                                        Box(
                                            Modifier.size(44.dp).background(if (row.alert) brand.danger.copy(alpha = 0.12f) else brand.peach, RoundedCornerShape(brand.radius.dp)),
                                            contentAlignment = Alignment.Center,
                                        ) { LineIcon(row.icon, if (row.alert) brand.danger else brand.primary, size = 24.dp) }
                                        Column(Modifier.weight(1f), verticalArrangement = Arrangement.spacedBy(4.dp)) {
                                            Text(row.title, style = MaterialTheme.typography.titleMedium)
                                            row.text?.let {
                                                Text(it, style = MaterialTheme.typography.bodyLarge.copy(color = brand.muted, fontFamily = if (row.mono) FontFamily.Monospace else FontFamily.Default))
                                            }
                                            row.action?.let { action ->
                                                val shown = if (action.type == "copy" && copied == action.value) "Copied" else action.label
                                                ActionButton(shown, filled = false) {
                                                    if (action.type == "copy") { clipboard.setText(AnnotatedString(action.value)); copied = action.value } else env.deps.openUrl(action.value)
                                                }
                                            }
                                        }
                                    }
                                }
                            }
                        }
                    }
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
fun MemoriesTab(slug: String, onHome: () -> Unit = {}) {
    val env = LocalEnv.current
    val brand = LocalBrand.current
    val eventLoader = rememberLoader("event:$slug") { env.repository.event(slug) }
    val event = (eventLoader.state.value as? LoadState.Ready)?.value

    LazyColumn(Modifier.fillMaxSize(), contentPadding = PaddingValues(16.dp), verticalArrangement = Arrangement.spacedBy(20.dp)) {
        event?.let { e ->
            item {
                val shape = RoundedCornerShape(brand.radius.dp)
                Card(Modifier.clip(shape).patternBackground(Res.drawable.pattern_event, brand.surface)) {
                    Row(horizontalArrangement = Arrangement.spacedBy(12.dp), verticalAlignment = Alignment.CenterVertically) {
                        Box(Modifier.size(104.dp).background(brand.background, shape).border(1.dp, brand.line, shape), contentAlignment = Alignment.Center) {
                            RemoteImage(e.logo, Modifier.size(96.dp)) { Image(painterResource(Res.drawable.app_icon), contentDescription = null, modifier = Modifier.size(72.dp)) }
                        }
                        Column(Modifier.weight(1f)) {
                            Text("MEMORIES", style = MaterialTheme.typography.labelMedium.copy(color = brand.primary, letterSpacing = 0.72.sp), modifier = Modifier.padding(bottom = 4.dp))
                            Text(e.name, style = MaterialTheme.typography.titleLarge.copy(fontSize = 20.sp, lineHeight = 23.sp, fontWeight = FontWeight.ExtraBold, letterSpacing = (-0.4).sp))
                            heroDates(e.startsOn, e.endsOn)?.let { Text(it, style = MaterialTheme.typography.bodyMedium.copy(color = brand.muted), modifier = Modifier.padding(top = 4.dp)) }
                        }
                    }
                }
            }
        }
        item { MemoriesCard(slug, withHeading = false, emptyNote = true) }
        item { QuietButton("Back to ${event?.name ?: "the event"}", onHome, Modifier.fillMaxWidth()) }
    }
}

/** The photo album and the talks on WordPress.tv, once the organizers added them (the website's memories-card). */
@Composable
fun MemoriesCard(slug: String, withHeading: Boolean, emptyNote: Boolean = false) {
    val env = LocalEnv.current
    val brand = LocalBrand.current
    val state = rememberLoader("memories:$slug") { env.repository.memories(slug) }.state.value
    val memories = (state as? LoadState.Ready)?.value
    val links = listOfNotNull(
        memories?.photos?.let { Triple("camera", "Photo album" to "The official photos. Find yourself in them.", it) },
        memories?.videos?.let { Triple("play", "Speaker sessions on WordPress.tv" to "Missed a talk, or want to watch one again? The recordings are here.", it) },
    )
    if (memories == null) return
    if (links.isEmpty()) {
        if (emptyNote) Card {
            Column {
                Text("Nothing here yet", style = MaterialTheme.typography.titleMedium)
                Text("The photo album and the recorded talks show up here once the organizers share them. Talks usually reach WordPress.tv a few weeks after the event.", style = MaterialTheme.typography.bodyMedium.copy(color = brand.muted), modifier = Modifier.padding(top = 4.dp))
            }
        }
        return
    }
    Column(verticalArrangement = Arrangement.spacedBy(12.dp)) {
        if (withHeading) Text("Memories", style = MaterialTheme.typography.headlineSmall)
        links.forEach { (icon, copy, url) ->
            Card(onClick = { env.deps.openUrl(url) }) {
                Row(horizontalArrangement = Arrangement.spacedBy(12.dp), verticalAlignment = Alignment.CenterVertically) {
                    LineIcon(icon, brand.primary, size = 22.dp)
                    Column(Modifier.weight(1f)) {
                        Text(copy.first, style = MaterialTheme.typography.titleMedium)
                        Text(copy.second, style = MaterialTheme.typography.bodyMedium.copy(color = brand.muted), modifier = Modifier.padding(top = 4.dp))
                    }
                    LineIcon("external", brand.muted, size = 18.dp)
                }
            }
        }
    }
}

private val ROLE_ORDER = listOf("organizer", "speaker", "volunteer", "microsponsor", "sponsor", "media_partner", "table_lead")

private val ROLE_LABELS = mapOf(
    "organizer" to "Organizer", "speaker" to "Speaker", "volunteer" to "Volunteer", "microsponsor" to "Microsponsor", "sponsor" to "Sponsor",
    "media_partner" to "Media Partner", "table_lead" to "Table Lead", "camp_card" to "Camp Card",
)

private val ROLE_FILTER_LABELS = mapOf(
    "organizer" to "Organizers", "speaker" to "Speakers", "volunteer" to "Volunteers", "microsponsor" to "Microsponsors", "sponsor" to "Sponsors",
    "media_partner" to "Media Partners", "table_lead" to "Table Leads",
)

/** One role mark in the website's colours (roster-roles / .role-badge--*). */
@Composable
private fun RoleBadge(role: String) {
    val brand = LocalBrand.current
    val (bg, border, ink) = when (role) {
        "organizer" -> Triple(brand.primary, Color.Transparent, Color.White)
        "speaker" -> Triple(brand.accent, Color.Transparent, brand.ink)
        "volunteer" -> Triple(Color(0xFFE3F3E9), Color(0xFF9FD3B2), Color(0xFF14532D))
        "microsponsor" -> Triple(brand.peach, brand.accent, brand.ink)
        "sponsor" -> Triple(Color(0xFFE8F0FB), Color(0xFFA9C2E8), Color(0xFF1D3A66))
        "media_partner" -> Triple(Color(0xFFF3E8FB), Color(0xFFCFA9E8), Color(0xFF4A1D66))
        "table_lead" -> Triple(Color.White, brand.accent, Color(0xFF7A4B00))
        else -> Triple(Color.White, brand.primary, brand.primary)
    }
    Text(
        ROLE_LABELS[role] ?: role,
        style = MaterialTheme.typography.labelMedium.copy(color = ink),
        modifier = Modifier.background(bg, RoundedCornerShape(50)).border(1.dp, border, RoundedCornerShape(50)).padding(horizontal = 8.dp, vertical = 1.dp),
    )
}
