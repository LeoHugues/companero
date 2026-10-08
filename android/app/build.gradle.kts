import org.jetbrains.kotlin.gradle.dsl.JvmTarget

plugins {
    id("com.android.application")
    id("org.jetbrains.kotlin.android")
}

// Server opened on first launch; `.\dev apk` passes the PC's Wi-Fi address, the CI the public one.
val serverUrl = providers.gradleProperty("serverUrl").getOrElse("http://192.168.1.2:8000")

// Every build is newer than the previous one: the number of commits, so that an update installs
// over the app (a build from the CI or from a PC, whichever is the latest).
val commitCount = runCatching {
    providers.exec { commandLine("git", "rev-list", "--count", "HEAD") }.standardOutput.asText.get().trim().toInt()
}.getOrDefault(1)
val appVersionCode = providers.gradleProperty("versionCode").map { it.toInt() }.getOrElse(commitCount)

// The key the app is signed with: always the same one, or Android refuses to update it in place.
// The CI passes it (see docs/deploiement.md); otherwise, the PC's debug key.
val keystore = providers.environmentVariable("COMPANERO_KEYSTORE").orNull?.let { file(it) }

android {
    namespace = "app.companero"
    compileSdk = 36

    defaultConfig {
        applicationId = "app.companero"
        minSdk = 28 // Hotwire Native's minimum
        targetSdk = 36
        versionCode = appVersionCode
        versionName = "1.0.$appVersionCode"
        buildConfigField("String", "DEFAULT_SERVER_URL", "\"$serverUrl\"")
    }

    signingConfigs {
        if (keystore != null && keystore.exists()) {
            create("companero") {
                storeFile = keystore
                storePassword = providers.environmentVariable("COMPANERO_KEYSTORE_PASSWORD").getOrElse("android")
                keyAlias = providers.environmentVariable("COMPANERO_KEY_ALIAS").getOrElse("androiddebugkey")
                keyPassword = providers.environmentVariable("COMPANERO_KEY_PASSWORD").getOrElse("android")
            }
        }
    }

    buildTypes {
        release {
            isMinifyEnabled = false
            signingConfig = signingConfigs.findByName("companero") ?: signingConfigs.getByName("debug")
        }
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
