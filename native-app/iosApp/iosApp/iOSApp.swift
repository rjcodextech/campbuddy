import SwiftUI
import Shared

@main
struct iOSApp: App {
    @UIApplicationDelegateAdaptor(AppDelegate.self) private var appDelegate

    var body: some Scene {
        WindowGroup {
            ContentView()
                .ignoresSafeArea(.keyboard)
                // campbuddy://event/… and universal links (https://campbuddy.club/event/…) open that event.
                .onOpenURL { url in PlatformIosKt.openLink(url: url.absoluteString) }
                .onContinueUserActivity(NSUserActivityTypeBrowsingWeb) { activity in
                    if let url = activity.webpageURL { PlatformIosKt.openLink(url: url.absoluteString) }
                }
        }
    }
}
