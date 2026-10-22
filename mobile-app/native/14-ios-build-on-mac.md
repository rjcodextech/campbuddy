# iOS app — Mac par build karne ke steps

Native app (`native-app/`, branch `app`) ka iPhone version. Code wahi hai jo Android ka hai (Kotlin + Compose); iOS ka sirf chhota sa shell hai (`native-app/iosApp/`). Ek hi script sab karti hai: **`bash native-app/build-ios.sh`**.

| Command | Kya karta hai | Apple account chahiye? |
|---|---|---|
| `bash native-app/build-ios.sh check` | Kya install hai, kya set hai — kuch build nahi | Nahi |
| `bash native-app/build-ios.sh sim` | Build karke iPhone **simulator** mein app khol deta hai | Nahi |
| `bash native-app/build-ios.sh open` | Xcode project banakar Xcode khol deta hai (wahan se apne iPhone par Run) | Haan (free Apple ID bhi chalega phone test ke liye) |
| `bash native-app/build-ios.sh archive` | App Store wala signed build: `native-app/iosApp/build/ipa/*.ipa` | Haan, Developer Program ($99/saal) |
| `bash native-app/build-ios.sh upload` | Wahi build seedha App Store Connect / TestFlight par | Haan, Developer Program |

> Har command repo ke root folder (`V2/`) se chalao. Pehli baar build mein **15–30 minute** lag sakte hain (Kotlin/Native ~1 GB download karta hai, Firebase package bhi). Uske baad 2–5 minute.

---

## 1. Ek baar ki taiyari (Mac par)

1. **Xcode** — Mac App Store se latest Xcode install karo (App Store upload ke liye Apple latest Xcode hi maangta hai). Ek baar kholo, license accept karo, *iOS* platform/simulator download hone do. Phir Terminal mein:
   ```bash
   sudo xcode-select -s /Applications/Xcode.app
   xcodebuild -runFirstLaunch
   ```
2. **Homebrew** (nahi hai to): https://brew.sh wala ek-line command.
3. **XcodeGen + Java 21:**
   ```bash
   brew install xcodegen git
   brew install --cask temurin@21
   ```
   (Android Studio installed hai to Java ki zaroorat nahi — script uska apna Java le leti hai. Android SDK ki zaroorat **nahi** hai.)
4. **Repo** Mac par lao aur branch `app` par jao:
   ```bash
   git clone <aapka repo URL> campbuddy-V2 && cd campbuddy-V2
   git checkout app
   ```
5. Check:
   ```bash
   bash native-app/build-ios.sh check
   ```
   Saare ✓ aane chahiye. "No signing team" aur "No GoogleService-Info.plist" abhi theek hai (• wali lines) — wo neeche ke steps mein aayenge.

## 2. Pehla build — simulator mein (account ke bina)

```bash
bash native-app/build-ios.sh sim
```
Simulator khulega aur CampBuddy chalu ho jayegi. Yahi sabse pehla test hai: Picker, event kholna, tabs, Camp Card, My Day.

Agar kuch fail ho → neeche **§9 Problems** dekho.

## 3. Apple Developer account (ek baar)

1. **Apple Developer Program** join karo (agar pehle se nahi): https://developer.apple.com/programs/ — approval 1–2 din.
2. **Team ID** lo: developer.apple.com → Account → *Membership details* → Team ID (10 akshar, jaise `ABCDE12345`).
3. Mac par file banao `native-app/iosApp/local.env` (git mein nahi jaati):
   ```bash
   echo 'CAMPBUDDY_TEAM_ID=ABCDE12345' > native-app/iosApp/local.env
   ```
4. **Xcode → Settings → Accounts** → `+` → apna Apple ID sign in karo (wahi jo Developer Program mein hai).
5. App ID, Push aur Associated Domains capability **Xcode khud bana deta hai** (automatic signing, `-allowProvisioningUpdates`). Kuch haath se nahi banana.
6. **Server** (`.env`, aap deploy karte ho): `MOBILE_APPLE_TEAM_ID=ABCDE12345` — isse `https://campbuddy.club/.well-known/apple-app-site-association` mein app ka naam aata hai aur campbuddy.club ke event links seedhe app mein khulte hain (Universal Links). Deploy ke baad browser mein wo URL kholkar JSON check karo.

