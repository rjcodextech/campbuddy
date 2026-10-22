import UIKit
import UserNotifications
import FirebaseCore
import FirebaseMessaging
import Shared

/// Push (Firebase Cloud Messaging over APNs) and how notifications behave. Push stays off when the build has no
/// GoogleService-Info.plist; local reminders work either way.
final class AppDelegate: NSObject, UIApplicationDelegate, UNUserNotificationCenterDelegate, MessagingDelegate {
    func application(_ application: UIApplication, didFinishLaunchingWithOptions launchOptions: [UIApplication.LaunchOptionsKey: Any]? = nil) -> Bool {
        UNUserNotificationCenter.current().delegate = self

        if Bundle.main.path(forResource: "GoogleService-Info", ofType: "plist") != nil {
            FirebaseApp.configure()
            Messaging.messaging().delegate = self
            // No question is shown here: the app asks for notifications itself (My Day / live updates).
            application.registerForRemoteNotifications()
        }

        return true
    }

    func application(_ application: UIApplication, didRegisterForRemoteNotificationsWithDeviceToken deviceToken: Data) {
        Messaging.messaging().apnsToken = deviceToken
    }

    /// A new FCM token: the shared code sends it the next time an event is opened.
    func messaging(_ messaging: Messaging, didReceiveRegistrationToken fcmToken: String?) {
        IosPushKt.savePushToken(token: fcmToken)
    }

    /// A reminder or push while the app is open is still shown, as on Android.
    func userNotificationCenter(_ center: UNUserNotificationCenter, willPresent notification: UNNotification,
                                withCompletionHandler completionHandler: @escaping (UNNotificationPresentationOptions) -> Void) {
        completionHandler([.banner, .list, .sound])
    }

    /// A tapped push opens the page it is about (data.url, a path on campbuddy.club).
    func userNotificationCenter(_ center: UNUserNotificationCenter, didReceive response: UNNotificationResponse,
                                withCompletionHandler completionHandler: @escaping () -> Void) {
        if let url = response.notification.request.content.userInfo["url"] as? String {
            PlatformIosKt.openLink(url: url)
        }
        completionHandler()
    }
}
