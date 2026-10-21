package club.campbuddy.shared.data

import club.campbuddy.shared.model.AppConfig
import club.campbuddy.shared.model.ContributorTable
import club.campbuddy.shared.model.Deal
import club.campbuddy.shared.model.FreeSteal
import club.campbuddy.shared.model.Guide
import club.campbuddy.shared.model.InfoGroup
import club.campbuddy.shared.model.LegalDoc
import club.campbuddy.shared.model.Memories
import club.campbuddy.shared.model.Quest
import club.campbuddy.shared.model.RosterPage
import club.campbuddy.shared.model.Sponsor
import club.campbuddy.shared.model.DiscoveryCard
import club.campbuddy.shared.model.DiscoveryForm
import club.campbuddy.shared.model.DiscoveryJoined
import club.campbuddy.shared.model.MessageRequest
import club.campbuddy.shared.model.ReportRequest
import club.campbuddy.shared.model.WaveRequest
import club.campbuddy.shared.model.WaveState
import io.ktor.client.request.delete
import io.ktor.client.request.request
import club.campbuddy.shared.model.Data
import club.campbuddy.shared.model.LeadRequest
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
import club.campbuddy.shared.model.ActivityPage
import club.campbuddy.shared.model.AdminDashboard
import club.campbuddy.shared.model.ConfirmBody
import club.campbuddy.shared.model.DealForm
import club.campbuddy.shared.model.DealsData
import club.campbuddy.shared.model.DetailsForm
import club.campbuddy.shared.model.ErrorsPage
import club.campbuddy.shared.model.EventDetails
import club.campbuddy.shared.model.EventInformation
import club.campbuddy.shared.model.EventOption
import club.campbuddy.shared.model.FeedbackPage
import club.campbuddy.shared.model.FetchResult
import club.campbuddy.shared.model.HiddenBody
import club.campbuddy.shared.model.LeadsPage
import club.campbuddy.shared.model.ManagerForm
import club.campbuddy.shared.model.Msg
import club.campbuddy.shared.model.NewEventForm
import club.campbuddy.shared.model.NotificationRow
import club.campbuddy.shared.model.OrgDeal
import club.campbuddy.shared.model.OrgManager
import club.campbuddy.shared.model.OrgQuest
import club.campbuddy.shared.model.OrgTable
import club.campbuddy.shared.model.OrganizerAttendeeEntry
import club.campbuddy.shared.model.Paged
import club.campbuddy.shared.model.QuestForm
import club.campbuddy.shared.model.Reply
import club.campbuddy.shared.model.TableForm
import club.campbuddy.shared.model.TablesData
import io.ktor.client.HttpClient
import io.ktor.http.encodeURLParameter
import io.ktor.client.plugins.timeout
import io.ktor.client.request.forms.submitFormWithBinaryData
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

@kotlinx.serialization.Serializable
data class ShareReply(@kotlinx.serialization.SerialName("share_id") val shareId: String, @kotlinx.serialization.SerialName("owner_token") val ownerToken: String)

@kotlinx.serialization.Serializable
internal data class PushRegistration(
    val token: String,
    @kotlinx.serialization.SerialName("device_id") val deviceId: String,
    val platform: String,
    @kotlinx.serialization.SerialName("app_version") val appVersion: String,
)

/** An answer the server gave that was not a success (the message is the server own, in plain words). */
class ApiException(val status: Int, override val message: String) : Exception(message)

