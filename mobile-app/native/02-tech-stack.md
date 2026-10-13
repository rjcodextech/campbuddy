# Tech stack

| Kaam | Library | Note |
|---|---|---|
| Language | **Kotlin 2.x** | shared code ~85–90% |
| UI | **Compose Multiplatform** | ek UI dono par; Material 3 + apna design system (brand: maroon `#60121C`, cream `#FCF1DB`, orange `#DA7F01`, Inter font) |
| Navigation | Navigation Compose (multiplatform) | type-safe routes, deep links |
| State | ViewModel + StateFlow (MVI jaisa) | `androidx.lifecycle` multiplatform |
| Network | **Ktor client** + kotlinx.serialization | ETag/304, retry, timeouts |
| Local DB | **SQLDelight** | offline-first; schema migrations |
| Settings | multiplatform-settings / DataStore | chhoti preferences |
| DI | Koin | |
| Images | Coil 3 (multiplatform) | cache, placeholders |
| Date/time | kotlinx-datetime | event ka apna time zone (web ki tarah) |
| Logging/errors | Napier + Crashlytics (platform) | PII scrub |
| Fonts/icons | Inter bundled; line icons SVG→ImageVector (web ke `LineIcons` wale hi) | |
| QR | pure-Kotlin QR encoder (common) | Camp Card, QR kit |
| Tests | kotlin.test, Turbine, Ktor MockEngine, Paparazzi (Android screenshot) | |

## Platform-specific (expect / actual) — chhote hisse
| Feature | Android | iOS |
|---|---|---|
| Local reminders | AlarmManager (inexact) + WorkManager resync | UNUserNotificationCenter (64 limit handle) |
| Remote push | Firebase Cloud Messaging | FCM via APNs (Firebase iOS SDK) |
| Biometric lock | BiometricPrompt | LocalAuthentication |
| Secure storage (organizer token) | Keystore + EncryptedSharedPreferences | Keychain |
| Share / save file | Intent.ACTION_SEND + FileProvider | UIActivityViewController |
| Deep / App Links | intent filters | Universal Links |
| Haptics, badge | Vibrator | UIImpactFeedbackGenerator, badge |
| Background refresh | WorkManager | BGTaskScheduler |

Firebase ke liye GitLive `firebase-kotlin-sdk` ya seedha platform SDK (decision: seedha SDK, kam dependency).

## Android + iOS ek hi code: kitna sach
- Business logic, networking, DB, UI: shared.
- iOS app compile karne ke liye **macOS + Xcode zaroori** (Kotlin/Native ka niyam). Windows par aap Android + shared code chalayenge; iOS ka build/test **cloud macOS** (GitHub Actions macOS / Codemagic) par.
- Android Studio (Windows) mein sab likha ja sakta hai; iOS UI ka visual check simulator ke bina sirf CI screenshots se.
