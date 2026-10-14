plugins {
    alias(libs.plugins.androidApplication)
    alias(libs.plugins.kotlinAndroid)
    alias(libs.plugins.composeMultiplatform)
    alias(libs.plugins.composeCompiler)
}

// Firebase (push) is set up from the same google-services.json the website project keeps (not in git). Without the
// file the app builds and runs, it just has no push. The values go into BuildConfig; no Google plugin is needed.
val firebase: Map<String, String> = run {
    val file = rootProject.file("../storage/app/analytics/google-services.json")
    if (!file.exists()) return@run emptyMap()
    @Suppress("UNCHECKED_CAST")
    val json = groovy.json.JsonSlurper().parse(file) as Map<String, Any>
    val info = json["project_info"] as Map<String, Any>
    val client = (json["client"] as List<Map<String, Any>>).firstOrNull {
        ((it["client_info"] as Map<String, Any>)["android_client_info"] as Map<String, Any>)["package_name"] == "club.campbuddy.app"
    } ?: return@run emptyMap()
    mapOf(
        "FIREBASE_PROJECT_ID" to info["project_id"].toString(),
        "FIREBASE_SENDER_ID" to info["project_number"].toString(),
        "FIREBASE_APP_ID" to (client["client_info"] as Map<String, Any>)["mobilesdk_app_id"].toString(),
        "FIREBASE_API_KEY" to ((client["api_key"] as List<Map<String, Any>>).first()["current_key"]).toString(),
    )
}

// The upload key for the store build lives next to the Firebase file (not in git). Without it the release build is unsigned.
val signing: Map<String, String> = run {
    val file = rootProject.file("../storage/app/analytics/android-signing.txt")
    if (!file.exists()) return@run emptyMap()
    file.readLines().mapNotNull { line -> line.substringBefore("=", "").takeIf { it.isNotBlank() && !line.startsWith("NOTE") }?.let { it to line.substringAfter("=") } }.toMap()
}

android {
    namespace = "club.campbuddy.app"
    compileSdk = 36

    defaultConfig {
        applicationId = "club.campbuddy.app"
        minSdk = 24
        targetSdk = 36
        versionCode = 1
        versionName = "2.0.0"
    }
    buildFeatures { buildConfig = true }
    defaultConfig {
        // Push only works for the store id (the debug build has another id, which Firebase does not know).
        listOf("FIREBASE_PROJECT_ID", "FIREBASE_SENDER_ID", "FIREBASE_APP_ID", "FIREBASE_API_KEY").forEach { key ->
            buildConfigField("String", key, "\"${firebase[key].orEmpty()}\"")
        }
    }
    signingConfigs {
        if (signing["keystore"] != null) {
            create("upload") {
                storeFile = rootProject.file("../storage/app/analytics/" + signing.getValue("keystore"))
                keyAlias = signing["alias"]
                storePassword = signing["storepass"]
                keyPassword = signing["keypass"]
            }
        }
    }
    buildTypes {
        // The debug build installs next to the store app (Capacitor, same id) for side-by-side testing.
        debug {
            applicationIdSuffix = ".native"
            listOf("FIREBASE_PROJECT_ID", "FIREBASE_SENDER_ID", "FIREBASE_APP_ID", "FIREBASE_API_KEY").forEach { key ->
                buildConfigField("String", key, "\"\"")
            }
            buildConfigField("String", "API_BASE", "\"https://campbuddy.club/api/v1\"")
        }
        release {
            signingConfigs.findByName("upload")?.let { signingConfig = it }
            isMinifyEnabled = false
            buildConfigField("String", "API_BASE", "\"https://campbuddy.club/api/v1\"")
        }
    }
    compileOptions {
        sourceCompatibility = JavaVersion.VERSION_17
        targetCompatibility = JavaVersion.VERSION_17
    }
    kotlinOptions { jvmTarget = "17" }
}

dependencies {
    implementation(project(":shared"))
    implementation(libs.androidx.activity.compose)
    implementation("androidx.core:core-ktx:1.16.0")
    implementation("androidx.browser:browser:1.8.0") // Custom Tabs: sponsor links open inside the app
    implementation("androidx.fragment:fragment-ktx:1.8.5") // a current Fragment (the activity-result API needs 1.3+)
    implementation(compose.runtime)
    implementation(compose.foundation)
    implementation(compose.material3)
    implementation(platform(libs.firebase.bom))
    implementation(libs.firebase.messaging)
}
