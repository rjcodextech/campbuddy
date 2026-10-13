package club.campbuddy.shared.data

import club.campbuddy.shared.model.AppConfig
import club.campbuddy.shared.model.ContributorTable
import club.campbuddy.shared.model.Deal
import club.campbuddy.shared.model.FreeSteal
import club.campbuddy.shared.model.DelayInfo
import club.campbuddy.shared.model.Guide
import club.campbuddy.shared.model.Memories
import club.campbuddy.shared.model.Quest
import club.campbuddy.shared.model.RosterEntry
import club.campbuddy.shared.model.Sponsor
import club.campbuddy.shared.model.EventCard
import club.campbuddy.shared.model.EventDetail
import club.campbuddy.shared.model.Session
import club.campbuddy.shared.model.Speaker
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

    suspend fun speakers(slug: String): Loaded<List<Speaker>> = cached("speakers:$slug", ListSerializer(Speaker.serializer())) { api.speakers(slug) }

    suspend fun guide(slug: String): Loaded<Guide> = cached("guide:$slug", Guide.serializer()) { api.guide(slug) }

    suspend fun memories(slug: String): Loaded<Memories> = cached("memories:$slug", Memories.serializer()) { api.memories(slug) }

    suspend fun roster(slug: String): Loaded<List<RosterEntry>> = cached("roster:$slug", ListSerializer(RosterEntry.serializer())) { api.roster(slug) }

    private suspend fun <T> cached(key: String, serializer: kotlinx.serialization.KSerializer<T>, fetch: suspend () -> T): Loaded<T> =
        try {
            val value = fetch()
            store.put("cache:$key", AppJson.encodeToString(serializer, value))
            Loaded(value, fromCache = false)
        } catch (error: Exception) {
            val saved = store.get("cache:$key")?.let { runCatching { AppJson.decodeFromString(serializer, it) }.getOrNull() }
            if (saved != null) Loaded(saved, fromCache = true) else throw error
        }

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
        const val KEY_CONFIG = "config"
        const val KEY_TOKEN = "organizer.token"
        const val KEY_ROLE = "organizer.role"
        const val KEY_NAME = "organizer.name"
    }
}
