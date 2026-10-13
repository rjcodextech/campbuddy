package club.campbuddy.shared.data

import club.campbuddy.shared.model.AppConfig
import club.campbuddy.shared.model.ContributorTable
import club.campbuddy.shared.model.Deal
import club.campbuddy.shared.model.FreeSteal
import club.campbuddy.shared.model.Guide
import club.campbuddy.shared.model.Memories
import club.campbuddy.shared.model.Quest
import club.campbuddy.shared.model.RosterPage
import club.campbuddy.shared.model.Sponsor
import club.campbuddy.shared.model.Data
import club.campbuddy.shared.model.DelayData
import club.campbuddy.shared.model.DelayInfo
import club.campbuddy.shared.model.DelayRequest
import club.campbuddy.shared.model.EventCard
import club.campbuddy.shared.model.EventDetail
import club.campbuddy.shared.model.LoginRequest
import club.campbuddy.shared.model.LoginResponse
import club.campbuddy.shared.model.OrganizerEvent
import club.campbuddy.shared.model.Person
import club.campbuddy.shared.model.ReportsData
import club.campbuddy.shared.model.Session
import club.campbuddy.shared.model.Speaker
import io.ktor.client.HttpClient
import io.ktor.client.call.body
import io.ktor.client.plugins.HttpTimeout
import io.ktor.client.plugins.contentnegotiation.ContentNegotiation
import io.ktor.client.request.bearerAuth
import io.ktor.client.request.get
import io.ktor.client.request.header
import io.ktor.client.request.post
import io.ktor.client.request.setBody
import io.ktor.client.statement.HttpResponse
import io.ktor.client.statement.bodyAsText
import io.ktor.http.ContentType
import io.ktor.http.HttpStatusCode
import io.ktor.http.contentType
import io.ktor.serialization.kotlinx.json.json
import kotlinx.serialization.json.Json

/** The one JSON setup: the server may add fields any time, and the app ignores what it does not know. */
val AppJson = Json {
    ignoreUnknownKeys = true
    coerceInputValues = true
    isLenient = true
}

/** An answer the server gave that was not a success (the message is the server own, in plain words). */
class ApiException(val status: Int, override val message: String) : Exception(message)

/** Talks to https://campbuddy.club/api/v1/app. Every call is a suspend function; none touches the UI. */
class ApiClient(
    private val baseUrl: String = "https://campbuddy.club/api/v1",
    private val appVersion: String = "2.0.0",
    private val platform: String = "android",
    client: HttpClient? = null,
) {
    private val http = client ?: HttpClient {
        install(ContentNegotiation) { json(AppJson) }
        install(HttpTimeout) {
            requestTimeoutMillis = 20_000
            connectTimeoutMillis = 10_000
        }
    }

    suspend fun config(): AppConfig = get("/app/config")

    suspend fun events(): List<EventCard> = get<Data<List<EventCard>>>("/app/events").data

    suspend fun event(slug: String): EventDetail = get<Data<EventDetail>>("/app/events/$slug").data

    suspend fun sessions(slug: String): List<Session> = get<Data<List<Session>>>("/app/events/$slug/sessions").data

    suspend fun sponsors(slug: String): List<Sponsor> = get<Data<List<Sponsor>>>("/app/events/$slug/sponsors").data

    suspend fun deals(slug: String): List<Deal> = get<Data<List<Deal>>>("/app/events/$slug/deals").data

    suspend fun freeSteals(slug: String): List<FreeSteal> = get<Data<List<FreeSteal>>>("/app/events/$slug/free-steals").data

    suspend fun quests(slug: String): List<Quest> = get<Data<List<Quest>>>("/app/events/$slug/quests").data

    suspend fun tables(slug: String): List<ContributorTable> = get<Data<List<ContributorTable>>>("/app/events/$slug/contributor-tables").data

    suspend fun eventDelays(slug: String): List<DelayInfo> = get<Data<List<DelayInfo>>>("/app/events/$slug/delay").data

    suspend fun speakers(slug: String): List<Speaker> = get<Data<List<Speaker>>>("/app/events/$slug/speakers").data

    suspend fun guide(slug: String): Guide = get<Data<Guide>>("/app/events/$slug/guide").data

    suspend fun memories(slug: String): Memories = get<Data<Memories>>("/app/events/$slug/memories").data

    /** The attendee list is paged (200 a page); this fetches every page. */
    suspend fun roster(slug: String): List<club.campbuddy.shared.model.RosterEntry> {
        val all = mutableListOf<club.campbuddy.shared.model.RosterEntry>()
        var page = 1
        do {
            val result = get<RosterPage>("/events/$slug/roster?page=$page")
            all += result.data
            page++
        } while (page <= result.lastPage && page <= 25)

        return all
    }

    // ---- organizer ----

    suspend fun login(email: String, password: String, role: String, device: String): LoginResponse {
        val response = http.post("$baseUrl/app/auth/login") {
            common()
            contentType(ContentType.Application.Json)
            setBody(LoginRequest(email, password, role, device))
        }
        return checked(response).body()
    }

    suspend fun me(token: String): Person = get<LoginResponse>("/app/organizer/me", token).user

    suspend fun organizerEvents(token: String): List<OrganizerEvent> = get<Data<List<OrganizerEvent>>>("/app/organizer/events", token).data

    suspend fun reports(token: String, eventId: Long): ReportsData = get<Data<ReportsData>>("/app/organizer/events/$eventId/reports", token).data

    suspend fun removeReported(token: String, eventId: Long, discoveryId: String) = send("/app/organizer/events/$eventId/reports/$discoveryId/remove", token)

    suspend fun dismissReported(token: String, eventId: Long, discoveryId: String) = send("/app/organizer/events/$eventId/reports/$discoveryId/dismiss", token)

    suspend fun delay(token: String, eventId: Long): DelayData = get<Data<DelayData>>("/app/organizer/events/$eventId/delay", token).data

    suspend fun setDelay(token: String, eventId: Long, request: DelayRequest) {
        val response = http.post("$baseUrl/app/organizer/events/$eventId/delay") {
            common()
            bearerAuth(token)
            contentType(ContentType.Application.Json)
            setBody(request)
        }
        checked(response)
    }

    suspend fun clearDelay(token: String, eventId: Long) = send("/app/organizer/events/$eventId/delay/clear", token)

    suspend fun logout(token: String) = send("/app/auth/logout", token)

    // ---- plumbing ----

    private suspend inline fun <reified T> get(path: String, token: String? = null): T {
        val response = http.get("$baseUrl$path") {
            common()
            if (token != null) bearerAuth(token)
        }
        return checked(response).body()
    }

    private suspend fun send(path: String, token: String) {
        checked(http.post("$baseUrl$path") {
            common()
            bearerAuth(token)
        })
    }

    private fun io.ktor.client.request.HttpRequestBuilder.common() {
        header("Accept", "application/json")
        header("X-App-Version", appVersion)
        header("X-Platform", platform)
        header("X-Schema", "1")
    }

    private suspend fun checked(response: HttpResponse): HttpResponse {
        if (response.status.value in 200..299 || response.status == HttpStatusCode.NotModified) return response

        // The server answers { "error": { "message": "…" } } or Laravel's { "message": "…" }.
        val text = runCatching { response.bodyAsText() }.getOrDefault("")
        val message = Regex("\"message\"\\s*:\\s*\"([^\"]+)\"").find(text)?.groupValues?.get(1) ?: "Something went wrong (${response.status.value})."
        throw ApiException(response.status.value, message)
    }
}
