plugins {
    alias(libs.plugins.androidApplication)
    alias(libs.plugins.kotlinAndroid)
    alias(libs.plugins.composeMultiplatform)
    alias(libs.plugins.composeCompiler)
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
    buildTypes {
        // The debug build installs next to the store app (Capacitor, same id) for side-by-side testing.
        debug { applicationIdSuffix = ".native" }
        release { isMinifyEnabled = false }
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
    implementation(compose.runtime)
    implementation(compose.foundation)
    implementation(compose.material3)
}
