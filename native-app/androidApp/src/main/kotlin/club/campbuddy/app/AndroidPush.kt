package club.campbuddy.app

import android.content.Context
import club.campbuddy.shared.domain.PushPlatform
import com.google.firebase.FirebaseApp
import com.google.firebase.FirebaseOptions
import com.google.firebase.messaging.FirebaseMessaging
import kotlin.coroutines.resume
import kotlin.coroutines.suspendCoroutine

/** Push through Firebase Cloud Messaging; off (no token) when the build has no Firebase settings. */
class AndroidPush(private val context: Context) : PushPlatform {
    private val configured = BuildConfig.FIREBASE_APP_ID.isNotEmpty() && BuildConfig.FIREBASE_API_KEY.isNotEmpty()

    init {
        if (configured && FirebaseApp.getApps(context).isEmpty()) {
            FirebaseApp.initializeApp(
                context,
                FirebaseOptions.Builder()
                    .setProjectId(BuildConfig.FIREBASE_PROJECT_ID)
                    .setGcmSenderId(BuildConfig.FIREBASE_SENDER_ID)
                    .setApplicationId(BuildConfig.FIREBASE_APP_ID)
                    .setApiKey(BuildConfig.FIREBASE_API_KEY)
                    .build(),
            )
        }
    }

    override suspend fun token(): String? {
        if (!configured) return null

        return suspendCoroutine { done ->
            FirebaseMessaging.getInstance().token.addOnCompleteListener { task -> done.resume(if (task.isSuccessful) task.result else null) }
        }
    }
}
