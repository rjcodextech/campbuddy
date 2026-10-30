package club.campbuddy.shared

import club.campbuddy.shared.data.ApiClient
import club.campbuddy.shared.data.AppJson
import club.campbuddy.shared.data.uuidShape
import io.ktor.client.HttpClient
import io.ktor.client.engine.mock.MockEngine
import io.ktor.client.engine.mock.respond
import io.ktor.client.plugins.contentnegotiation.ContentNegotiation
import io.ktor.client.request.HttpRequestData
import io.ktor.http.HttpStatusCode
import io.ktor.http.headersOf
import io.ktor.serialization.kotlinx.json.json
import kotlinx.coroutines.test.runTest
import kotlinx.datetime.Clock
import kotlin.math.abs
import kotlin.test.Test
import kotlin.test.assertEquals
import kotlin.test.assertTrue

/** The app's side of the API handshake (VerifyApiHandshake on the server). */
class HandshakeTest {
    private fun client(vararg replies: (HttpRequestData) -> Pair<HttpStatusCode, Map<String, String>>): Pair<ApiClient, MutableList<HttpRequestData>> {
        val seen = mutableListOf<HttpRequestData>()
        var i = 0
        val engine = MockEngine { request ->
            seen += request
            val (status, extra) = replies[minOf(i++, replies.lastIndex)](request)
            respond("{}", status, headersOf(*(extra + ("Content-Type" to "application/json")).map { it.key to listOf(it.value) }.toTypedArray()))
        }
        val http = HttpClient(engine) { install(ContentNegotiation) { json(AppJson) } }
        return ApiClient(baseUrl = "https://campbuddy.test/api/v1", client = http, deviceId = { "0123456789abcdef0123456789abcdef" }) to seen
    }

    @Test
    fun everyCallSaysItIsTheAppWhichPhoneAndWhen() = runTest {
        val (api, seen) = client({ HttpStatusCode.OK to emptyMap() })
        api.markSeen("wc-test", "0123456789abcdef0123456789abcdef", "android")

        val headers = seen.single().headers
        assertEquals("native", headers["X-CampBuddy-Client"])
        assertEquals("01234567-89ab-cdef-0123-456789abcdef", headers["X-CampBuddy-Device"])
        assertTrue(abs(headers["X-CampBuddy-Timestamp"]!!.toLong() - Clock.System.now().epochSeconds) <= 2)
    }

    @Test
    fun aWrongClockRetriesOnceWithTheServerTimeAndRemembersIt() = runTest {
        val server = Clock.System.now().epochSeconds + 7200
        val skew = { _: HttpRequestData -> HttpStatusCode.Unauthorized to mapOf("X-CampBuddy-Handshake-Error" to "time-skew", "X-CampBuddy-Server-Time" to server.toString()) }
        val ok = { _: HttpRequestData -> HttpStatusCode.OK to emptyMap<String, String>() }
        val (api, seen) = client(skew, ok, ok)

        api.markSeen("wc-test", "d", "android")
        assertEquals(2, seen.size, "exactly one retry")
        assertTrue(abs(seen[1].headers["X-CampBuddy-Timestamp"]!!.toLong() - server) <= 2)

        api.markSeen("wc-test", "d", "android")
        assertEquals(3, seen.size, "the next call needs no retry")
        assertTrue(abs(seen[2].headers["X-CampBuddy-Timestamp"]!!.toLong() - server) <= 2)
    }

    @Test
    fun anOrdinary401IsNotRetried() = runTest {
        val (api, seen) = client({ HttpStatusCode.Unauthorized to emptyMap() })
        runCatching { api.markSeen("wc-test", "d", "android") }
        assertEquals(1, seen.size)
    }

    @Test
    fun theDeviceIdTakesTheServersShape() {
        assertEquals("01234567-89ab-cdef-0123-456789abcdef", uuidShape("0123456789ABCDEF0123456789ABCDEF"))
        assertEquals("not-hex", uuidShape("not-hex"))
    }
}
