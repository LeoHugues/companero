package app.companero

import android.Manifest
import android.app.NotificationChannel
import android.app.NotificationManager
import android.app.PendingIntent
import android.content.Context
import android.content.Intent
import android.content.pm.PackageManager
import android.os.Build
import android.webkit.CookieManager
import androidx.core.app.NotificationCompat
import androidx.core.app.NotificationManagerCompat
import androidx.core.content.ContextCompat
import androidx.core.content.edit
import androidx.work.Constraints
import androidx.work.ExistingPeriodicWorkPolicy
import androidx.work.NetworkType
import androidx.work.PeriodicWorkRequestBuilder
import androidx.work.WorkManager
import androidx.work.Worker
import androidx.work.WorkerParameters
import org.json.JSONArray
import java.net.HttpURLConnection
import java.net.URL
import java.util.concurrent.TimeUnit

/**
 * The coloc's reminders as the phone's notifications. Every quarter of an hour (the shortest
 * Android allows in the background), [ReminderWorker] asks the server what to tell (GET
 * /api/notifications, with the WebView's session cookies — see src/Notification/NoticeBoard.php)
 * and shows what was not shown yet: each notice has a key, shown once.
 */
object Notifier {
    private const val CHANNEL = "reminders"
    private const val API_PATH = "/api/notifications"
    private const val WORK = "reminders"
    private const val TIMEOUT_MS = 10_000
    /** Enough to remember a few weeks of keys. */
    private const val SEEN_LIMIT = 300

    /** What came of a check: how many were shown, or why none could be. */
    sealed interface Outcome {
        data class Shown(val count: Int) : Outcome
        data object Blocked : Outcome
        data object LoggedOut : Outcome
        data class Offline(val reason: String) : Outcome
    }

    fun setUp(context: Context) {
        createChannel(context)
        val request = PeriodicWorkRequestBuilder<ReminderWorker>(15, TimeUnit.MINUTES)
            .setConstraints(Constraints.Builder().setRequiredNetworkType(NetworkType.CONNECTED).build())
            .build()
        WorkManager.getInstance(context).enqueueUniquePeriodicWork(WORK, ExistingPeriodicWorkPolicy.KEEP, request)
    }

    fun isAllowed(context: Context): Boolean {
        val granted = Build.VERSION.SDK_INT < Build.VERSION_CODES.TIRAMISU ||
            ContextCompat.checkSelfPermission(context, Manifest.permission.POST_NOTIFICATIONS) == PackageManager.PERMISSION_GRANTED
        return granted && NotificationManagerCompat.from(context).areNotificationsEnabled()
    }

    /** Asks the server, shows what is new. [test]: the server adds a test notice first. Blocking: not on the main thread. */
    fun check(context: Context, test: Boolean = false): Outcome {
        if (!isAllowed(context)) return Outcome.Blocked
        val server = Server.url(context)
        val notices = try {
            fetch(server, test) ?: return Outcome.LoggedOut
        } catch (e: Exception) {
            return Outcome.Offline(e.message ?: e.javaClass.simpleName)
        }

        val seen = seenKeys(context)
        var shown = 0
        for (i in 0 until notices.length()) {
            val notice = notices.getJSONObject(i)
            val key = notice.getString("key")
            if (key in seen) continue
            show(context, server, key, notice.getString("title"), notice.getString("body"), notice.optString("path", "/"))
            seen += key
            shown++
        }
        remember(context, seen)
        return Outcome.Shown(shown)
    }

    /** null: nobody is logged in (the server redirects to its login page). */
    private fun fetch(server: String, test: Boolean): JSONArray? {
        val connection = URL(server + API_PATH + if (test) "?test=1" else "").openConnection() as HttpURLConnection
        try {
            connection.instanceFollowRedirects = false
            connection.connectTimeout = TIMEOUT_MS
            connection.readTimeout = TIMEOUT_MS
            connection.setRequestProperty("Accept", "application/json")
            CookieManager.getInstance().getCookie(server)?.let { connection.setRequestProperty("Cookie", it) }
            if (connection.responseCode != HttpURLConnection.HTTP_OK) return null
            return JSONArray(connection.inputStream.bufferedReader().use { it.readText() })
        } finally {
            connection.disconnect()
        }
    }

    private fun show(context: Context, server: String, key: String, title: String, body: String, path: String) {
        // A tap opens the app on the page it is about.
        val open = Intent(context, MainActivity::class.java)
            .putExtra(MainActivity.EXTRA_LOCATION, server + path)
            .addFlags(Intent.FLAG_ACTIVITY_NEW_TASK or Intent.FLAG_ACTIVITY_CLEAR_TASK)
        val tap = PendingIntent.getActivity(context, key.hashCode(), open, PendingIntent.FLAG_UPDATE_CURRENT or PendingIntent.FLAG_IMMUTABLE)
        val notification = NotificationCompat.Builder(context, CHANNEL)
            .setSmallIcon(R.drawable.ic_notification)
            .setColor(ContextCompat.getColor(context, R.color.clay))
            .setContentTitle(title)
            .setContentText(body)
            .setStyle(NotificationCompat.BigTextStyle().bigText(body))
            .setContentIntent(tap)
            .setAutoCancel(true)
            .build()
        try {
            NotificationManagerCompat.from(context).notify(key.hashCode(), notification)
        } catch (e: SecurityException) {
            // The permission was taken back in the meantime: never mind.
        }
    }

    private fun createChannel(context: Context) {
        val channel = NotificationChannel(CHANNEL, context.getString(R.string.notification_channel), NotificationManager.IMPORTANCE_DEFAULT)
        channel.description = context.getString(R.string.notification_channel_description)
        context.getSystemService(NotificationManager::class.java)?.createNotificationChannel(channel)
    }

    private fun seenKeys(context: Context): MutableList<String> =
        prefs(context).getString("seen", null)?.split('\n')?.filter { it.isNotEmpty() }?.toMutableList() ?: mutableListOf()

    private fun remember(context: Context, seen: List<String>) {
        prefs(context).edit { putString("seen", seen.takeLast(SEEN_LIMIT).joinToString("\n")) }
    }

    private fun prefs(context: Context) = context.getSharedPreferences("notifications", Context.MODE_PRIVATE)
}

/** Every quarter of an hour, with a network: what the coloc has to tell. */
class ReminderWorker(context: Context, params: WorkerParameters) : Worker(context, params) {
    override fun doWork(): Result {
        Notifier.check(applicationContext)
        // Offline or logged out: the next round will tell.
        return Result.success()
    }
}
