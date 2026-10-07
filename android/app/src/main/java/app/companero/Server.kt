package app.companero

import android.app.Activity
import android.content.Context
import android.content.Intent
import androidx.core.content.edit
import dev.hotwire.core.config.Hotwire
import dev.hotwire.core.turbo.config.PathConfiguration

/**
 * The Companero server the app displays: the PC on the home Wi-Fi for now.
 * Its address can be changed from the error screen when it cannot be reached.
 */
object Server {
    /** Served by Symfony UX Native, see src/Native/NativeConfiguration.php. */
    private const val PATH_CONFIGURATION = "/native/android_v1.json"

    fun url(context: Context): String =
        prefs(context).getString("url", null) ?: BuildConfig.DEFAULT_SERVER_URL

    fun startLocation(context: Context) = "${url(context)}/"

    fun loadPathConfiguration(context: Context) {
        Hotwire.loadPathConfiguration(
            context = context,
            location = PathConfiguration.Location(remoteFileUrl = url(context) + PATH_CONFIGURATION),
        )
    }

    fun change(activity: Activity, input: String) {
        val address = input.trim().trimEnd('/')
        val url = if ("://" in address) address else "http://$address"
        prefs(activity).edit { putString("url", url) }
        restart(activity)
    }

    /** Starts over with a fresh WebView session, e.g. once the server is reachable again. */
    fun restart(activity: Activity) {
        loadPathConfiguration(activity)
        activity.startActivity(
            Intent(activity, MainActivity::class.java)
                .addFlags(Intent.FLAG_ACTIVITY_NEW_TASK or Intent.FLAG_ACTIVITY_CLEAR_TASK)
        )
    }

    private fun prefs(context: Context) =
        context.getSharedPreferences("server", Context.MODE_PRIVATE)
}
