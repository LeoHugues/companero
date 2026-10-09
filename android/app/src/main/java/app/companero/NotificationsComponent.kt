package app.companero

import android.Manifest
import android.app.Activity
import android.content.Context
import android.content.Intent
import android.os.Build
import android.os.Handler
import android.os.Looper
import android.provider.Settings
import androidx.core.app.ActivityCompat
import androidx.core.content.edit
import dev.hotwire.core.bridge.BridgeComponent
import dev.hotwire.core.bridge.BridgeDelegate
import dev.hotwire.core.bridge.Message
import dev.hotwire.navigation.destinations.HotwireDestination
import org.json.JSONObject
import java.util.concurrent.Executors

/**
 * The profile's "Notifications" card (assets/controllers/notifications_controller.js): whether
 * the phone lets Companero notify, asking for it, and a test notification right away.
 */
class NotificationsComponent(
    name: String,
    private val delegate: BridgeDelegate<HotwireDestination>,
) : BridgeComponent<HotwireDestination>(name, delegate) {
    private val main = Handler(Looper.getMainLooper())

    override fun onReceive(message: Message) {
        val activity = delegate.destination.fragment.activity ?: return
        when (message.event) {
            "status" -> replyStatus("status")
            "allow" -> {
                val asked = Build.VERSION.SDK_INT >= Build.VERSION_CODES.TIRAMISU &&
                    !Notifier.isAllowed(activity) &&
                    (ActivityCompat.shouldShowRequestPermissionRationale(activity, Manifest.permission.POST_NOTIFICATIONS) || !Permissions.askedBefore(activity))
                if (asked) {
                    // The answer comes back to MainActivity, then here.
                    Permissions.onResult = { main.post { replyStatus("allow") } }
                    Permissions.markAsked(activity)
                    ActivityCompat.requestPermissions(activity, arrayOf(Manifest.permission.POST_NOTIFICATIONS), Permissions.REQUEST_CODE)
                } else {
                    // Refused for good, or turned off by hand: only the phone's settings can bring them back.
                    activity.startActivity(
                        Intent(Settings.ACTION_APP_NOTIFICATION_SETTINGS).putExtra(Settings.EXTRA_APP_PACKAGE, activity.packageName)
                    )
                    replyStatus("allow")
                }
            }
            "test" -> {
                val context = activity.applicationContext
                executor.execute {
                    val outcome = Notifier.check(context, test = true)
                    val reply = JSONObject()
                    when (outcome) {
                        is Notifier.Outcome.Shown -> reply.put("shown", outcome.count)
                        Notifier.Outcome.Blocked -> reply.put("error", context.getString(R.string.notification_blocked))
                        Notifier.Outcome.LoggedOut -> reply.put("error", context.getString(R.string.notification_logged_out))
                        is Notifier.Outcome.Offline -> reply.put("error", context.getString(R.string.notification_offline, outcome.reason))
                    }
                    main.post { replyTo("test", reply.toString()) }
                }
            }
        }
    }

    private fun replyStatus(event: String) {
        val context = delegate.destination.fragment.context ?: return
        replyTo(event, JSONObject().put("granted", Notifier.isAllowed(context)).toString())
    }

    companion object {
        private val executor = Executors.newSingleThreadExecutor()
    }
}

/** The notification permission's answer, from MainActivity to whoever asked. */
object Permissions {
    const val REQUEST_CODE = 42
    var onResult: (() -> Unit)? = null

    fun askedBefore(activity: Activity) = prefs(activity).getBoolean("asked", false)

    fun markAsked(activity: Activity) = prefs(activity).edit { putBoolean("asked", true) }

    fun answered() {
        onResult?.invoke()
        onResult = null
    }

    private fun prefs(context: Context) = context.getSharedPreferences("notifications", Context.MODE_PRIVATE)
}
