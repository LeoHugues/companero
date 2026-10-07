package app.companero

import android.app.Application
import dev.hotwire.core.config.Hotwire
import dev.hotwire.core.logging.HotwireLogLevel
import dev.hotwire.navigation.config.defaultFragmentDestination
import dev.hotwire.navigation.config.registerFragmentDestinations

class CompaneroApplication : Application() {
    override fun onCreate() {
        super.onCreate()

        // "Hotwire Native" in the user agent is how Symfony UX Native recognises the app (ux_is_native()).
        Hotwire.config.applicationUserAgentPrefix = "Companero;"
        Hotwire.config.webViewDebuggingEnabled = BuildConfig.DEBUG
        Hotwire.config.logger.logLevel = if (BuildConfig.DEBUG) HotwireLogLevel.DEBUG else HotwireLogLevel.NONE

        Hotwire.defaultFragmentDestination = WebFragment::class
        Hotwire.registerFragmentDestinations(WebFragment::class)

        Server.loadPathConfiguration(this)
    }
}
