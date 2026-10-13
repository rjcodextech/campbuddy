package club.campbuddy.shared.data

import club.campbuddy.shared.model.AppConfig
import club.campbuddy.shared.model.EventCard
import club.campbuddy.shared.model.EventDetail
import club.campbuddy.shared.model.Session
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
