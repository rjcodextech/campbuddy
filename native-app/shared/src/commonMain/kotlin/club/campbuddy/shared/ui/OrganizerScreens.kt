package club.campbuddy.shared.ui

import androidx.compose.foundation.background
import androidx.compose.foundation.layout.Arrangement
import androidx.compose.foundation.layout.Column
import androidx.compose.foundation.layout.PaddingValues
import androidx.compose.foundation.layout.Row
import androidx.compose.foundation.layout.fillMaxSize
import androidx.compose.foundation.layout.fillMaxWidth
import androidx.compose.foundation.layout.padding
import androidx.compose.foundation.layout.statusBarsPadding
import androidx.compose.foundation.lazy.LazyColumn
import androidx.compose.foundation.lazy.items
import androidx.compose.foundation.shape.RoundedCornerShape
import androidx.compose.foundation.text.KeyboardOptions
import androidx.compose.material3.FilterChip
import androidx.compose.material3.MaterialTheme
import androidx.compose.material3.OutlinedTextField
import androidx.compose.material3.Text
import androidx.compose.runtime.Composable
import androidx.compose.runtime.getValue
import androidx.compose.runtime.mutableStateOf
import androidx.compose.runtime.remember
import androidx.compose.runtime.rememberCoroutineScope
import androidx.compose.runtime.setValue
import androidx.compose.ui.Modifier
import androidx.compose.ui.text.input.KeyboardType
import androidx.compose.ui.text.input.PasswordVisualTransformation
import androidx.compose.ui.unit.dp
import club.campbuddy.shared.PlatformBackHandler
import club.campbuddy.shared.data.ApiClient
import club.campbuddy.shared.data.ApiException
import club.campbuddy.shared.data.Loaded
import club.campbuddy.shared.data.Repository
import club.campbuddy.shared.domain.EventClock
import club.campbuddy.shared.model.DelayRequest
import club.campbuddy.shared.model.OrganizerEvent
import kotlinx.coroutines.launch
import kotlinx.datetime.Clock
import kotlinx.datetime.toLocalDateTime

/** Admin / Event manager sign-in. The token goes to the platform store; the password is never kept. */
@Composable
fun OrganizerLoginScreen(api: ApiClient, repository: Repository, deviceName: String, onSignedIn: () -> Unit, onBack: () -> Unit) {
    val brand = LocalBrand.current
    val scope = rememberCoroutineScope()
    var email by remember { mutableStateOf("") }
    var password by remember { mutableStateOf("") }
    var role by remember { mutableStateOf("manager") }
    var busy by remember { mutableStateOf(false) }
    var error by remember { mutableStateOf<String?>(null) }

    PlatformBackHandler(true, onBack)

    Column(Modifier.fillMaxSize().background(brand.background).statusBarsPadding().padding(16.dp), verticalArrangement = Arrangement.spacedBy(12.dp)) {
        Text("Event staff sign-in", style = MaterialTheme.typography.headlineSmall)
        Text("For organizers and event managers. Attendees do not need to sign in.", style = MaterialTheme.typography.bodyMedium)
        Row(horizontalArrangement = Arrangement.spacedBy(8.dp)) {
            CbChip(selected = role == "manager", onClick = { role = "manager" }, label = { Text("Event manager") })
            CbChip(selected = role == "admin", onClick = { role = "admin" }, label = { Text("Admin") })
        }
        OutlinedTextField(email, { email = it }, Modifier.fillMaxWidth(), label = { Text("E-mail") }, singleLine = true, keyboardOptions = KeyboardOptions(keyboardType = KeyboardType.Email), shape = RoundedCornerShape(brand.radius.dp))
        OutlinedTextField(password, { password = it }, Modifier.fillMaxWidth(), label = { Text("Password") }, singleLine = true, visualTransformation = PasswordVisualTransformation(), keyboardOptions = KeyboardOptions(keyboardType = KeyboardType.Password), shape = RoundedCornerShape(brand.radius.dp))
        error?.let { Text(it, style = MaterialTheme.typography.bodyMedium.copy(color = androidx.compose.ui.graphics.Color(0xFFB3261E))) }
        PrimaryButton(if (busy) "Signing in…" else "Sign in", enabled = !busy && email.isNotBlank() && password.isNotBlank(), modifier = Modifier.fillMaxWidth(), onClick = {
            busy = true
            error = null
            scope.launch {
                try {
                    val login = api.login(email.trim(), password, role, deviceName)
                    repository.saveOrganizer(login.token, login.role, login.user.name)
                    onSignedIn()
                } catch (e: ApiException) {
                    error = e.message
                } catch (e: Exception) {
                    error = "Could not reach CampBuddy. Check your connection."
                } finally {
                    busy = false
                }
            }
        })
        QuietButton("Back", onBack, Modifier.fillMaxWidth())
    }
}

