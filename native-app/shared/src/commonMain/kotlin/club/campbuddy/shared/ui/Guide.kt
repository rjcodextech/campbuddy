package club.campbuddy.shared.ui

import androidx.compose.foundation.Image
import androidx.compose.foundation.background
import androidx.compose.foundation.border
import androidx.compose.foundation.clickable
import androidx.compose.foundation.horizontalScroll
import androidx.compose.foundation.layout.Arrangement
import androidx.compose.foundation.layout.Box
import androidx.compose.foundation.layout.Column
import androidx.compose.foundation.layout.IntrinsicSize
import androidx.compose.foundation.layout.PaddingValues
import androidx.compose.foundation.layout.Row
import androidx.compose.foundation.layout.fillMaxHeight
import androidx.compose.foundation.layout.fillMaxSize
import androidx.compose.foundation.layout.fillMaxWidth
import androidx.compose.foundation.layout.height
import androidx.compose.foundation.layout.heightIn
import androidx.compose.foundation.layout.padding
import androidx.compose.foundation.layout.size
import androidx.compose.foundation.layout.statusBarsPadding
import androidx.compose.foundation.layout.width
import androidx.compose.foundation.lazy.LazyColumn
import androidx.compose.foundation.lazy.LazyListScope
import androidx.compose.foundation.lazy.rememberLazyListState
import androidx.compose.foundation.rememberScrollState
import androidx.compose.foundation.shape.CircleShape
import androidx.compose.foundation.shape.RoundedCornerShape
import androidx.compose.material3.MaterialTheme
import androidx.compose.material3.Text
import androidx.compose.runtime.Composable
import androidx.compose.runtime.getValue
import androidx.compose.runtime.mutableStateOf
import androidx.compose.runtime.remember
import androidx.compose.runtime.rememberCoroutineScope
import androidx.compose.runtime.setValue
import androidx.compose.ui.Alignment
import androidx.compose.ui.Modifier
import androidx.compose.ui.draw.clip
import androidx.compose.ui.draw.drawBehind
import androidx.compose.ui.geometry.Offset
import androidx.compose.ui.graphics.Brush
import androidx.compose.ui.graphics.Color
import androidx.compose.ui.semantics.Role
import androidx.compose.ui.text.SpanStyle
import androidx.compose.ui.text.buildAnnotatedString
import androidx.compose.ui.text.font.FontWeight
import androidx.compose.ui.text.withStyle
import androidx.compose.ui.unit.dp
import androidx.compose.ui.unit.sp
import campbuddy_native.shared.generated.resources.Res
import campbuddy_native.shared.generated.resources.app_icon
import campbuddy_native.shared.generated.resources.art_first_badge
import campbuddy_native.shared.generated.resources.pattern_guide
import campbuddy_native.shared.generated.resources.wordmark
import club.campbuddy.shared.PlatformBackHandler
import club.campbuddy.shared.domain.EventClock
import club.campbuddy.shared.domain.Moments
import club.campbuddy.shared.model.Guide
import club.campbuddy.shared.model.GuideDay
import club.campbuddy.shared.model.InfoRow
import club.campbuddy.shared.model.Session
import kotlinx.coroutines.launch
import org.jetbrains.compose.resources.painterResource

