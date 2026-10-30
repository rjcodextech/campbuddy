package club.campbuddy.shared.data

import club.campbuddy.shared.model.AppConfig
import club.campbuddy.shared.model.ContributorTable
import club.campbuddy.shared.model.Deal
import club.campbuddy.shared.model.FreeSteal
import club.campbuddy.shared.model.DelayInfo
import club.campbuddy.shared.model.DiscoveryCard
import club.campbuddy.shared.model.Guide
import club.campbuddy.shared.model.InfoGroup
import club.campbuddy.shared.model.LegalDoc
import club.campbuddy.shared.model.Memories
import club.campbuddy.shared.model.Quest
import club.campbuddy.shared.model.RosterEntry
import club.campbuddy.shared.model.Sponsor
import club.campbuddy.shared.model.EventCard
import club.campbuddy.shared.model.EventDetail
import club.campbuddy.shared.model.Session
import club.campbuddy.shared.model.Speaker
import kotlinx.coroutines.async
import kotlinx.coroutines.awaitAll
import kotlinx.serialization.builtins.ListSerializer
import kotlinx.serialization.encodeToString

/** Small persistent storage: SharedPreferences on Android, NSUserDefaults on iOS. */
interface KeyValueStore {
    fun get(key: String): String?
    fun put(key: String, value: String)
    fun remove(key: String)
}

/** What a screen shows, and whether it is a saved copy because the network failed. */
data class Loaded<T>(val value: T, val fromCache: Boolean)

/**
 * Offline-first reads: try the server, keep the answer on the phone, and fall back to the last
 * saved answer when the server cannot be reached. A screen never sees a half result.
 */
class Repository(private val api: ApiClient, private val store: KeyValueStore) {

    /** Bundled default first (the very first launch, offline), then the last good copy, then fresh. */
    fun cachedConfig(): AppConfig {
        val saved = store.get(KEY_CONFIG)?.let { runCatching { AppJson.decodeFromString<AppConfig>(it) }.getOrNull() }

        return saved ?: AppJson.decodeFromString(DEFAULT_CONFIG_JSON)
    }

    suspend fun freshConfig(): AppConfig? = runCatching { api.config() }.getOrNull()?.also {
        // A document this app cannot make sense of is ignored: the last good one stays.
        if (it.tabs.isNotEmpty()) store.put(KEY_CONFIG, AppJson.encodeToString(it))
    }?.takeIf { it.tabs.isNotEmpty() }

    suspend fun events(): Loaded<List<EventCard>> = cached("events", ListSerializer(EventCard.serializer())) { api.events() }

    suspend fun event(slug: String): Loaded<EventDetail> = cached("event:$slug", EventDetail.serializer()) { api.event(slug) }

    suspend fun sessions(slug: String): Loaded<List<Session>> = cached("sessions:$slug", ListSerializer(Session.serializer())) { api.sessions(slug) }

    suspend fun sponsors(slug: String): Loaded<List<Sponsor>> = cached("sponsors:$slug", ListSerializer(Sponsor.serializer())) { api.sponsors(slug) }

    suspend fun deals(slug: String): Loaded<List<Deal>> = cached("deals:$slug", ListSerializer(Deal.serializer())) { api.deals(slug) }

    suspend fun freeSteals(slug: String): Loaded<List<FreeSteal>> = cached("steals:$slug", ListSerializer(FreeSteal.serializer())) { api.freeSteals(slug) }

    suspend fun quests(slug: String): Loaded<List<Quest>> = cached("quests:$slug", ListSerializer(Quest.serializer())) { api.quests(slug) }

    suspend fun tables(slug: String): Loaded<List<ContributorTable>> = cached("tables:$slug", ListSerializer(ContributorTable.serializer())) { api.tables(slug) }

    suspend fun delays(slug: String): Loaded<List<DelayInfo>> = cached("delay:$slug", ListSerializer(DelayInfo.serializer())) { api.eventDelays(slug) }

    suspend fun discovery(slug: String): Loaded<List<DiscoveryCard>> = cached("discovery:$slug", ListSerializer(DiscoveryCard.serializer())) { api.discovery(slug) }

    suspend fun speakers(slug: String): Loaded<List<Speaker>> = cached("speakers:$slug", ListSerializer(Speaker.serializer())) { api.speakers(slug) }

    suspend fun info(slug: String): Loaded<List<InfoGroup>> = cached("info:$slug", ListSerializer(InfoGroup.serializer())) { api.info(slug) }

