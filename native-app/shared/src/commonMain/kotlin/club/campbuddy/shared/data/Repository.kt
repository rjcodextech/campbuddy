package club.campbuddy.shared.data

import club.campbuddy.shared.model.AppConfig
import club.campbuddy.shared.model.ContributorTable
import club.campbuddy.shared.model.Deal
import club.campbuddy.shared.model.DelayInfo
import club.campbuddy.shared.model.DiscoveryCard
import club.campbuddy.shared.model.EventCard
import club.campbuddy.shared.model.EventDetail
import club.campbuddy.shared.model.FreeSteal
import club.campbuddy.shared.model.Guide
import club.campbuddy.shared.model.InfoGroup
import club.campbuddy.shared.model.LegalDoc
import club.campbuddy.shared.model.Memories
import club.campbuddy.shared.model.Quest
import club.campbuddy.shared.model.RosterEntry
import club.campbuddy.shared.model.Session
import club.campbuddy.shared.model.Speaker
import club.campbuddy.shared.model.Sponsor
import kotlinx.coroutines.async
import kotlinx.coroutines.awaitAll
import kotlinx.coroutines.flow.MutableStateFlow
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
    /** Monotonic ticks observed by UI loaders when background cron syncs land new server data. */
    val catalogTick = MutableStateFlow(0)
    val eventTick = MutableStateFlow(0)

    private var lastSyncedCatalogAt = 0L
    private val lastSyncedEventAt = mutableMapOf<String, Long>()

    /** Bundled default first (the very first launch, offline), then the last good copy, then fresh. */
    fun cachedConfig(): AppConfig {
        val saved = store.get(KEY_CONFIG)?.let { runCatching { AppJson.decodeFromString<AppConfig>(it) }.getOrNull() }

        return saved ?: AppJson.decodeFromString(DEFAULT_CONFIG_JSON)
    }

    suspend fun freshConfig(): AppConfig? = runCatching { api.config() }.getOrNull()?.also {
        // A document this app cannot make sense of is ignored: the last good one stays.
        if (it.tabs.isNotEmpty()) store.put(KEY_CONFIG, AppJson.encodeToString(it))
    }?.takeIf { it.tabs.isNotEmpty() }

    suspend fun events(): Loaded<List<EventCard>> {
        val now = nowMillis()
        if (now - lastSyncedCatalogAt < SYNC_REUSE_MS) {
            store.get("cache:events")?.let { raw ->
                runCatching { AppJson.decodeFromString(ListSerializer(EventCard.serializer()), raw) }.getOrNull()
                    ?.let { return Loaded(it, fromCache = false) }
            }
        }
        return cached("events", ListSerializer(EventCard.serializer())) { api.events() }
    }

    suspend fun event(slug: String): Loaded<EventDetail> = eventCached(slug, "event:$slug", EventDetail.serializer()) {
        api.event(slug).also { detail ->
            detail.dataVersion?.takeIf { it.isNotBlank() }?.let { store.put("dataver:$slug", it) }
        }
    }

    suspend fun sessions(slug: String): Loaded<List<Session>> = eventCached(slug, "sessions:$slug", ListSerializer(Session.serializer())) { api.sessions(slug) }

    suspend fun sponsors(slug: String): Loaded<List<Sponsor>> = eventCached(slug, "sponsors:$slug", ListSerializer(Sponsor.serializer())) { api.sponsors(slug) }

    suspend fun deals(slug: String): Loaded<List<Deal>> = eventCached(slug, "deals:$slug", ListSerializer(Deal.serializer())) { api.deals(slug) }

    suspend fun freeSteals(slug: String): Loaded<List<FreeSteal>> = eventCached(slug, "steals:$slug", ListSerializer(FreeSteal.serializer())) { api.freeSteals(slug) }

    suspend fun quests(slug: String): Loaded<List<Quest>> = eventCached(slug, "quests:$slug", ListSerializer(Quest.serializer())) { api.quests(slug) }

    suspend fun tables(slug: String): Loaded<List<ContributorTable>> = eventCached(slug, "tables:$slug", ListSerializer(ContributorTable.serializer())) { api.tables(slug) }

    suspend fun delays(slug: String): Loaded<List<DelayInfo>> = eventCached(slug, "delay:$slug", ListSerializer(DelayInfo.serializer())) { api.eventDelays(slug) }

    suspend fun discovery(slug: String): Loaded<List<DiscoveryCard>> = eventCached(slug, "discovery:$slug", ListSerializer(DiscoveryCard.serializer())) { api.discovery(slug) }

    suspend fun speakers(slug: String): Loaded<List<Speaker>> = eventCached(slug, "speakers:$slug", ListSerializer(Speaker.serializer())) { api.speakers(slug) }

    suspend fun info(slug: String): Loaded<List<InfoGroup>> = eventCached(slug, "info:$slug", ListSerializer(InfoGroup.serializer())) { api.info(slug) }

    suspend fun guide(slug: String): Loaded<Guide> = eventCached(slug, "guide:$slug", Guide.serializer()) { api.guide(slug) }

    suspend fun memories(slug: String): Loaded<Memories> = eventCached(slug, "memories:$slug", Memories.serializer()) { api.memories(slug) }

    suspend fun roster(slug: String): Loaded<List<RosterEntry>> = eventCached(slug, "roster:$slug", ListSerializer(RosterEntry.serializer())) { api.roster(slug) }

    suspend fun legal(slug: String): Loaded<LegalDoc> = cached("legal:$slug", LegalDoc.serializer()) { api.legal(slug) }

    /**
     * Checks the server's catalog fingerprint (`GET /api/v1/events-version`) and refreshes
     * the event picker list & app config immediately whenever a cron publishes, archives, or updates an event.
     */
    suspend fun syncCatalogIfChanged(): Boolean {
        val remote = runCatching { api.eventsVersion() }.getOrNull()?.takeIf { it.isNotBlank() } ?: return false
        val stored = store.get(KEY_CATALOG_VER)
        if (remote == stored) return false

        // Bypass recent-sync reuse so we fetch fresh events straight from the server.
        lastSyncedCatalogAt = 0L
        val freshEvents = runCatching { api.events() }.getOrNull() ?: return false
        store.put("cache:events", AppJson.encodeToString(ListSerializer(EventCard.serializer()), freshEvents))
        runCatching { freshConfig() }
        store.put(KEY_CATALOG_VER, remote)
        lastSyncedCatalogAt = nowMillis()
        if (stored != null) catalogTick.value += 1
        return true
    }

    /**
     * Checks `GET /api/v1/events/{slug}/data-version` against the last saved fingerprint.
     * When any cron updated sessions, speakers, sponsors, roster, photos, info, tables, deals, or delays,
     * replaces all saved copies in place and bumps [eventTick] so active UI screens update immediately.
     */
    suspend fun syncEventIfChanged(slug: String): Boolean {
        val remote = runCatching { api.dataVersion(slug) }.getOrNull()?.takeIf { it.isNotBlank() } ?: return false
        val stored = store.get("dataver:$slug")
        if (remote == stored) return false

        lastSyncedEventAt.remove(slug)
        val ok = warmUpAll(slug, now = kotlinx.datetime.Clock.System.now().epochSeconds, force = true)
        if (!ok) return false
        store.put("dataver:$slug", remote)
        lastSyncedEventAt[slug] = nowMillis()
        if (stored != null) eventTick.value += 1
        return true
    }

    /**
     * The whole event saved on the phone in one go (like the website's offline warm-up), so a screen nobody
     * opened yet still has its data with no connection. Quietly does nothing offline; at most every 30 minutes.
     */
    suspend fun warmUp(slug: String, now: Long = kotlinx.datetime.Clock.System.now().epochSeconds) {
        warmUpAll(slug, now, force = false)
    }

    private suspend fun warmUpAll(slug: String, now: Long, force: Boolean): Boolean {
        val last = store.get("warm:$slug")?.toLongOrNull() ?: 0L
        if (!force && now - last < WARM_EVERY_SECONDS) return false
        val loads: List<suspend () -> Any> = mutableListOf<suspend () -> Any>(
            { cached("event:$slug", EventDetail.serializer()) { api.event(slug).also { d -> d.dataVersion?.let { store.put("dataver:$slug", it) } } } },
            { cached("sessions:$slug", ListSerializer(Session.serializer())) { api.sessions(slug) } },
            { cached("speakers:$slug", ListSerializer(Speaker.serializer())) { api.speakers(slug) } },
            { cached("sponsors:$slug", ListSerializer(Sponsor.serializer())) { api.sponsors(slug) } },
            { cached("deals:$slug", ListSerializer(Deal.serializer())) { api.deals(slug) } },
            { cached("steals:$slug", ListSerializer(FreeSteal.serializer())) { api.freeSteals(slug) } },
            { cached("quests:$slug", ListSerializer(Quest.serializer())) { api.quests(slug) } },
            { cached("tables:$slug", ListSerializer(ContributorTable.serializer())) { api.tables(slug) } },
            { cached("info:$slug", ListSerializer(InfoGroup.serializer())) { api.info(slug) } },
            { cached("guide:$slug", Guide.serializer()) { api.guide(slug) } },
            { cached("roster:$slug", ListSerializer(RosterEntry.serializer())) { api.roster(slug) } },
            { cached("delay:$slug", ListSerializer(DelayInfo.serializer())) { api.eventDelays(slug) } },
        ).apply {
            if (force) {
                add { cached("discovery:$slug", ListSerializer(DiscoveryCard.serializer())) { api.discovery(slug) } }
                add { cached("memories:$slug", Memories.serializer()) { api.memories(slug) } }
            }
        }
        val done = kotlinx.coroutines.coroutineScope {
            loads.map { load -> async { runCatching { load() }.getOrNull() } }.awaitAll()
        }
        // Only a run that reached the server counts; offline, the next open tries again.
        val reached = done.any { (it as? Loaded<*>)?.fromCache == false }
        if (reached) store.put("warm:$slug", now.toString())
        return reached
    }

    private suspend fun <T> eventCached(slug: String, key: String, serializer: kotlinx.serialization.KSerializer<T>, fetch: suspend () -> T): Loaded<T> {
        val syncedAt = lastSyncedEventAt[slug] ?: 0L
        if (nowMillis() - syncedAt < SYNC_REUSE_MS) {
            store.get("cache:$key")?.let { raw ->
                runCatching { AppJson.decodeFromString(serializer, raw) }.getOrNull()
                    ?.let { return Loaded(it, fromCache = false) }
            }
        }
        return cached(key, serializer, fetch)
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

    private fun nowMillis(): Long = kotlinx.datetime.Clock.System.now().toEpochMilliseconds()

    private companion object {
        const val SLOW_NETWORK_MS = 4_000L
        const val WARM_EVERY_SECONDS = 30 * 60L
        const val SYNC_REUSE_MS = 5_000L
        const val KEY_CATALOG_VER = "catalog_ver"
        const val KEY_CONFIG = "config"
        const val KEY_TOKEN = "organizer.token"
        const val KEY_ROLE = "organizer.role"
        const val KEY_NAME = "organizer.name"
    }
}
