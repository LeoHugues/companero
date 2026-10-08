import org.jetbrains.kotlin.gradle.dsl.JvmTarget

plugins {
    id("com.android.application")
    id("org.jetbrains.kotlin.android")
}

// Server opened on first launch; `.\dev apk` passes the PC's Wi-Fi address.
val serverUrl = providers.gradleProperty("serverUrl").getOrElse("http://192.168.1.2:8000")

android {
    namespace = "app.companero"
    compileSdk = 36

    defaultConfig {
        applicationId = "app.companero"
        minSdk = 28 // Hotwire Native's minimum
        targetSdk = 36
        versionCode = 2
        versionName = "0.2.0"
        buildConfigField("String", "DEFAULT_SERVER_URL", "\"$serverUrl\"")
    }

    buildFeatures {
        buildConfig = true
    }

    compileOptions {
        sourceCompatibility = JavaVersion.VERSION_17
        targetCompatibility = JavaVersion.VERSION_17
    }
}

kotlin {
    compilerOptions {
        jvmTarget.set(JvmTarget.JVM_17)
    }
}

dependencies {
    implementation("dev.hotwire:core:1.3.1")
    implementation("dev.hotwire:navigation-fragments:1.3.1")
    implementation("androidx.activity:activity-ktx:1.10.1")
    implementation("androidx.appcompat:appcompat:1.7.0")
    implementation("androidx.core:core-ktx:1.16.0")
    implementation("com.google.android.material:material:1.12.0")
}
