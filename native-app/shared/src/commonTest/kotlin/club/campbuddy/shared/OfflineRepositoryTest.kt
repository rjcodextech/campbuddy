package club.campbuddy.shared

import club.campbuddy.shared.data.ApiClient
import club.campbuddy.shared.data.AppJson
import club.campbuddy.shared.data.KeyValueStore
import club.campbuddy.shared.data.Repository
import io.ktor.client.HttpClient
import io.ktor.client.engine.mock.MockEngine
import io.ktor.client.engine.mock.respond
import io.ktor.client.engine.mock.respondError
import io.ktor.client.plugins.contentnegotiation.ContentNegotiation
import io.ktor.http.HttpStatusCode
import io.ktor.http.headersOf
import io.ktor.serialization.kotlinx.json.json
import kotlinx.coroutines.delay
import kotlinx.coroutines.sync.withLock
import kotlinx.coroutines.test.runTest
import kotlin.test.Test
import kotlin.test.assertEquals
import kotlin.test.assertTrue

private class OfflineTestStore : KeyValueStore {
    val map = mutableMapOf<String, String>()
    override fun get(key: String) = map[key]
    override fun put(key: String, value: String) { map[key] = value }
    override fun remove(key: String) { map.remove(key) }
}

/** The app with no connection, or a connection that barely moves (venue wifi). */
class OfflineRepositoryTest {
    private val lock = kotlinx.coroutines.sync.Mutex()
    // On the real clock: the 4-second wait for slow wifi is a real wait, not the test scheduler's virtual time.
    private suspend fun <T> real(block: suspend () -> T): T = kotlinx.coroutines.withContext(kotlinx.coroutines.Dispatchers.Default) { block() }

    private fun repository(store: OfflineTestStore, seen: MutableList<String>, waitMillis: Long = 0, fail: Boolean = false): Repository {
        val engine = MockEngine { request ->
            // Requests arrive on several threads at once (the warm-up runs in parallel).
            lock.withLock { seen += request.url.encodedPath }
            if (waitMillis > 0) delay(waitMillis)
            if (fail) respondError(HttpStatusCode.ServiceUnavailable) else respond("""{"data":[]}""", HttpStatusCode.OK, headersOf("Content-Type", "application/json"))
        }
        val api = ApiClient(baseUrl = "https://campbuddy.test/api/v1", client = HttpClient(engine) { install(ContentNegotiation) { json(AppJson) } })
        return Repository(api, store)
    }

    @Test
    fun openingAnEventSavesEveryTabForOfflineUseOnceEveryHalfHour() = runTest {
        val store = OfflineTestStore()
        val seen = mutableListOf<String>()
        val repo = repository(store, seen)

        real { repo.warmUp("wc", now = 1_000_000) }
        for (tab in listOf("sessions", "speakers", "sponsors", "deals", "free-steals", "quests", "contributor-tables", "info", "guide", "delay")) {
            assertTrue(seen.any { it.endsWith("/app/events/wc/$tab") }, "warm-up fetched $tab")
        }
        assertTrue(store.map.containsKey("cache:speakers:wc"), "a tab nobody opened is saved")

        val before = seen.size
        real { repo.warmUp("wc", now = 1_000_000 + 60) }
        assertEquals(before, seen.size, "not again within half an hour")
        real { repo.warmUp("wc", now = 1_000_000 + 31 * 60) }
        assertTrue(seen.size > before, "again after half an hour")
    }

    @Test
    fun offlineTheWarmUpIsNotCountedSoTheNextOpenTriesAgain() = runTest {
        val store = OfflineTestStore()
        val repo = repository(store, mutableListOf(), fail = true)

        real { repo.warmUp("wc", now = 1_000_000) }
        assertEquals(null, store.map["warm:wc"])
    }

    @Test
    fun onSlowWifiTheSavedCopyShowsInsteadOfWaitingForTheNetwork() = runTest {
        val store = OfflineTestStore().apply { put("cache:sessions:wc", "[]") }
        val repo = repository(store, mutableListOf(), waitMillis = 15_000)

        val loaded = real { repo.sessions("wc") }
        assertEquals(emptyList(), loaded.value)
        assertEquals(false, loaded.fromCache, "slow is not offline: no \"you are offline\" line")
    }

    @Test
    fun withNoConnectionTheSavedCopyShowsWithTheOfflineLine() = runTest {
        val store = OfflineTestStore().apply { put("cache:sessions:wc", "[]") }
        val repo = repository(store, mutableListOf(), fail = true)

        val loaded = real { repo.sessions("wc") }
        assertTrue(loaded.fromCache)
    }

    @Test
    fun withNoSavedCopyItStillWaitsForTheAnswer() = runTest {
        val store = OfflineTestStore()
        val repo = repository(store, mutableListOf(), waitMillis = 50)

        val loaded = real { repo.sessions("wc") }
        assertEquals(false, loaded.fromCache)
        assertTrue(store.map.containsKey("cache:sessions:wc"))
    }
}