## 4. Push notifications (Firebase) — ek baar

Push ke bina bhi app chalti hai (local reminders kaam karte hain). Push chahiye to:

1. **APNs key:** developer.apple.com → Certificates, IDs & Profiles → **Keys** → `+` → *Apple Push Notifications service (APNs)* → `.p8` download (sirf ek baar milti hai, sambhal ke rakho) + **Key ID** note karo.
2. **Firebase console** (wahi project jo Android push ke liye hai) → Project settings → **Cloud Messaging** → *Apple app configuration* → APNs Authentication Key upload (`.p8`, Key ID, Team ID).
3. Firebase → Project settings → *Your apps* → iOS app `club.campbuddy.app`. **Pehle se bani hai** (Capacitor app ke time) to wahi use karo, nayi mat banao. → **`GoogleService-Info.plist`** download.
4. File ko yahan rakho: `native-app/iosApp/iosApp/GoogleService-Info.plist` (git mein nahi jaati).
5. `bash native-app/build-ios.sh check` → "push on" dikhna chahiye. Agla build push ke saath hoga.

Server side kuch nahi badalna: wahi FCM setup (`FCM_CREDENTIALS_PATH`) iOS ko bhi bhejta hai (APNs block pehle se hai).

## 5. Apne iPhone par chalana

1. iPhone ko USB se Mac se jodo, phone par *Trust this computer*.
2. iPhone: **Settings → Privacy & Security → Developer Mode → On** (restart hoga).
3. ```bash
   bash native-app/build-ios.sh open
   ```
4. Xcode mein upar beech mein device list se apna iPhone chuno → **Run (⌘R)**.
5. Pehli baar phone par "Untrusted Developer" aaye to: Settings → General → **VPN & Device Management** → apna developer account → Trust.

> Xcode ke Run button wale build version **nahi badhaate** (sirf script ke `sim`/`archive`/`upload` badhaate hain). Phone par test ke liye theek hai.

## 6. TestFlight / App Store

1. **App Store Connect** → My Apps → `+` → New App: naam `CampBuddy`, platform iOS, bundle id `club.campbuddy.app`, SKU `campbuddy-ios`. (Capacitor app ke time bana diya tha to wahi record — native app usi ka naya build banegi.)
2. Build bhejo:
   ```bash
   bash native-app/build-ios.sh upload
   ```
   Ya `archive` se `.ipa` banao aur Apple ke **Transporter** app se upload karo.
3. 10–30 minute baad App Store Connect → **TestFlight** mein build dikhega. *Internal testing* group mein khud ko jodo → iPhone par TestFlight app se install.
4. Store listing, screenshots, privacy label, review notes, demo manager account: `mobile-app/SETUP.md` (§ Apple/Play) mein pehle se likha hai — wahi copy karo.
5. Encryption wala sawal har upload par nahi aayega (Info.plist mein `ITSAppUsesNonExemptEncryption = NO` hai — app sirf HTTPS use karti hai).

## 7. Version — har build alag number

Android jaisa hi niyam: `native-app/version.properties` mein **agle build** ka version hai. `sim`, `archive` ya `upload` successful hote hi script use +1 kar deti hai (0.0.3 → 0.0.4, build 6 → 7), aur `iosApp/project.yml` mein wo version likh deti hai jo abhi bana.

**Mac par build ke baad dono files commit + push karo** (`native-app/version.properties`, `native-app/iosApp/project.yml`), aur Windows par Android build se pehle pull karo — warna dono taraf ek hi number dobara ban jayega. App Store har upload par pichhle se bada build number maangta hai.

## 8. Local server se test (zaroorat ho to)

Release build hamesha `campbuddy.club` se baat karta hai. Debug build ko Mac par chal rahe server par bhejna ho:
Xcode → Product → Scheme → **Edit Scheme** → Run → Arguments → Environment Variables → `CAMPBUDDY_API` = `http://127.0.0.1:8099/api/v1` → Run (simulator mein). Hatane ke liye variable untick karo.

## 9. Problems aur hal