/** The first-timer guide, laid out the way the website's /event/<slug>/guide page is. */
@Composable
fun GuideScreen(slug: String, onBack: () -> Unit, onTab: (String) -> Unit = {}) {
    val env = LocalEnv.current
    val brand = LocalBrand.current
    val loader = rememberLoader("guide:$slug") { env.repository.guide(slug) }
    val eventLoader = rememberLoader("event:$slug") { env.repository.event(slug) }
    val sessionsLoader = rememberLoader("sessions:$slug") { env.repository.sessions(slug) }
    val infoGroups = (rememberLoader("info:$slug") { env.repository.info(slug) }.state.value as? LoadState.Ready)?.value.orEmpty()
    val event = (eventLoader.state.value as? LoadState.Ready)?.value
    val sessions = (sessionsLoader.state.value as? LoadState.Ready)?.value.orEmpty()
    val state by loader.state
    val listState = rememberLazyListState()
    val scope = rememberCoroutineScope()
    val tag = event?.shortName?.takeIf { it.isNotBlank() }

    PlatformBackHandler(true, onBack)

    // The rows of "At <event>": the same facts the website lists (venue, registration, wifi...), plus the code of conduct.
    val practical = infoGroups.flatMap { it.rows }.filter { it.title in setOf("Venue", "Registration", "Wifi", "Contributor Day", "Social event", "Emergency contact", "Code of Conduct") }
        .sortedBy { PRACTICAL_ORDER.indexOf(it.title) }

    Column(Modifier.fillMaxSize().background(brand.background).statusBarsPadding()) {
        Row(Modifier.fillMaxWidth().padding(horizontal = 16.dp, vertical = 8.dp), horizontalArrangement = Arrangement.SpaceBetween, verticalAlignment = Alignment.CenterVertically) {
            Image(painterResource(Res.drawable.wordmark), contentDescription = "CampBuddy", modifier = Modifier.height(32.dp))
            Box(Modifier.size(44.dp).clickable(role = Role.Button, onClickLabel = "Back", onClick = onBack), contentAlignment = Alignment.Center) {
                Box(Modifier.size(32.dp).background(brand.primary, CircleShape), contentAlignment = Alignment.Center) { LineIcon("x", Color.White, size = 16.dp) }
            }
        }
        Box(Modifier.fillMaxWidth().height(1.dp).background(brand.line))

        LazyColumn(Modifier.fillMaxSize(), state = listState, contentPadding = PaddingValues(16.dp)) {
            item { GuideHero(tag) }

            val starts = mutableMapOf<String, Int>()
            var count = 1 // the hero
            fun row(content: @Composable () -> Unit, scope: LazyListScope) { scope.item { content() }; count++ }

            val sections = buildList {
                if (practical.isNotEmpty()) add("At this event")
                add("What is it?"); add("Your day"); add("Words you'll hear"); add("Tips"); add("Students"); add("What to bring"); add("FAQ")
            }
            // The jump pills sit right after the hero.
            item {
                Row(Modifier.padding(top = 20.dp).horizontalScroll(rememberScrollState()), horizontalArrangement = Arrangement.spacedBy(8.dp)) {
                    sections.forEach { name ->
                        val pill = RoundedCornerShape(50)
                        Text(
                            name, style = MaterialTheme.typography.labelLarge.copy(color = brand.ink, fontWeight = FontWeight.SemiBold),
                            modifier = Modifier.heightIn(min = 44.dp).background(brand.surface, pill).border(1.dp, brand.line, pill)
                                .clickable(role = Role.Button) { scope.launch { listState.animateScrollToItem(starts[name] ?: 0) } }.padding(horizontal = 14.dp, vertical = 11.dp),
                        )
                    }
                }
            }
            count++

            when (val s = state) {
                LoadState.Loading -> item { Loading() }
                is LoadState.Failed -> item { ErrorState(s.message, loader.reload) }
                is LoadState.Ready -> {
                    val guide = s.value
                    val multiDay = sessions.mapNotNull { x -> EventClock.startOf(x)?.let { EventClock.day(it, EventClock.zone(event?.timezone ?: "UTC")) } }.distinct().size > 1
                    val zone = EventClock.zone(event?.timezone ?: "UTC")

                    if (s.fromCache) { item { OfflineNote() }; count++ }

                    if (practical.isNotEmpty()) {
                        starts["At this event"] = count
                        item { GuideTitle("At ${event?.name ?: "this WordCamp"}") }; count++
                        item { PracticalCard(practical, env.deps.openUrl) }; count++
                    }

                    starts["What is it?"] = count
                    item { GuideTitle("What is a WordCamp?") }; count++
                    item { WhatIsCard() }; count++

                    starts["Your day"] = count
                    item { GuideTitle("How the day usually goes") }; count++
                    item { GuideDesc("Every WordCamp is a little different, but most follow this shape." + if (event != null) " Where we could find it in this event's schedule, you'll see the real time." else "") }; count++
                    item {
                        Column {
                            guide.day.forEachIndexed { i, step -> DayStep(step, whenText(step, sessions, zone, multiDay), last = i == guide.day.lastIndex) }
                        }
                    }
                    count++

                    starts["Words you'll hear"] = count
                    item { GuideTitle("Words you'll hear") }; count++
                    item { GuideDesc("Tap a word to see what it means.") }; count++
                    item { Accordion(guide.glossary.map { it.term to it.meaning }) }; count++

                    starts["Tips"] = count
                    item { GuideTitle("Tips from WordCamp regulars") }; count++
                    guide.tips.forEach { t -> item { TipCard(t.icon, t.title, t.text) }; count++ }

                    starts["Students"] = count
                    item { GuideTitle("For college students", icon = "graduation-cap") }; count++
                    item { GuideDesc("For the price of a ticket you get a day of practical talks, a chance to work on real open-source code, and a room full of people who hire.") }; count++
                    guide.students.points.forEach { t -> item { TipCard(t.icon, t.title, t.text) }; count++ }
                    if (guide.students.intro.isNotBlank()) {
                        item {
                            Card {
                                Column {
                                    Text("YOUR 15-SECOND INTRO", style = MaterialTheme.typography.labelMedium.copy(color = brand.primary, letterSpacing = 0.72.sp))
                                    Text(guide.students.intro, style = MaterialTheme.typography.titleLarge.copy(fontSize = 18.sp, lineHeight = 24.sp), modifier = Modifier.padding(top = 4.dp, bottom = 8.dp))
                                    Text("End with a question. Then it's their turn to talk, and the conversation keeps going.", style = MaterialTheme.typography.bodySmall)
                                }
                            }
                        }
                        count++
                    }

                    starts["What to bring"] = count
                    item { GuideTitle("What to bring") }; count++
                    item { BringCard(guide.bring) }; count++

                    starts["FAQ"] = count
                    item { GuideTitle("Questions newcomers ask") }; count++
                    item { Accordion(guide.faq.map { it.q to it.a }) }; count++

                    item { GuideTitle("Ready? Three small steps") }
                    item { NextStep("star", "Save 3 sessions you'd enjoy", "Tap the star on a session in My Day. Look for \"Beginner friendly\".") { onTab("my-day") } }
                    item { NextStep("compass", "Start your Quest", "Small, friendly challenges that make meeting people easy.") { onTab("quest") } }
                    item { NextStep("id-card", "Make your Camp Card", "A digital badge people can scan to stay in touch.") { onTab("camp-card") } }
                }
            }
        }
    }
}

