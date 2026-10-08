package app.companero

import android.content.Context
import android.os.Build
import android.os.VibrationEffect
import android.os.VibrationEffect.Composition
import android.os.Vibrator
import android.os.VibratorManager
import dev.hotwire.core.bridge.BridgeComponent
import dev.hotwire.core.bridge.BridgeDelegate
import dev.hotwire.core.bridge.Message
import dev.hotwire.navigation.destinations.HotwireDestination
import org.json.JSONObject

/**
 * The phone's own vibrations for every feedback of the pages, by name (assets/lib/fx.js, buzz()):
 * a tick under the finger, a press, points earned, a gauge that rises, a box that opens, a cat
 * that purrs… Played with the vibrator's primitives (real clicks, rises and thuds) when the phone
 * has them, otherwise as a waveform with its strengths.
 */
class HapticsComponent(
    name: String,
    private val delegate: BridgeDelegate<HotwireDestination>,
) : BridgeComponent<HotwireDestination>(name, delegate) {

    override fun onReceive(message: Message) {
        if (message.event != "vibrate") return
        val effect = runCatching { JSONObject(message.jsonData).optString("effect", "tick") }.getOrDefault("tick")
        val context = delegate.destination.fragment.context ?: return
        Haptics.play(context, effect)
    }
}

object Haptics {
    /** A primitive of a composition: which one, how strong (0–1), after how long (ms). */
    private class Step(val primitive: Int, val scale: Float, val delay: Int = 0)

    private val compositions: Map<String, List<Step>> = mapOf(
        "tick" to listOf(Step(Composition.PRIMITIVE_TICK, 0.6f)),
        "toggle" to listOf(Step(Composition.PRIMITIVE_CLICK, 0.5f), Step(Composition.PRIMITIVE_TICK, 0.8f, 50)),
        "press" to listOf(Step(Composition.PRIMITIVE_CLICK, 1f)),
        "success" to listOf(
            Step(Composition.PRIMITIVE_QUICK_RISE, 0.7f),
            Step(Composition.PRIMITIVE_CLICK, 1f, 30),
            Step(Composition.PRIMITIVE_CLICK, 0.6f, 70),
        ),
        "rise" to listOf(Step(Composition.PRIMITIVE_SLOW_RISE, 0.7f), Step(Composition.PRIMITIVE_TICK, 1f)),
        "reward" to listOf(
            Step(Composition.PRIMITIVE_THUD, 0.8f),
            Step(Composition.PRIMITIVE_QUICK_RISE, 1f, 60),
            Step(Composition.PRIMITIVE_CLICK, 1f, 40),
            Step(Composition.PRIMITIVE_CLICK, 0.7f, 70),
            Step(Composition.PRIMITIVE_CLICK, 0.5f, 70),
        ),
        "levelup" to listOf(
            Step(Composition.PRIMITIVE_SPIN, 0.8f),
            Step(Composition.PRIMITIVE_QUICK_RISE, 1f, 40),
            Step(Composition.PRIMITIVE_CLICK, 1f, 90),
            Step(Composition.PRIMITIVE_CLICK, 1f, 90),
            Step(Composition.PRIMITIVE_THUD, 1f, 90),
        ),
        "card" to listOf(Step(Composition.PRIMITIVE_THUD, 1f), Step(Composition.PRIMITIVE_CLICK, 0.6f, 130)),
        "alert" to listOf(Step(Composition.PRIMITIVE_THUD, 0.8f), Step(Composition.PRIMITIVE_THUD, 0.8f, 100)),
        "giggle" to List(4) { Step(Composition.PRIMITIVE_TICK, 0.7f, if (it == 0) 0 else 55) },
        "dust" to listOf(Step(Composition.PRIMITIVE_TICK, 0.3f)),
        "sparkle" to listOf(
            Step(Composition.PRIMITIVE_TICK, 0.5f),
            Step(Composition.PRIMITIVE_TICK, 0.7f, 45),
            Step(Composition.PRIMITIVE_TICK, 0.9f, 45),
            Step(Composition.PRIMITIVE_CLICK, 1f, 70),
        ),
        "purr" to List(12) { Step(Composition.PRIMITIVE_LOW_TICK, if (it % 2 == 0) 0.9f else 0.5f, if (it == 0) 0 else 40) },
        "sneeze" to listOf(Step(Composition.PRIMITIVE_QUICK_FALL, 0.6f), Step(Composition.PRIMITIVE_THUD, 0.8f, 40)),
    )

