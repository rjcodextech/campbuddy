# Build, CI, Windows

## Aapki machine (Windows)
- **Android Studio** + JDK 21 (pehle se `native-app/Tools` mein): shared Kotlin + Android app yahin build aur phone par chalega (USB, jaise abhi).
- iOS: Windows par Kotlin/Native iOS compile nahi hota. Isliye **cloud macOS**: GitHub Actions `macos-15` (ya Codemagic). Wahi IPA/TestFlight banata hai.
- iOS ka UI check: CI screenshot tests (simulator) + TestFlight; Compose Multiplatform ka UI Android jaisa hi render hota hai, farak sirf platform hisse (notifications, biometric, share) mein.

## Repo
Code top-level `native-app/` folder mein, docs `.claude/skills/campbuddy-docs/mobile/` mein. Build tools aur sab caches `native-app/Tools` ke andar (gitignored; aapka niyam: project se bahar kuch nahi). `mobile-app/` folder 23 Oct 2026 ko hata diya.

## CI jobs
| Job | Kya |
|---|---|
| `shared-tests` | `./gradlew :shared:allTests` (JVM + iOS simulator) |
| `contract` | PHP API contract tests + JSON schema match (server ↔ Kotlin models) |
| `android-build` | lint + `assembleDebug`, APK artifact |
| `ios-build` | macOS: `xcodebuild` simulator build + screenshot tests |
| `config-check` | bundled default config schema valid, strings keys sab present |
| `release-android` / `release-ios` | signed AAB / IPA → Play internal / TestFlight (manual) |
| `check:sync` | purana + naye parity checks |

## Testing
Unit (domain logic, web parity cases), Ktor MockEngine (API), SQLDelight in-memory, Compose UI tests (Android instrumented), screenshot tests (Paparazzi), `device_smoke.py` ka native version (adb se: launch, tabs, save, offline, crash) — wahi script kuch badlav ke saath chalegi, kyunki ye UI tree padhti hai.

## Release
Version ek jagah (`gradle.properties`), `set-version` jaisa task; staged rollout; kill switch = config flag + min version. Crash-free ≥ 99.5%, ANR < 0.47%, startup < 1.5 s (config bundled hone se).
