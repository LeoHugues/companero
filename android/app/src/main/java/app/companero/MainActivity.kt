package app.companero

import android.os.Bundle
import android.view.View
import androidx.activity.SystemBarStyle
import androidx.activity.enableEdgeToEdge
import androidx.core.content.ContextCompat
import androidx.core.view.ViewCompat
import androidx.core.view.WindowInsetsCompat
import androidx.core.view.updatePadding
import dev.hotwire.navigation.activities.HotwireActivity
import dev.hotwire.navigation.navigator.NavigatorConfiguration

class MainActivity : HotwireActivity() {
    override fun onCreate(savedInstanceState: Bundle?) {
        val cream = ContextCompat.getColor(this, R.color.cream)
        enableEdgeToEdge(
            statusBarStyle = SystemBarStyle.light(cream, cream),
            navigationBarStyle = SystemBarStyle.light(cream, cream),
        )
        super.onCreate(savedInstanceState)
        setContentView(R.layout.activity_main)

        // Keep the pages clear of the status bar, the navigation bar and the keyboard.
        ViewCompat.setOnApplyWindowInsetsListener(findViewById<View>(R.id.main_nav_host)) { view, insets ->
            val bars = insets.getInsets(WindowInsetsCompat.Type.systemBars() or WindowInsetsCompat.Type.ime())
            view.updatePadding(bars.left, bars.top, bars.right, bars.bottom)
            WindowInsetsCompat.CONSUMED
        }
    }

    override fun onResume() {
        super.onResume()
        // A newer build on the server? It is downloaded, then installed (see Updater).
        Updater.check(this)
    }

    override fun onStop() {
        super.onStop()
        // Leaving the app: the right moment for a silent update.
        Updater.installIfReady(this)
    }

    override fun onRequestPermissionsResult(requestCode: Int, permissions: Array<out String>, grantResults: IntArray) {
        super.onRequestPermissionsResult(requestCode, permissions, grantResults)
        if (requestCode == Permissions.REQUEST_CODE) {
            Permissions.answered()
        }
    }

    override fun navigatorConfigurations() = listOf(
        NavigatorConfiguration(
            name = "main",
            // Opened from a notification: straight to the page it is about, on this server.
            startLocation = intent?.getStringExtra(EXTRA_LOCATION)?.takeIf { it.startsWith(Server.url(this)) }
                ?: Server.startLocation(this),
            navigatorHostId = R.id.main_nav_host,
        )
    )

    companion object {
        const val EXTRA_LOCATION = "location"
    }
}
