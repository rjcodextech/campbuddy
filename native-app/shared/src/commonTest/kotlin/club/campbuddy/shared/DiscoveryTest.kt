package club.campbuddy.shared

import club.campbuddy.shared.data.errorMessage
import club.campbuddy.shared.domain.Discovery
import club.campbuddy.shared.model.DiscoveryCard
import club.campbuddy.shared.model.DiscoveryFields
import kotlin.test.Test
import kotlin.test.assertEquals

class DiscoveryTest {
    private fun card(id: String, name: String?, vararg tags: String) = DiscoveryCard(id, name, fields = DiscoveryFields(tags = tags.toList()))

    @Test
    fun matchesWithMostSharedTagsComeFirstAndOwnAndHiddenAreLeftOut() {
        val cards = listOf(
            card("a", null, "designer"),
            card("me", "Me", "developer", "speaker"),
            card("b", "Bea", "developer", "speaker", "student"),
            card("c", "Cy", "developer"),
            card("hidden", "Hid", "developer", "speaker"),
        )

        val ranked = Discovery.rank(cards, listOf("developer", "speaker"), myId = "me", hidden = setOf("hidden"))

        assertEquals(listOf("b", "c", "a"), ranked.map { it.discoveryId })
    }

    @Test
    fun sharedTagsIgnoreCase() {
        assertEquals(listOf("Developer"), Discovery.shared(listOf("developer"), listOf("Developer", "student")))
    }

    @Test
    fun serverErrorsComeOutInPlainWords() {
        assertEquals("That attendee has left discovery.", errorMessage("""{"message":"The given data was invalid.","errors":{"to":["That attendee has left discovery."]}}"""))
        assertEquals("Nope", errorMessage("""{"error":{"message":"Nope"}}"""))
        assertEquals("Hi \"there\"", errorMessage("""{"message":"Hi \"there\""}"""))
        assertEquals(null, errorMessage("not json"))
    }

    @Test
    fun theServersWaveStateReadsIntoTheAppModel() {
        // A real answer from the dev server (the "chat" block is one the app does not read).
        val json = """{"max_messages":3,"chat":{"open":true,"day":"2026-10-13","days":2},"sent":["aa"],"received":["bb"],"received_with_message":["bb"],
            "pending":[{"discovery_id":"aa","messages":[{"mine":true,"body":"Hi","at":"2026-10-13T10:00:00+00:00","day":"2026-10-13"}]}],
            "mutual":[{"discovery_id":"cc","name":"Ann Tester","messages":[],"mine":0,"theirs":0,"can_send":true,"reason":null}]}"""

        val state = club.campbuddy.shared.data.AppJson.decodeFromString(club.campbuddy.shared.model.WaveState.serializer(), json)

        assertEquals(listOf("aa"), state.sent)
        assertEquals(listOf("bb"), state.receivedWithMessage)
        assertEquals("Hi", state.pending.single().messages.single().body)
        assertEquals("Ann Tester", state.mutual.single().name)
        assertEquals(true, state.mutual.single().canSend)
    }
}