private val PRACTICAL_ORDER = listOf("Venue", "Registration", "Wifi", "Contributor Day", "Social event", "Emergency contact", "Code of Conduct")

@Composable
private fun GuideHero(tag: String?) {
    val brand = LocalBrand.current
    Column(
        Modifier.fillMaxWidth().clip(RoundedCornerShape(14.dp))
            .background(Brush.linearGradient(listOf(Color(0xFF6D2021), Color(0xFF4A1415))))
            .patternBackground(Res.drawable.pattern_guide, null, alpha = 0.9f)
            .drawBehind {
                drawRect(Brush.radialGradient(listOf(Color(0x59F4A629), Color.Transparent), center = Offset(size.width * 0.92f, size.height * 0.12f), radius = size.width * 0.45f))
                drawRect(Brush.radialGradient(listOf(Color(0x8CFF4D2D), Color.Transparent), center = Offset(0f, size.height), radius = size.width * 0.55f))
                // The gold ticket-stub edge along the top.
                var x = 0f
                while (x < size.width) { drawRect(Color(0xCCD98A1C), Offset(x, 0f), androidx.compose.ui.geometry.Size(18.dp.toPx(), 5.dp.toPx())); x += 26.dp.toPx() }
            }
            .padding(horizontal = 20.dp, vertical = 24.dp),
        verticalArrangement = Arrangement.spacedBy(12.dp),
    ) {
        Row(
            Modifier.background(Color(0x1FFBEFD9), RoundedCornerShape(50)).padding(start = 4.dp, end = 10.dp, top = 4.dp, bottom = 4.dp),
            horizontalArrangement = Arrangement.spacedBy(8.dp), verticalAlignment = Alignment.CenterVertically,
        ) {
            Image(painterResource(Res.drawable.app_icon), contentDescription = null, modifier = Modifier.size(22.dp).clip(CircleShape).background(Color(0xFFFBEFD9)))
            Text("WORDCAMP 101", style = MaterialTheme.typography.labelMedium.copy(color = Color(0xFFFBEFD9), letterSpacing = 0.72.sp))
        }
        Row(verticalAlignment = Alignment.CenterVertically) {
            Column(Modifier.weight(1f), verticalArrangement = Arrangement.spacedBy(8.dp)) {
                Text(
                    "New to ${tag ?: "WordCamp"}?\nStart here.",
                    style = MaterialTheme.typography.headlineSmall.copy(color = Color.White, fontSize = 24.8.sp, lineHeight = 28.5.sp, fontWeight = FontWeight.ExtraBold, letterSpacing = (-0.25).sp),
                )
                Text("How the day runs, the words people throw around, and how to start talking to people you don't know yet.", style = MaterialTheme.typography.bodyMedium.copy(color = Color(0xE0FBEFD9)))
            }
            Image(painterResource(Res.drawable.art_first_badge), contentDescription = null, modifier = Modifier.width(84.dp).padding(start = 8.dp))
        }
        Row(horizontalArrangement = Arrangement.spacedBy(8.dp)) {
            listOf("clock" to "5-minute read", "wifi" to "Works offline").forEach { (icon, label) ->
                Row(
                    Modifier.background(Color(0xFFD98A1C), RoundedCornerShape(50)).padding(horizontal = 10.dp, vertical = 4.dp),
                    horizontalArrangement = Arrangement.spacedBy(6.dp), verticalAlignment = Alignment.CenterVertically,
                ) {
                    LineIcon(icon, Color(0xFF4A1415), size = 14.dp)
                    Text(label, style = MaterialTheme.typography.labelMedium.copy(color = Color(0xFF4A1415)))
                }
            }
        }
    }
}