/** The events you run, with how many reports are waiting. */
@Composable
fun OrganizerHomeScreen(api: ApiClient, repository: Repository, onEvent: (OrganizerEvent) -> Unit, onSignedOut: () -> Unit, onBack: () -> Unit) {
    val brand = LocalBrand.current
    val token = repository.organizerToken().orEmpty()
    val scope = rememberCoroutineScope()
    val loader = rememberLoader("organizer-events") { Loaded(api.organizerEvents(token), false) }
    val state by loader.state

    PlatformBackHandler(true, onBack)

    LazyColumn(Modifier.fillMaxSize().background(brand.background).statusBarsPadding(), contentPadding = PaddingValues(16.dp), verticalArrangement = Arrangement.spacedBy(12.dp)) {
        item {
            Text("Hello, ${repository.organizerName() ?: "organizer"}", style = MaterialTheme.typography.headlineSmall)
            Text((repository.organizerRole() ?: "").replaceFirstChar { it.uppercase() }, style = MaterialTheme.typography.bodySmall)
        }
        when (val s = state) {
            LoadState.Loading -> item { Loading() }
            is LoadState.Failed -> item {
                if (s.message.contains("Sign in again")) {
                    repository.forgetOrganizer()
                    onSignedOut()
                } else {
                    ErrorState(s.message, loader.reload)
                }
            }
            is LoadState.Ready -> {
                if (s.value.isEmpty()) item { Text("No events yet.", style = MaterialTheme.typography.bodyMedium) }
                items(s.value, key = { it.id }) { event ->
                    Card(onClick = { onEvent(event) }) {
                        Column(verticalArrangement = Arrangement.spacedBy(4.dp)) {
                            Text(event.name, style = MaterialTheme.typography.titleMedium)
                            Text(listOfNotNull(event.startsOn, event.status).joinToString(" · "), style = MaterialTheme.typography.bodyMedium)
                            if (event.openReports > 0) Text("${event.openReports} report(s) waiting", style = MaterialTheme.typography.labelMedium.copy(color = brand.primary))
                        }
                    }
                }
            }
        }
        item {
            QuietButton("Sign out", {
                scope.launch {
                    runCatching { api.logout(token) }
                    repository.forgetOrganizer()
                    onSignedOut()
                }
            }, Modifier.fillMaxWidth())
        }
    }
}