    /** For phones without primitives: [durations] and [strengths] (1–255), off/on in turn. */
    private val waveforms: Map<String, Pair<LongArray, IntArray>> = mapOf(
        "tick" to (longArrayOf(0, 10) to intArrayOf(0, 90)),
        "toggle" to (longArrayOf(0, 12, 40, 16) to intArrayOf(0, 120, 0, 180)),
        "press" to (longArrayOf(0, 22) to intArrayOf(0, 255)),
        "success" to (longArrayOf(0, 16, 50, 16, 50, 32) to intArrayOf(0, 140, 0, 200, 0, 255)),
        "rise" to (longArrayOf(0, 20, 20, 20, 20, 20, 20, 30) to intArrayOf(0, 40, 0, 90, 0, 150, 0, 230)),
        "reward" to (longArrayOf(0, 40, 60, 20, 40, 20, 40, 60) to intArrayOf(0, 255, 0, 160, 0, 200, 0, 255)),
        "levelup" to (longArrayOf(0, 60, 60, 20, 40, 20, 40, 20, 40, 90) to intArrayOf(0, 120, 0, 180, 0, 220, 0, 255, 0, 255)),
        "card" to (longArrayOf(0, 70, 120, 25) to intArrayOf(0, 255, 0, 140)),
        "alert" to (longArrayOf(0, 30, 90, 30) to intArrayOf(0, 220, 0, 220)),
        "giggle" to (longArrayOf(0, 10, 45, 10, 45, 10, 45, 10) to intArrayOf(0, 110, 0, 110, 0, 110, 0, 110)),
        "dust" to (longArrayOf(0, 6) to intArrayOf(0, 50)),
        "sparkle" to (longArrayOf(0, 10, 35, 10, 35, 10, 35, 40) to intArrayOf(0, 90, 0, 140, 0, 190, 0, 255)),
        "purr" to (LongArray(24) { if (it % 2 == 0) 22L else 28L } to IntArray(24) { if (it % 2 == 0) 0 else if (it % 4 == 1) 120 else 70 }),
        "sneeze" to (longArrayOf(0, 8, 60, 35) to intArrayOf(0, 60, 0, 230)),
    )

    fun play(context: Context, effect: String) {
        val vibrator = vibrator(context) ?: return
        if (!vibrator.hasVibrator()) return

        val steps = compositions[effect] ?: compositions.getValue("tick")
        if (Build.VERSION.SDK_INT >= Build.VERSION_CODES.R &&
            vibrator.areAllPrimitivesSupported(*steps.map { it.primitive }.distinct().toIntArray())
        ) {
            val composition = VibrationEffect.startComposition()
            steps.forEach { composition.addPrimitive(it.primitive, it.scale, it.delay) }
            vibrator.vibrate(composition.compose())
            return
        }

        val (timings, amplitudes) = waveforms[effect] ?: waveforms.getValue("tick")
        vibrator.vibrate(
            if (vibrator.hasAmplitudeControl()) VibrationEffect.createWaveform(timings, amplitudes, -1)
            else VibrationEffect.createWaveform(timings, -1)
        )
    }

    private fun vibrator(context: Context): Vibrator? =
        if (Build.VERSION.SDK_INT >= Build.VERSION_CODES.S) {
            context.getSystemService(VibratorManager::class.java)?.defaultVibrator
        } else {
            context.getSystemService(Vibrator::class.java)
        }
}