@Composable
private fun GuideTitle(text: String, icon: String? = null) {
    val brand = LocalBrand.current
    Row(Modifier.padding(top = 32.dp, bottom = 8.dp), horizontalArrangement = Arrangement.spacedBy(8.dp), verticalAlignment = Alignment.CenterVertically) {
        if (icon != null) LineIcon(icon, brand.primary, size = 20.dp)
        Text(text, style = MaterialTheme.typography.titleLarge.copy(fontWeight = FontWeight.ExtraBold, fontSize = 18.sp))
    }
}

@Composable
private fun GuideDesc(text: String) {
    Text(text, style = MaterialTheme.typography.bodyMedium.copy(color = LocalBrand.current.muted), modifier = Modifier.padding(bottom = 12.dp))
}

@Composable
private fun PracticalCard(rows: List<InfoRow>, open: (String) -> Unit) {
    val brand = LocalBrand.current
    Card {
        Column {
            rows.forEachIndexed { i, row ->
                val link = row.action?.takeIf { it.type == "url" }?.value
                val title = if (row.title == "Emergency contact") "Need help?" else row.title
                val text = if (row.title == "Code of Conduct") "The rules that keep this event welcoming for everyone." else row.text
                Row(
                    Modifier.fillMaxWidth().let { if (link != null) it.clickable(role = Role.Button) { open(link) } else it }.padding(vertical = 12.dp),
                    horizontalArrangement = Arrangement.spacedBy(14.dp), verticalAlignment = Alignment.Top,
                ) {
                    Box(Modifier.padding(top = 2.dp).size(24.dp), contentAlignment = Alignment.Center) { LineIcon(row.icon, brand.primary, size = 22.dp) }
                    Column(Modifier.weight(1f)) {
                        Text(title, style = MaterialTheme.typography.titleMedium)
                        text?.let { Text(it, style = MaterialTheme.typography.bodyMedium.copy(color = brand.muted), modifier = Modifier.padding(top = 2.dp)) }
                    }
                }
                if (i < rows.lastIndex) Box(Modifier.fillMaxWidth().height(1.dp).background(brand.line))
            }
        }
    }
}