/** Reports on profiles and "running late" for one event. */
@Composable
fun OrganizerEventScreen(api: ApiClient, repository: Repository, event: OrganizerEvent, onBack: () -> Unit) {
    val brand = LocalBrand.current
    val token = repository.organizerToken().orEmpty()
    val scope = rememberCoroutineScope()
    val reports = rememberLoader("reports:${event.id}") { Loaded(api.reports(token, event.id), false) }
    val delay = rememberLoader("delay:${event.id}") { Loaded(api.delay(token, event.id), false) }
    val reportState by reports.state
    val delayState by delay.state
    var minutes by remember { mutableStateOf("15") }
    var note by remember { mutableStateOf("") }
    var message by remember { mutableStateOf<String?>(null) }

    PlatformBackHandler(true, onBack)

    LazyColumn(Modifier.fillMaxSize().background(brand.background).statusBarsPadding(), contentPadding = PaddingValues(16.dp), verticalArrangement = Arrangement.spacedBy(12.dp)) {
        item {
            Text(event.name, style = MaterialTheme.typography.headlineSmall)
            QuietButton("Back", onBack, Modifier.padding(top = 8.dp))
        }
        message?.let { text -> item { Text(text, style = MaterialTheme.typography.bodyMedium) } }

        item { SectionTitle("Reports") }
        when (val s = reportState) {
            LoadState.Loading -> item { Loading() }
            is LoadState.Failed -> item { ErrorState(s.message, reports.reload) }
            is LoadState.Ready -> {
                if (s.value.open.isEmpty()) item { Text("No open reports.", style = MaterialTheme.typography.bodyMedium) }
                items(s.value.open, key = { it.discoveryId }) { report ->
                    Card {
                        Column(verticalArrangement = Arrangement.spacedBy(6.dp)) {
                            Text(report.profile?.name ?: "Anonymous attendee", style = MaterialTheme.typography.titleMedium)
                            report.profile?.profession?.let { Text(it, style = MaterialTheme.typography.bodySmall) }
                            report.reports.forEach { Text(it.label + (it.note?.let { n -> " · $n" } ?: ""), style = MaterialTheme.typography.bodyMedium) }
                            Row(horizontalArrangement = Arrangement.spacedBy(8.dp)) {
                                if (report.profileStillThere) {
                                    PrimaryButton("Remove profile", {
                                        scope.launch { runCatching { api.removeReported(token, event.id, report.discoveryId) }; reports.reload() }
                                    })
                                }
                                QuietButton("Dismiss", { scope.launch { runCatching { api.dismissReported(token, event.id, report.discoveryId) }; reports.reload() } })
                            }
                        }
                    }
                }
            }
        }

        item { SectionTitle("Running late") }
        when (val s = delayState) {
            LoadState.Loading -> item { Loading() }
            is LoadState.Failed -> item { ErrorState(s.message, delay.reload) }
            is LoadState.Ready -> item {
                Card {
                    Column(verticalArrangement = Arrangement.spacedBy(8.dp)) {
                        if (s.value.active.isEmpty()) Text("On time.", style = MaterialTheme.typography.bodyMedium)
                        s.value.active.forEach { Text(it.text ?: "${it.minutes} min late", style = MaterialTheme.typography.bodyMedium) }
                        OutlinedTextField(minutes, { minutes = it.filter(Char::isDigit).take(3) }, Modifier.fillMaxWidth(), label = { Text("Minutes late") }, singleLine = true, keyboardOptions = KeyboardOptions(keyboardType = KeyboardType.Number), shape = RoundedCornerShape(brand.radius.dp))
                        OutlinedTextField(note, { note = it.take(140) }, Modifier.fillMaxWidth(), label = { Text("Note for attendees (optional)") }, singleLine = true, shape = RoundedCornerShape(brand.radius.dp))
                        Row(horizontalArrangement = Arrangement.spacedBy(8.dp)) {
                            PrimaryButton("Set delay", {
                                val zone = EventClock.zone(s.value.timezone)
                                val local = Clock.System.now().toLocalDateTime(zone)
                                val from = "${local.year}-${local.monthNumber.toString().padStart(2, '0')}-${local.dayOfMonth.toString().padStart(2, '0')}T${local.hour.toString().padStart(2, '0')}:${local.minute.toString().padStart(2, '0')}"
                                scope.launch {
                                    message = try {
                                        api.setDelay(token, event.id, DelayRequest(minutes.toIntOrNull() ?: 0, "", from, note.ifBlank { null }))
                                        "Saved. Every phone sees the new times."
                                    } catch (e: ApiException) {
                                        e.message
                                    }
                                    delay.reload()
                                }
                            })
                            QuietButton("Back on time", { scope.launch { runCatching { api.clearDelay(token, event.id) }; message = "Back on time."; delay.reload() } })
                        }
                    }
                }
            }
        }
    }
}