| Dikkat | Hal |
|---|---|
| `No Java 17+ found` | `brew install --cask temurin@21` |
| `XcodeGen is missing` | `brew install xcodegen` |
| Xcode build mein **"Build the Kotlin framework"** step fail | Xcode → Report navigator (⌘9) mein poora error. Terminal mein seedha chalao: `cd native-app && ./gradlew :shared:linkDebugFrameworkIosSimulatorArm64` — error saaf dikhega. Mujhe bhejo. |
| `Signing requires a development team` | §3 step 3–4 (local.env + Xcode Accounts), phir script dobara |
| Firebase package download atka / fail | Xcode → File → Packages → **Reset Package Caches**, internet check, phir build |
| Warning "Kotlin hasn't been tested with this Xcode version" | Sirf warning — build chalne do |
| Naye Xcode par Kotlin framework **link error** | Kotlin version (abhi 2.1.21) naye Xcode ke liye purana ho sakta hai. Upgrade Android build ko bhi chhoota hai, isliye mujhse poochh kar karenge. |
| Simulator mein organizer sign-in app band karne par yaad nahi | Team ke bina (unsigned) simulator mein Keychain nahi milta, tab app normal storage use karti hai — asli phone/TestFlight par Keychain hi |
| iPhone par app khulte hi band | Xcode → Window → Devices and Simulators → phone → *View Device Logs*, ya Xcode se Run karke console dekho |

## 10. iPhone par kya check karna hai (pehla device test)

- [ ] Picker, event kholna, saare tabs, Hindi phone language par Hindi text
- [ ] My Day: reminder on → notification permission ka sawal → reminder time par notification (app khuli ho tab bhi)
- [ ] Camp Card: Share (share sheet), Download (Photos permission → Photos mein picture)
- [ ] My Day → PDF save / share; meeting ka calendar (.ics) → Calendar app
- [ ] Sponsor / deal link: app ke andar Safari sheet mein khule
- [ ] Notes app mein `campbuddy://event/<slug>` likh kar tap → wahi event khule
- [ ] Server par Team ID live hone ke baad: `https://campbuddy.club/event/<slug>` link (Messages/Notes se) → app mein khule
- [ ] Push (plist ke saath build): Admin se broadcast → phone par aaye; tap → us event par
- [ ] Organizer login → app delete → reinstall → login phir se maange (purana sign-in nahi bachta)
- [ ] Landscape ghuma kar dekho; app light theme mein hi rahe (phone Dark Mode mein bhi)

---

## Code mein kya bana hai (22 Oct 2026)

- `shared/src/iosMain/…/PlatformIos.kt` — iPhone ke saare hisse Android jaise: links (Universal + `campbuddy://` + push tap), sponsor page Safari sheet mein, `open(url)` ka naya iOS 18 wala tarika, debug mein `CAMPBUDDY_API`.
- `IosKeychain.kt` — organizer sign-in (`organizer.*`) Keychain mein (Android encrypted prefs ki tarah); naye install par purana Keychain saaf.
- `IosPush.kt` + `iosApp/AppDelegate.swift` — Firebase push (plist ho to hi), app khuli ho tab bhi notification dikhe.
- `IosDayPdf.kt` — "My day" PDF (Android jaisa layout, Hindi theek).
- `IosCardExport.kt` — share/save + PNG (Social media Publish), iPad par share sheet crash fix, ek compile bug fix.
- `iosApp/project.yml` — Info.plist, entitlements (push, Universal Links), app icon, launch colour, privacy manifest (`PrivacyInfo.xcprivacy`), Firebase package, iPhone-only, light mode.
- `native-app/gradlew` — Gradle wrapper (Mac par alag Gradle install nahi karna).
- `native-app/build-ios.sh` — upar wali script. CI (`.github/workflows/native-build.yml`) ka iOS job bhi isi project se build karta hai.

**Windows par kya check hua:** iOS ka saara Kotlin code teeno iOS targets (iPhone, simulator arm64, simulator x64) ke liye Windows par compile hua (`./gradlew :shared:compileKotlinIosArm64 -Pkotlin.native.enableKlibsCrossCompilation=true`), bina error/warning. **Swift files, Xcode build, linking aur asli iPhone test sirf Mac par ho sakte hain** — wo abhi nahi hue.

**iOS par abhi alag:** screen ke kinare se swipe-back nahi (har screen par Back button hai); iPad par iPhone app ki tarah chalegi.
