package club.campbuddy.shared

import club.campbuddy.shared.data.Profile
import club.campbuddy.shared.domain.Onboarding
import kotlin.test.Test
import kotlin.test.assertEquals
import kotlin.test.assertNull
import kotlin.test.assertTrue

class OnboardingTest {
    @Test
    fun aSixthTagIsRefusedAndATappedOneComesOff() {
        val five = Onboarding.TAGS.take(5)

        assertEquals(five, Onboarding.toggleTag(five, "student"))
        assertEquals(five.drop(1), Onboarding.toggleTag(five, five.first()))
        assertEquals(listOf("student"), Onboarding.toggleTag(emptyList(), "student"))
    }

    @Test
    fun theWordPressProfileLinkBecomesAUsername() {
        assertEquals("jane", Onboarding.wporgUsername("https://profiles.wordpress.org/jane/"))
        assertEquals("jane", Onboarding.wporgUsername("  jane  "))
        assertEquals("a.b@c", Onboarding.wporgUsername("a.b@c"))
        assertNull(Onboarding.wporgUsername("not a name!"))
        assertNull(Onboarding.wporgUsername("   "))
    }

    @Test
    fun whatIsSavedIsTidyAndKnown() {
        val saved = Onboarding.clean(Profile(interests = listOf("Student", "made-up", "developer"), profession = "  Engineer ", whoToMeet = "  ", wporg = "https://profiles.wordpress.org/sam"))

        assertEquals(listOf("developer", "student"), saved.interests, "known tags only, in the list order")
        assertEquals("Engineer", saved.profession)
        assertNull(saved.whoToMeet)
        assertEquals("sam", saved.wporg)
    }

    @Test
    fun theClosingScreenSaysWhatEachAnswerDoesAndIsNeverEmpty() {
        val all = Onboarding.summary(Profile(interests = listOf("developer", "speaker"), firstWordCamp = "yes", attendingContributorDay = "yes", whoToMeet = "x"))
        assertEquals(4, all.size)
        assertTrue(all[0].contains("developer, speaker"))

        assertEquals(1, Onboarding.summary(Profile()).size)
        assertTrue(Onboarding.summary(Profile(firstWordCamp = "no"))[0].contains("out of your way"))
    }

    @Test
    fun theTagListIsTheOneTheWebsiteOffers() {
        assertEquals(12, Onboarding.TAGS.size)
        assertEquals("developer", Onboarding.TAGS.first())
        assertEquals("mentor", Onboarding.TAGS.last())
    }
}
