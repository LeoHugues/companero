package app.companero

import android.os.Build
import android.os.Handler
import android.os.Looper
import android.service.quicksettings.Tile
import android.service.quicksettings.TileService
import android.webkit.CookieManager
import org.json.JSONObject
import java.net.HttpURLConnection
import java.net.URL
import java.util.concurrent.Executors

/**
 * "À la maison" in the phone's quick settings, next to Wi-Fi and Bluetooth: a tap says
 * "I'm home" or "I'm out" without opening the app. It reuses the WebView's session cookies
 * to call /api/presence (see src/Controller/PresenceController.php).
 */
class PresenceTileService : TileService() {
    private val main = Handler(Looper.getMainLooper())

    override fun onStartListening() {
        super.onStartListening()
        request("GET")
    }

    override fun onClick() {
        super.onClick()
        request("POST")
    }

    private fun request(method: String) {
        val server = Server.url(this)
        executor.execute {
            val atHome = try {
                call(server, method)
            } catch (e: Exception) {
                null
            }
            main.post { show(atHome) }
        }
    }

    /** true / false: at home or not; null: the server cannot be reached, or nobody is logged in. */
    private fun call(server: String, method: String): Boolean? {
        val connection = URL("$server$API_PATH").openConnection() as HttpURLConnection
        try {
            connection.requestMethod = method
            connection.instanceFollowRedirects = false
            connection.connectTimeout = TIMEOUT_MS
            connection.readTimeout = TIMEOUT_MS
            connection.setRequestProperty("Accept", "application/json")
            connection.setRequestProperty(APP_HEADER, "1")
            CookieManager.getInstance().getCookie(server)?.let { connection.setRequestProperty("Cookie", it) }
            if (method == "POST") {
                // No body: the server switches between home and out.
                connection.doOutput = true
                connection.setFixedLengthStreamingMode(0)
                connection.outputStream.close()
            }
            if (connection.responseCode != HttpURLConnection.HTTP_OK) {
                return null
            }
            val body = connection.inputStream.bufferedReader().use { it.readText() }
            return JSONObject(body).getBoolean("atHome")
        } finally {
            connection.disconnect()
        }
    }

    private fun show(atHome: Boolean?) {
        val tile = qsTile ?: return
        tile.state = when (atHome) {
            true -> Tile.STATE_ACTIVE
            false -> Tile.STATE_INACTIVE
            null -> Tile.STATE_UNAVAILABLE
        }
        if (Build.VERSION.SDK_INT >= Build.VERSION_CODES.Q) {
            tile.subtitle = getString(
                when (atHome) {
                    true -> R.string.tile_home
                    false -> R.string.tile_away
                    null -> R.string.tile_offline
                }
            )
        }
        tile.updateTile()
    }

    companion object {
        private const val API_PATH = "/api/presence"
        /** Required by the server on POST: a cross-site form cannot send it (CSRF protection). */
        private const val APP_HEADER = "X-Companero-App"
        private const val TIMEOUT_MS = 5_000
        private val executor = Executors.newSingleThreadExecutor()
    }
}
