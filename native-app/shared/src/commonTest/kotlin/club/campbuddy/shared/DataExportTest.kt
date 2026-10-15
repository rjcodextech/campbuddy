package club.campbuddy.shared

import club.campbuddy.shared.data.KeyValueStore
import club.campbuddy.shared.data.Meeting
import club.campbuddy.shared.data.MyDiscovery
import club.campbuddy.shared.data.UserData
import kotlin.test.Test
import kotlin.test.assertFalse
import kotlin.test.assertTrue

private class Store : KeyValueStore {
    private val map = mutableMapOf<String, String>()
    override fun get(key: String) = map[key]
    override fun put(key: String, value: String) { map[key] = value }
    override fun remove(key: String) { map.remove(key) }
}

class DataExportTest {
    @Test
    fun theCopyOfMyDataHasWhatIKeepButNeverTheSecretCodes() {
        val data = UserData(Store())
        data.saveCard(mapOf("name" to "Asha Rao"))
        data.saveMeeting("wc-demo", Meeting(5, "Dev Patel", note = "Ask about caching"))
        data.saveDiscovery("wc-demo", MyDiscovery("public-id-1", "SECRET-OWNER-TOKEN"))

        val json = data.exportJson()

        assertTrue(json.contains("Asha Rao"))
        assertTrue(json.contains("Ask about caching"))
        assertTrue(json.contains("wc-demo"))
        assertFalse(json.contains("SECRET-OWNER-TOKEN"), "the code that proves ownership of a profile is not exported")
        assertFalse(json.contains("public-id-1"))
    }
}