    suspend fun guide(slug: String): Loaded<Guide> = cached("guide:$slug", Guide.serializer()) { api.guide(slug) }

    suspend fun memories(slug: String): Loaded<Memories> = cached("memories:$slug", Memories.serializer()) { api.memories(slug) }

    suspend fun roster(slug: String): Loaded<List<RosterEntry>> = cached("roster:$slug", ListSerializer(RosterEntry.serializer())) { api.roster(slug) }

    suspend fun legal(slug: String): Loaded<LegalDoc> = cached("legal:$slug", LegalDoc.serializer()) { api.legal(slug) }

    /**
     * The whole event saved on the phone in one go (like the website's offline warm-up), so a screen nobody
     * opened yet still has its data with no connection. Quietly does nothing offline; at most every 30 minutes.
     */
    suspend fun warmUp(slug: String, now: Long = kotlinx.datetime.Clock.System.now().epochSeconds) {
        val last = store.get("warm:$slug")?.toLongOrNull() ?: 0L
        if (now - last < WARM_EVERY_SECONDS) return
        val loads: List<suspend () -> Any> = listOf(
            { event(slug) }, { sessions(slug) }, { speakers(slug) }, { sponsors(slug) }, { deals(slug) }, { freeSteals(slug) },
            { quests(slug) }, { tables(slug) }, { info(slug) }, { guide(slug) }, { roster(slug) }, { delays(slug) },
        )
        val done = kotlinx.coroutines.coroutineScope {
            loads.map { load -> async { runCatching { load() }.getOrNull() } }.awaitAll()
        }
        // Only a run that reached the server counts; offline, the next open tries again.
        if (done.any { (it as? Loaded<*>)?.fromCache == false }) store.put("warm:$slug", now.toString())
    }

    /**
     * The server's answer, saved for offline use. With a saved copy already on the phone a slow network
     * (venue wifi that connects but barely moves) gets [SLOW_NETWORK_MS]: then the saved copy shows and the
     * answer, when it comes, is saved for next time. With no saved copy it waits as long as it takes.
     */
    private suspend fun <T> cached(key: String, serializer: kotlinx.serialization.KSerializer<T>, fetch: suspend () -> T): Loaded<T> {
        val saved = store.get("cache:$key")?.let { runCatching { AppJson.decodeFromString(serializer, it) }.getOrNull() }
        val fresh = background.async {
            fetch().also { store.put("cache:$key", AppJson.encodeToString(serializer, it)) }
        }
        if (saved == null) return Loaded(fresh.await(), fromCache = false)

        val outcome: Result<T>? = kotlinx.coroutines.withTimeoutOrNull(SLOW_NETWORK_MS) {
            try {
                Result.success(fresh.await())
            } catch (stop: kotlinx.coroutines.CancellationException) {
                throw stop
            } catch (error: Exception) {
                Result.failure(error)
            }
        }

        return when {
            // Slow, not offline: the saved copy shows quietly, like the website's service worker does.
            outcome == null -> Loaded(saved, fromCache = false)
            outcome.isSuccess -> Loaded(outcome.getOrThrow(), fromCache = false)
            // The server could not be reached: the saved copy, with the "you are offline" line.
            else -> Loaded(saved, fromCache = true)
        }
    }

    /** Refreshes outlive the screen that asked (a slow answer is still saved). */
    private val background = kotlinx.coroutines.CoroutineScope(kotlinx.coroutines.SupervisorJob() + kotlinx.coroutines.Dispatchers.Default)

    // ---- organizer token (kept in the platform secure store by the app shell) ----

    fun organizerToken(): String? = store.get(KEY_TOKEN)

    fun saveOrganizer(token: String, role: String, name: String) {
        store.put(KEY_TOKEN, token)
        store.put(KEY_ROLE, role)
        store.put(KEY_NAME, name)
    }

    fun organizerRole(): String? = store.get(KEY_ROLE)

    fun organizerName(): String? = store.get(KEY_NAME)

    fun forgetOrganizer() {
        store.remove(KEY_TOKEN)
        store.remove(KEY_ROLE)
        store.remove(KEY_NAME)
    }

    private companion object {
        const val SLOW_NETWORK_MS = 4_000L
        const val WARM_EVERY_SECONDS = 30 * 60L
        const val KEY_CONFIG = "config"
        const val KEY_TOKEN = "organizer.token"
        const val KEY_ROLE = "organizer.role"
        const val KEY_NAME = "organizer.name"
    }
}
