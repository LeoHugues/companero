package app.companero

import android.app.Activity
import android.app.PendingIntent
import android.content.BroadcastReceiver
import android.content.Context
import android.content.Intent
import android.content.pm.PackageInstaller
import android.os.Build
import android.util.Log
import android.widget.Toast
import androidx.core.content.IntentCompat
import androidx.core.content.edit
import com.google.android.material.dialog.MaterialAlertDialogBuilder
import org.json.JSONObject
import java.io.File
import java.net.HttpURLConnection
import java.net.URL
import java.security.MessageDigest
import kotlin.concurrent.thread

/**
 * Keeps the app up to date on its own. The server publishes the latest build next to its
 * description (`/companero-apk.json`, written by the CI: see docs/deploiement.md); when it is
 * newer than this one, the app downloads it, checks it, and installs it over itself — no need to
 * uninstall: every build is signed with the same key.
 *
 * The first time, Android asks once to allow Companero to install apps, and to confirm. Then, on
 * Android 12 and later, the next updates install silently when the app goes to the background.
 */
object Updater {
    private const val TAG = "CompaneroUpdate"
    private const val DESCRIPTION = "/companero-apk.json"
    private const val CHECK_EVERY_MS = 30 * 60 * 1000L

    @Volatile private var busy = false
    /** Downloaded and checked, waiting for the app to go to the background to be installed silently. */
    @Volatile private var ready: File? = null

    /** On every return to the app: is there a newer build on the server? (At most every half hour.) */
    fun check(activity: Activity) {
        val prefs = activity.getSharedPreferences("updater", Context.MODE_PRIVATE)
        val now = System.currentTimeMillis()
        if (busy || ready != null || now - prefs.getLong("checkedAt", 0) < CHECK_EVERY_MS) return
        busy = true
        prefs.edit { putLong("checkedAt", now) }

        val context = activity.applicationContext
        val server = Server.url(activity)
        thread(name = "companero-update") {
            try {
                val latest = JSONObject(read("$server$DESCRIPTION"))
                if (latest.getLong("versionCode") <= BuildConfig.VERSION_CODE) return@thread

                val apk = File(context.cacheDir, "companero-update.apk")
                download(server + latest.optString("path", "/companero.apk"), apk)
                if (!sha256(apk).equals(latest.getString("sha256"), ignoreCase = true)) {
                    Log.w(TAG, "Downloaded build does not match its checksum")
                    apk.delete()
                    return@thread
                }

                if (canInstallSilently(context)) {
                    ready = apk
                } else {
                    activity.runOnUiThread { offer(activity, apk, latest.optString("versionName")) }
                }
            } catch (e: Exception) {
                Log.i(TAG, "No update: ${e.message}")
            } finally {
                busy = false
            }
        }
    }

    /** The app goes to the background: a build waiting to be installed silently is installed now. */
    fun installIfReady(context: Context) {
        val apk = ready ?: return
        ready = null
        install(context.applicationContext, apk)
    }

    /** Asks before installing: Android will ask too, the first time. */
    private fun offer(activity: Activity, apk: File, versionName: String) {
        if (activity.isFinishing || activity.isDestroyed) return
        MaterialAlertDialogBuilder(activity)
            .setTitle(R.string.update_title)
            .setMessage(activity.getString(R.string.update_message, versionName))
            .setNegativeButton(R.string.update_later, null)
            .setPositiveButton(R.string.update_install) { _, _ ->
                Toast.makeText(activity, R.string.update_installing, Toast.LENGTH_SHORT).show()
                install(activity.applicationContext, apk)
            }
            .show()
    }

    /**
     * Android 12+ installs an update without asking when the app installed itself before and may
     * install apps: from the second update on.
     */
    private fun canInstallSilently(context: Context): Boolean {
        if (Build.VERSION.SDK_INT < Build.VERSION_CODES.S) return false
        if (!context.packageManager.canRequestPackageInstalls()) return false
        return runCatching {
            context.packageManager.getInstallSourceInfo(context.packageName).installingPackageName == context.packageName
        }.getOrDefault(false)
    }

    private fun install(context: Context, apk: File) {
        thread(name = "companero-install") {
            try {
                val installer = context.packageManager.packageInstaller
                val params = PackageInstaller.SessionParams(PackageInstaller.SessionParams.MODE_FULL_INSTALL).apply {
                    setAppPackageName(context.packageName)
                    if (Build.VERSION.SDK_INT >= Build.VERSION_CODES.S) {
                        setRequireUserAction(PackageInstaller.SessionParams.USER_ACTION_NOT_REQUIRED)
                    }
                }
                val sessionId = installer.createSession(params)
                installer.openSession(sessionId).use { session ->
                    session.openWrite("companero.apk", 0, apk.length()).use { output ->
                        apk.inputStream().use { it.copyTo(output) }
                        session.fsync(output)
                    }
                    val flags = PendingIntent.FLAG_UPDATE_CURRENT or
                        (if (Build.VERSION.SDK_INT >= Build.VERSION_CODES.S) PendingIntent.FLAG_MUTABLE else 0)
                    val callback = PendingIntent.getBroadcast(context, sessionId, Intent(context, UpdateReceiver::class.java), flags)
                    session.commit(callback.intentSender)
                }
            } catch (e: Exception) {
                Log.w(TAG, "Update could not be installed", e)
            }
        }
    }

    private fun read(url: String): String =
        connect(url).run { inputStream.bufferedReader().use { it.readText() }.also { disconnect() } }

    private fun download(url: String, to: File) {
        val connection = connect(url)
        try {
            connection.inputStream.use { input -> to.outputStream().use { input.copyTo(it) } }
        } finally {
            connection.disconnect()
        }
    }

    private fun connect(url: String): HttpURLConnection =
        (URL(url).openConnection() as HttpURLConnection).apply {
            connectTimeout = 10_000
            readTimeout = 60_000
            setRequestProperty("Cache-Control", "no-cache")
            if (responseCode !in 200..299) {
                disconnect()
                throw IllegalStateException("HTTP $responseCode for $url")
            }
        }

    private fun sha256(file: File): String {
        val digest = MessageDigest.getInstance("SHA-256")
        file.inputStream().use { input ->
            val buffer = ByteArray(64 * 1024)
            while (true) {
                val read = input.read(buffer)
                if (read < 0) break
                digest.update(buffer, 0, read)
            }
        }
        return digest.digest().joinToString("") { "%02x".format(it) }
    }
}

/** What Android says about an update being installed. */
class UpdateReceiver : BroadcastReceiver() {
    override fun onReceive(context: Context, intent: Intent) {
        when (intent.getIntExtra(PackageInstaller.EXTRA_STATUS, PackageInstaller.STATUS_FAILURE)) {
            // The first time: Android shows its own confirmation (and asks to allow installing apps).
            PackageInstaller.STATUS_PENDING_USER_ACTION -> {
                val confirm = IntentCompat.getParcelableExtra(intent, Intent.EXTRA_INTENT, Intent::class.java) ?: return
                context.startActivity(confirm.addFlags(Intent.FLAG_ACTIVITY_NEW_TASK))
            }
            PackageInstaller.STATUS_SUCCESS -> Log.i("CompaneroUpdate", "Updated")
            else -> Log.w("CompaneroUpdate", "Update failed: ${intent.getStringExtra(PackageInstaller.EXTRA_STATUS_MESSAGE)}")
        }
    }
}
