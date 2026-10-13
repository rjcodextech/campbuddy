package club.campbuddy.shared.domain

/** What the platform gives for push: the phone's token (null when push is not set up or the person said no). */
interface PushPlatform {
    suspend fun token(): String?
}

object NoPush : PushPlatform {
    override suspend fun token(): String? = null
}