/** How long the organizer calls that run a fetch on the server may take. */
private const val LONG_WAIT = 130_000L

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

    suspend fun legal(slug: String): LegalDoc = get("/legal/$slug")

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

    suspend fun info(slug: String): List<InfoGroup> = get<Data<List<InfoGroup>>>("/app/events/$slug/info").data

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

    // ---- attendee discovery (the owner token is the only credential) ----

    suspend fun discovery(slug: String): List<DiscoveryCard> = get<Data<List<DiscoveryCard>>>("/events/$slug/discovery").data

    suspend fun joinDiscovery(slug: String, form: DiscoveryForm): DiscoveryJoined =
        write<DiscoveryJoined>(io.ktor.http.HttpMethod.Post, "/events/$slug/discovery", null, form)

    suspend fun updateDiscovery(slug: String, id: String, token: String, form: DiscoveryForm) {
        write<DiscoveryCard>(io.ktor.http.HttpMethod.Patch, "/events/$slug/discovery/$id", token, form)
    }

    suspend fun leaveDiscovery(slug: String, id: String, token: String) {
        checked(http.delete("$baseUrl/events/$slug/discovery/$id") { common(); bearerAuth(token) })
    }

    suspend fun waves(slug: String, id: String, token: String): WaveState = get("/events/$slug/discovery/$id/waves", token)

    suspend fun wave(slug: String, id: String, token: String, request: WaveRequest): WaveState =
        write(io.ktor.http.HttpMethod.Post, "/events/$slug/discovery/$id/waves", token, request)

    suspend fun message(slug: String, id: String, token: String, request: MessageRequest): WaveState =
        write(io.ktor.http.HttpMethod.Post, "/events/$slug/discovery/$id/messages", token, request)

    suspend fun takeBackWave(slug: String, id: String, token: String, target: String): WaveState {
        val response = http.delete("$baseUrl/events/$slug/discovery/$id/waves/$target") { common(); bearerAuth(token) }
        return checked(response).body()
    }

    suspend fun reportDiscovery(slug: String, id: String, request: ReportRequest) {
        write<Unit>(io.ktor.http.HttpMethod.Post, "/events/$slug/discovery/$id/report", null, request)
    }

    /** Tells the server this phone's push token for one event (asked for only after the person said yes to notifications). */
    suspend fun registerPush(slug: String, token: String, deviceId: String) {
        write<Unit>(io.ktor.http.HttpMethod.Post, "/events/$slug/push/native-subscribe", null,
            PushRegistration(token = token, deviceId = deviceId, platform = platform, appVersion = appVersion))
    }

    /** The contact form a deal asks for before it opens: one lead per e-mail, the sponsor gets it. */
    suspend fun sendLead(slug: String, offerId: Long, request: LeadRequest) {
        write<Unit>(io.ktor.http.HttpMethod.Post, "/events/$slug/offers/$offerId/leads", null, request)
    }

    // ---- the Camp Card on the attendee list (only its owner token can change or remove it) ----

    suspend fun shareCard(slug: String, payload: kotlinx.serialization.json.JsonObject): ShareReply =
        write(io.ktor.http.HttpMethod.Post, "/events/$slug/camp-card-share", null, payload)

    suspend fun updateSharedCard(slug: String, shareId: String, token: String, payload: kotlinx.serialization.json.JsonObject) {
        write<Unit>(io.ktor.http.HttpMethod.Put, "/events/$slug/camp-card-share/$shareId", token, payload)
    }

    suspend fun removeSharedCard(slug: String, shareId: String, token: String) {
        checked(http.delete("$baseUrl/events/$slug/camp-card-share/$shareId") { common(); bearerAuth(token) })
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

    suspend fun organizerOverview(token: String, eventId: Long): club.campbuddy.shared.model.OrganizerOverview =
        get<Data<club.campbuddy.shared.model.OrganizerOverview>>("/app/organizer/events/$eventId/overview", token).data

    suspend fun updateOrganizerInfo(token: String, eventId: Long, request: club.campbuddy.shared.model.UpdateInfoRequest) {
        val response = http.post("$baseUrl/app/organizer/events/$eventId/info") {
            common()
            bearerAuth(token)
            contentType(ContentType.Application.Json)
            setBody(request)
        }
        checked(response)
    }

    suspend fun updateOrganizerMemories(token: String, eventId: Long, request: club.campbuddy.shared.model.UpdateMemoriesRequest) {
        val response = http.post("$baseUrl/app/organizer/events/$eventId/memories") {
            common()
            bearerAuth(token)
            contentType(ContentType.Application.Json)
            setBody(request)
        }
        checked(response)
    }

    suspend fun notifyOrganizerMemories(token: String, eventId: Long, kind: String): club.campbuddy.shared.model.NotifyMemoriesResponse {
        val response = http.post("$baseUrl/app/organizer/events/$eventId/memories/notify/$kind") {
            common()
            bearerAuth(token)
            contentType(ContentType.Application.Json)
        }
        return checked(response).body<Data<club.campbuddy.shared.model.NotifyMemoriesResponse>>().data
    }

    suspend fun organizerAttendees(token: String, eventId: Long, query: String = ""): List<club.campbuddy.shared.model.OrganizerAttendeeEntry> {
        val path = if (query.isBlank()) "/app/organizer/events/$eventId/attendees" else "/app/organizer/events/$eventId/attendees?q=${query.trim()}"
        return get<Data<List<club.campbuddy.shared.model.OrganizerAttendeeEntry>>>(path, token).data
    }

    suspend fun updateOrganizerAttendeeRoles(token: String, eventId: Long, entryId: Long, roles: List<String>) {
        val response = http.post("$baseUrl/app/organizer/events/$eventId/attendees/$entryId/roles") {
            common()
            bearerAuth(token)
            contentType(ContentType.Application.Json)
            setBody(club.campbuddy.shared.model.UpdateAttendeeRolesRequest(roles))
        }
        checked(response)
    }

    suspend fun logout(token: String) = send("/app/auth/logout", token)

    // ---- organizer: the web panel's event tabs (OrganizerManageController) ----

    /** The events list with the web panel's filters, soonest first; blank filters are left out. */
    suspend fun organizerEvents(token: String, q: String, status: String?, visibility: String?, whenFilter: String?): List<OrganizerEvent> =
        get<Data<List<OrganizerEvent>>>("/app/organizer/events" + query("order" to "soon", "q" to q, "status" to status, "visibility" to visibility, "when" to whenFilter), token).data

    suspend fun eventDetails(token: String, eventId: Long): EventDetails = get<Data<EventDetails>>("/app/organizer/events/$eventId/details", token).data

    suspend fun saveEventDetails(token: String, eventId: Long, form: DetailsForm): Reply<EventDetails> =
        write(io.ktor.http.HttpMethod.Post, "/app/organizer/events/$eventId/details", token, form)

    suspend fun deleteEvent(token: String, eventId: Long, slug: String): Msg =
        write(io.ktor.http.HttpMethod.Delete, "/app/organizer/events/$eventId", token, ConfirmBody(slug))

    /** Refresh now / fetch information / branding: the server runs it in the request, so this waits up to two minutes. */
    suspend fun runFetch(token: String, eventId: Long, job: String): FetchResult =
        write<Data<FetchResult>>(io.ktor.http.HttpMethod.Post, "/app/organizer/events/$eventId/fetch/$job", token, ConfirmBody(job), LONG_WAIT).data

    suspend fun eventInformation(token: String, eventId: Long): EventInformation = get<Data<EventInformation>>("/app/organizer/events/$eventId/information", token).data

    suspend fun saveEventInformation(token: String, eventId: Long, form: EventInformation): Reply<EventInformation> =
        write(io.ktor.http.HttpMethod.Post, "/app/organizer/events/$eventId/information", token, form)

    suspend fun orgQuests(token: String, eventId: Long): List<OrgQuest> = get<Data<List<OrgQuest>>>("/app/organizer/events/$eventId/quests", token).data

    suspend fun addQuest(token: String, eventId: Long, form: QuestForm): Reply<OrgQuest> =
        write(io.ktor.http.HttpMethod.Post, "/app/organizer/events/$eventId/quests", token, form)

    suspend fun updateQuest(token: String, eventId: Long, itemId: Long, form: QuestForm): Reply<OrgQuest> =
        write(io.ktor.http.HttpMethod.Patch, "/app/organizer/events/$eventId/quests/$itemId", token, form)

    suspend fun deleteQuest(token: String, eventId: Long, itemId: Long): Msg = remove("/app/organizer/events/$eventId/quests/$itemId", token)

    suspend fun orgTables(token: String, eventId: Long): TablesData = get<Data<TablesData>>("/app/organizer/events/$eventId/tables", token).data

    suspend fun addTable(token: String, eventId: Long, form: TableForm): Reply<OrgTable> =
        write(io.ktor.http.HttpMethod.Post, "/app/organizer/events/$eventId/tables", token, form)

    suspend fun updateTable(token: String, eventId: Long, itemId: Long, form: TableForm): Reply<OrgTable> =
        write(io.ktor.http.HttpMethod.Patch, "/app/organizer/events/$eventId/tables/$itemId", token, form)

    suspend fun deleteTable(token: String, eventId: Long, itemId: Long): Msg = remove("/app/organizer/events/$eventId/tables/$itemId", token)

    /** One page of the attendee list (100 a page), narrowed by name, role ("none" = no role) and, for an admin, suppressed ones. */
    suspend fun organizerAttendeePage(token: String, eventId: Long, q: String, role: String?, status: String?, page: Int): Paged<OrganizerAttendeeEntry> =
        get("/app/organizer/events/$eventId/attendees" + query("q" to q, "role" to role, "status" to status, "page" to page.toString()), token)

    /** suppress, unsuppress, release-claim or remove-card (admin). */
    suspend fun attendeeAction(token: String, eventId: Long, entryId: Long, action: String): Msg =
        write(io.ktor.http.HttpMethod.Post, "/app/organizer/events/$eventId/attendees/$entryId/$action", token, ConfirmBody(action))

    suspend fun eventNotifications(token: String, eventId: Long): List<NotificationRow> = get<Data<List<NotificationRow>>>("/app/organizer/events/$eventId/notifications", token).data

    suspend fun eventActivity(token: String, eventId: Long, page: Int): ActivityPage = get("/app/organizer/events/$eventId/activity" + query("page" to page.toString()), token)

    suspend fun orgDeals(token: String, eventId: Long): DealsData = get<Data<DealsData>>("/app/organizer/events/$eventId/deals", token).data

    suspend fun addDeal(token: String, eventId: Long, form: DealForm): Reply<OrgDeal> =
        write(io.ktor.http.HttpMethod.Post, "/app/organizer/events/$eventId/deals", token, form)

    suspend fun updateDeal(token: String, eventId: Long, itemId: Long, form: DealForm): Reply<OrgDeal> =
        write(io.ktor.http.HttpMethod.Patch, "/app/organizer/events/$eventId/deals/$itemId", token, form)

    suspend fun deleteDeal(token: String, eventId: Long, itemId: Long): Msg = remove("/app/organizer/events/$eventId/deals/$itemId", token)

    suspend fun dealVisibility(token: String, eventId: Long, itemId: Long, hidden: Boolean): Msg =
        write(io.ktor.http.HttpMethod.Post, "/app/organizer/events/$eventId/deals/$itemId/visibility", token, HiddenBody(hidden))

    suspend fun orgLeads(token: String, eventId: Long, offerId: Long?, from: String?, to: String?, page: Int): LeadsPage =
        get("/app/organizer/events/$eventId/leads" + query("offer_id" to offerId?.toString(), "from" to from, "to" to to, "page" to page.toString()), token)

    /** The deal leads as the website's CSV file (same filters), for the share sheet. */
    suspend fun exportLeads(token: String, eventId: Long, offerId: Long?, from: String?, to: String?): ByteArray {
        val response = http.get("$baseUrl/app/organizer/events/$eventId/leads/export" + query("offer_id" to offerId?.toString(), "from" to from, "to" to to)) {
            common()
            bearerAuth(token)
        }
        return checked(response).body()
    }

    /** Admin → Analytics (GA4): a period (or custom dates), a stream, bots hidden or not; [fresh] skips the hour's cache. */
    suspend fun adminAnalytics(token: String, period: String, from: String?, to: String?, stream: String?, hideBots: Boolean, fresh: Boolean): club.campbuddy.shared.model.AnalyticsData =
        get<Data<club.campbuddy.shared.model.AnalyticsData>>(
            "/app/organizer/admin/analytics" + query("period" to period, "from" to from, "to" to to, "stream" to stream, "hide_bots" to (if (hideBots) "1" else "0"), "fresh" to (if (fresh) "1" else null)),
            token,
        ).data

    // ---- live announcements (EventBroadcasts) ----

    /** "This phone has the event open": the organizers' announcements reach phones seen since three days before it. */
    suspend fun markSeen(slug: String, deviceId: String, platform: String) {
        write<Unit>(io.ktor.http.HttpMethod.Post, "/events/$slug/seen", null, club.campbuddy.shared.model.SeenBody(deviceId, platform))
    }

    /** The announcement sent in the last half hour, if any. */
    suspend fun liveNotice(slug: String): club.campbuddy.shared.model.LiveNotice? =
        get<Data<club.campbuddy.shared.model.LiveNotice?>>("/events/$slug/live-notice").data

    suspend fun broadcasts(token: String, eventId: Long): club.campbuddy.shared.model.BroadcastData =
        get<Data<club.campbuddy.shared.model.BroadcastData>>("/app/organizer/events/$eventId/broadcasts", token).data

    suspend fun sendBroadcast(token: String, eventId: Long, form: club.campbuddy.shared.model.BroadcastForm): Msg =
        write(io.ktor.http.HttpMethod.Post, "/app/organizer/events/$eventId/broadcasts", token, form)

    // ---- organizer: social media (OrganizerSocialController) ----

    suspend fun socialKit(token: String, eventId: Long): club.campbuddy.shared.model.SocialKitData =
        get<Data<club.campbuddy.shared.model.SocialKitData>>("/app/organizer/events/$eventId/social", token).data

    suspend fun saveSocialColors(token: String, eventId: Long, colors: club.campbuddy.shared.model.SocialColors): Msg =
        write(io.ktor.http.HttpMethod.Post, "/app/organizer/events/$eventId/social/colors", token, club.campbuddy.shared.model.ColorsBody(colors.primary, colors.secondary, colors.ink, colors.paper))

    /** Reads the WordCamp site's colours and artwork again (the server fetches the site, so it waits longer). */
    suspend fun refreshSiteTheme(token: String, eventId: Long): Msg =
        write(io.ktor.http.HttpMethod.Post, "/app/organizer/events/$eventId/social/site", token, ConfirmBody("site"), LONG_WAIT)

    /** use, remove or kind on one piece of artwork. */
    suspend fun artAction(token: String, eventId: Long, artAction: String, piece: String, kind: String? = null): Msg =
        write(io.ktor.http.HttpMethod.Post, "/app/organizer/events/$eventId/social/art/$artAction", token, club.campbuddy.shared.model.ArtBody(piece, kind))

    suspend fun saveWebhook(token: String, eventId: Long, url: String?): Msg =
        write(io.ktor.http.HttpMethod.Post, "/app/organizer/events/$eventId/social/webhook", token, club.campbuddy.shared.model.WebhookBody(url, remove = url == null))

    /** The drawn card (PNG) with its title and caption, to the event's Publish webhook. */
    suspend fun publishSocial(token: String, eventId: Long, png: ByteArray, title: String, caption: String, kind: String): Msg {
        val response = http.submitFormWithBinaryData(
            "$baseUrl/app/organizer/events/$eventId/social/publish",
            io.ktor.client.request.forms.formData {
                append("title", title)
                append("caption", caption)
                append("kind", kind)
                append("image", png, io.ktor.http.Headers.build {
                    append(io.ktor.http.HttpHeaders.ContentType, "image/png")
                    append(io.ktor.http.HttpHeaders.ContentDisposition, "filename=\"$kind.png\"")
                })
            },
        ) {
            common()
            bearerAuth(token)
            timeout { requestTimeoutMillis = LONG_WAIT; socketTimeoutMillis = LONG_WAIT }
        }
        return checked(response).body()
    }

    // ---- organizer: the admin's platform pages (OrganizerAdminController) ----

    suspend fun adminDashboard(token: String): AdminDashboard = get<Data<AdminDashboard>>("/app/organizer/admin/dashboard", token).data

    suspend fun adminErrors(token: String, period: String?, eventId: Long?, job: String?, result: String?, page: Int): ErrorsPage =
        get("/app/organizer/admin/errors" + query("period" to period, "event" to eventId?.toString(), "job" to job, "result" to result, "page" to page.toString()), token)

    suspend fun adminFeedback(token: String, eventId: Long?, page: Int): FeedbackPage =
        get("/app/organizer/admin/feedback" + query("event" to eventId?.toString(), "page" to page.toString()), token)

    suspend fun adminManagers(token: String, q: String): List<OrgManager> = get<Data<List<OrgManager>>>("/app/organizer/admin/managers" + query("q" to q), token).data

    suspend fun addManager(token: String, form: ManagerForm): Reply<OrgManager> = write(io.ktor.http.HttpMethod.Post, "/app/organizer/admin/managers", token, form)

    suspend fun updateManager(token: String, itemId: Long, form: ManagerForm): Reply<OrgManager> =
        write(io.ktor.http.HttpMethod.Patch, "/app/organizer/admin/managers/$itemId", token, form)

    suspend fun deleteManager(token: String, itemId: Long): Msg = remove("/app/organizer/admin/managers/$itemId", token)

    suspend fun adminActivity(token: String, managerId: Long?, eventId: Long?, page: Int): ActivityPage =
        get("/app/organizer/admin/activity" + query("manager" to managerId?.toString(), "event" to eventId?.toString(), "page" to page.toString()), token)

    suspend fun adminEventOptions(token: String): List<EventOption> = get<Data<List<EventOption>>>("/app/organizer/admin/event-options", token).data

    suspend fun addEvent(token: String, form: NewEventForm): Reply<OrganizerEvent> = write(io.ktor.http.HttpMethod.Post, "/app/organizer/admin/events", token, form)

    suspend fun discoverEvents(token: String): Msg = write(io.ktor.http.HttpMethod.Post, "/app/organizer/admin/events/discover", token, ConfirmBody("discover"))

    suspend fun purgeCache(token: String): Msg = write(io.ktor.http.HttpMethod.Post, "/app/organizer/admin/cache/purge", token, ConfirmBody("purge"), LONG_WAIT)

    suspend fun refreshAllData(token: String): Msg = write(io.ktor.http.HttpMethod.Post, "/app/organizer/admin/data/refresh", token, ConfirmBody("refresh"), LONG_WAIT * 2)


    // ---- plumbing ----

    private suspend inline fun <reified T> get(path: String, token: String? = null): T {
        val response = http.get("$baseUrl$path") {
            common()
            if (token != null) bearerAuth(token)
        }
        return checked(response).body()
    }

    /** A JSON write (POST/PATCH) that may answer with a body; [waitMillis] for the few that run long on the server. */
    private suspend inline fun <reified T> write(method: io.ktor.http.HttpMethod, path: String, token: String?, payload: Any, waitMillis: Long? = null): T {
        val response = http.request("$baseUrl$path") {
            this.method = method
            common()
            if (token != null) bearerAuth(token)
            if (waitMillis != null) timeout { requestTimeoutMillis = waitMillis; socketTimeoutMillis = waitMillis }
            contentType(ContentType.Application.Json)
            setBody(payload)
        }
        checked(response)
        @Suppress("UNCHECKED_CAST")
        return if (T::class == Unit::class) Unit as T else response.body()
    }

    private suspend fun remove(path: String, token: String): Msg {
        val response = http.delete("$baseUrl$path") { common(); bearerAuth(token) }
        return checked(response).body()
    }

    /** "?a=1&b=x" from the pairs that have a value (blank ones are left out). */
    private fun query(vararg pairs: Pair<String, String?>): String {
        val kept = pairs.filter { !it.second.isNullOrBlank() }
        if (kept.isEmpty()) return ""
        return "?" + kept.joinToString("&") { (k, v) -> k + "=" + v!!.trim().encodeURLParameter() }
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
        throw ApiException(response.status.value, errorMessage(text) ?: "Something went wrong (${response.status.value}).")
    }
}

/** The plain-words reason in a server error body: the first validation message, else its own message. */
internal fun errorMessage(body: String): String? = runCatching {
    val root = AppJson.parseToJsonElement(body) as? kotlinx.serialization.json.JsonObject ?: return null
    val errors = (root["errors"] as? kotlinx.serialization.json.JsonObject)?.values
        ?.firstNotNullOfOrNull { (it as? kotlinx.serialization.json.JsonArray)?.firstOrNull() as? kotlinx.serialization.json.JsonPrimitive }
        ?.content
    val error = ((root["error"] as? kotlinx.serialization.json.JsonObject)?.get("message") as? kotlinx.serialization.json.JsonPrimitive)?.content
    val message = (root["message"] as? kotlinx.serialization.json.JsonPrimitive)?.content

    errors ?: error ?: message
}.getOrNull()?.takeIf { it.isNotBlank() }