@Composable
private fun WhatIsCard() {
    Card {
        Column(verticalArrangement = Arrangement.spacedBy(12.dp)) {
            fun bold(vararg parts: Pair<String, Boolean>) = buildAnnotatedString {
                parts.forEach { (text, strong) -> if (strong) withStyle(SpanStyle(fontWeight = FontWeight.Bold)) { append(text) } else append(text) }
            }
            Text(
                bold("A " to false, "WordCamp" to true, " is a conference about WordPress, the free software that runs a large part of the web. Each one is organized by " to false, "local volunteers" to true,
                    " from the WordPress community, and there are WordCamps in cities all over the world." to false),
                style = MaterialTheme.typography.bodyLarge,
            )
            Text(
                bold("It's for " to false, "everyone who uses WordPress" to true, " or is curious about it: bloggers, small-business owners, designers, developers, marketers, students and writers. You don't need to be an expert, and nobody will quiz you." to false),
                style = MaterialTheme.typography.bodyLarge,
            )
            Text(
                bold("Sponsors" to true, " pay for much of it, so tickets stay cheap. Speakers and organizers are volunteers, not paid staff, which is a big part of why people are so easy to talk to." to false),
                style = MaterialTheme.typography.bodyLarge,
            )
        }
    }
}

/** "At this WordCamp: Sat 9:30 AM · Track 1" for a step of the day, from the real schedule (the website's guide.js). */
private fun whenText(step: GuideDay, sessions: List<Session>, zone: kotlinx.datetime.TimeZone, multiDay: Boolean): String? {
    if (step.match.isEmpty()) return null
    val found = sessions.mapNotNull { s -> EventClock.startOf(s)?.let { s to it } }.sortedBy { it.second }.firstOrNull { (s, _) -> Moments.matches(step.match, step.talk, s) } ?: return null
    val (session, start) = found
    val day = if (multiDay) EventClock.day(start, zone).dayOfWeek.name.take(3).lowercase().replaceFirstChar { it.uppercase() } + " " else ""

    return "At this WordCamp: $day${EventClock.time(start, zone)}" + (session.trackNames.firstOrNull()?.let { " · $it" } ?: "")
}

@Composable
private fun DayStep(step: GuideDay, whenText: String?, last: Boolean) {
    val brand = LocalBrand.current
    Row(Modifier.fillMaxWidth().height(IntrinsicSize.Min), horizontalArrangement = Arrangement.spacedBy(12.dp)) {
        Column(horizontalAlignment = Alignment.CenterHorizontally) {
            Box(
                Modifier.size(44.dp).background(brand.line, CircleShape).padding(1.dp).background(brand.background, CircleShape).padding(2.dp).background(brand.peach, CircleShape),
                contentAlignment = Alignment.Center,
            ) { LineIcon(step.icon, brand.primary, size = 22.dp) }
            if (!last) Box(Modifier.weight(1f).width(2.dp).background(brand.line))
        }
        Column(Modifier.weight(1f).padding(top = 8.dp, bottom = 20.dp)) {
            Text(step.title, style = MaterialTheme.typography.titleMedium, modifier = Modifier.padding(bottom = 4.dp))
            whenText?.let {
                Text(
                    it, style = MaterialTheme.typography.labelMedium.copy(color = Color.White),
                    modifier = Modifier.padding(bottom = 8.dp).background(brand.primary, RoundedCornerShape(10.dp)).padding(horizontal = 8.dp, vertical = 3.dp),
                )
            }
            Text(step.what, style = MaterialTheme.typography.bodyMedium, modifier = Modifier.padding(bottom = 8.dp))
            if (step.tip.isNotBlank()) {
                Row(Modifier.fillMaxWidth().height(IntrinsicSize.Min)) {
                    Box(Modifier.fillMaxHeight().width(3.dp).background(brand.accent))
                    Text(
                        buildAnnotatedString { withStyle(SpanStyle(fontWeight = FontWeight.Bold, color = brand.ink)) { append("Tip:") }; append(" " + step.tip) },
                        style = MaterialTheme.typography.bodyMedium.copy(color = brand.muted),
                        modifier = Modifier.weight(1f).background(brand.background, RoundedCornerShape(topEnd = 4.dp, bottomEnd = 4.dp)).padding(horizontal = 12.dp, vertical = 8.dp),
                    )
                }
            }
        }
    }
}

