package app.companero

import android.annotation.SuppressLint
import android.os.Bundle
import android.text.InputType
import android.view.LayoutInflater
import android.view.View
import android.view.ViewGroup
import android.widget.EditText
import android.widget.FrameLayout
import android.widget.TextView
import com.google.android.material.dialog.MaterialAlertDialogBuilder
import dev.hotwire.core.turbo.errors.VisitError
import dev.hotwire.navigation.destinations.HotwireDestinationDeepLink
import dev.hotwire.navigation.fragments.HotwireWebFragment

/** Every page of the app. Companero draws its own headers, so there is no native toolbar. */
@HotwireDestinationDeepLink(uri = "hotwire://fragment/web")
class WebFragment : HotwireWebFragment() {
    override fun onCreateView(inflater: LayoutInflater, container: ViewGroup?, savedInstanceState: Bundle?): View =
        inflater.inflate(R.layout.fragment_web, container, false)

    @SuppressLint("InflateParams")
    override fun createErrorView(error: VisitError): View =
        layoutInflater.inflate(R.layout.error_server, null).apply {
            val server = Server.url(requireContext())
            findViewById<TextView>(R.id.error_detail).text =
                getString(R.string.error_detail, server, error.description())
            findViewById<View>(R.id.retry).setOnClickListener { Server.restart(requireActivity()) }
            findViewById<View>(R.id.change_server).setOnClickListener { askServerAddress(server) }
        }

    private fun askServerAddress(current: String) {
        val input = EditText(requireContext()).apply {
            inputType = InputType.TYPE_CLASS_TEXT or InputType.TYPE_TEXT_VARIATION_URI
            isSingleLine = true
            setText(current)
            setSelection(text.length)
        }
        val margin = resources.getDimensionPixelSize(R.dimen.dialog_margin)
        val container = FrameLayout(requireContext()).apply {
            setPadding(margin, 0, margin, 0)
            addView(input)
        }

        MaterialAlertDialogBuilder(requireContext())
            .setTitle(R.string.server_title)
            .setMessage(R.string.server_message)
            .setView(container)
            .setNegativeButton(android.R.string.cancel, null)
            .setPositiveButton(R.string.server_save) { _, _ ->
                Server.change(requireActivity(), input.text.toString())
            }
            .show()
    }
}
