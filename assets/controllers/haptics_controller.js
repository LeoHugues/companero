import { BridgeComponent } from '@hotwired/hotwire-native-bridge';

/*
 * In the Android app only (Hotwire Native bridge, android/…/HapticsComponent.kt): the phone's own
 * vibrations, by name, for every feedback — see buzz() in assets/lib/fx.js.
 */
export default class extends BridgeComponent {
    static component = 'haptics';

    connect() {
        super.connect();
        window.companeroHaptics = this;
    }

    disconnect() {
        if (window.companeroHaptics === this) {
            window.companeroHaptics = null;
        }
        super.disconnect();
    }

    play(effect) {
        this.send('vibrate', { effect });
    }
}