@Composable
private fun Accordion(items: List<Pair<String, String>>) {
    val brand = LocalBrand.current
    Card(Modifier.padding(0.dp)) {
        Column {
            items.forEachIndexed { i, (title, body) ->
                var open by remember(title) { mutableStateOf(false) }
                Row(
                    Modifier.fillMaxWidth().heightIn(min = 48.dp).clickable(role = Role.Button) { open = !open }.padding(vertical = 12.dp),
                    horizontalArrangement = Arrangement.SpaceBetween, verticalAlignment = Alignment.CenterVertically,
                ) {
                    Text(title, style = MaterialTheme.typography.bodyLarge.copy(fontWeight = FontWeight.Bold), modifier = Modifier.weight(1f))
                    Text(if (open) "×" else "+", style = MaterialTheme.typography.titleLarge.copy(color = brand.primary, fontWeight = FontWeight.Normal, fontSize = 22.sp), modifier = Modifier.padding(start = 12.dp))
                }
                if (open) Text(body, style = MaterialTheme.typography.bodyMedium.copy(color = brand.muted), modifier = Modifier.padding(bottom = 12.dp))
                if (i < items.lastIndex) Box(Modifier.fillMaxWidth().height(1.dp).background(brand.line))
            }
        }
    }
}

@Composable
private fun TipCard(icon: String, title: String, text: String) {
    val brand = LocalBrand.current
    Box(Modifier.padding(bottom = 12.dp)) {
        Card {
            Row(horizontalArrangement = Arrangement.spacedBy(12.dp), verticalAlignment = Alignment.Top) {
                Box(Modifier.size(24.dp), contentAlignment = Alignment.Center) { LineIcon(icon, brand.primary, size = 24.dp) }
                Column(Modifier.weight(1f)) {
                    Text(title, style = MaterialTheme.typography.titleMedium, modifier = Modifier.padding(bottom = 4.dp))
                    Text(text, style = MaterialTheme.typography.bodyMedium.copy(color = brand.muted))
                }
            }
        }
    }
}

@Composable
private fun BringCard(items: List<String>) {
    val brand = LocalBrand.current
    Card {
        Column {
            items.forEachIndexed { i, item ->
                Row(
                    Modifier.fillMaxWidth().padding(vertical = 8.dp).let { if (i < items.lastIndex) it.drawBehind {
                        drawLine(brand.line, Offset(0f, size.height), Offset(size.width, size.height), 1.dp.toPx(), pathEffect = androidx.compose.ui.graphics.PathEffect.dashPathEffect(floatArrayOf(6f, 6f)))
                    } else it },
                    horizontalArrangement = Arrangement.spacedBy(12.dp),
                ) {
                    Text("✓", style = MaterialTheme.typography.bodyMedium.copy(color = brand.success, fontWeight = FontWeight.ExtraBold))
                    Text(item, style = MaterialTheme.typography.bodyMedium)
                }
            }
        }
    }
}

@Composable
private fun NextStep(icon: String, title: String, desc: String, onClick: () -> Unit) {
    val brand = LocalBrand.current
    Box(Modifier.padding(bottom = 12.dp)) {
        Card(onClick = onClick) {
            Row(horizontalArrangement = Arrangement.spacedBy(12.dp), verticalAlignment = Alignment.CenterVertically) {
                LineIcon(icon, brand.primary, size = 22.dp)
                Column(Modifier.weight(1f)) {
                    Text(title, style = MaterialTheme.typography.titleMedium)
                    Text(desc, style = MaterialTheme.typography.bodyMedium.copy(color = brand.muted), modifier = Modifier.padding(top = 4.dp))
                }
            }
        }
    }
}
