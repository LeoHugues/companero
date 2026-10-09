import { BridgeComponent } from '@hotwired/hotwire-native-bridge';

/*
 * In the Android app only (Hotwire Native bridge, android/…/NotificationsComponent.kt): whether the
 * phone lets Companero notify, asking for it, and a test notification sent right away — the same
 * way as the reminders, through the server (/api/notifications?test=1). Outside the app this
 * controller is not loaded: the card keeps its words about the app.
 */
export default class extends BridgeComponent {
    static component = 'notifications';
    static targets = ['state', 'actions', 'allow', 'test'];

    connect() {
        super.connect();
        this.actionsTarget.hidden = false;
        this.send('status', {}, ({ data }) => this.show(data));
    }

    allow() {
        this.send('allow', {}, ({ data }) => this.show(data));
    }

    test() {
        this.testTarget.disabled = true;
        this.stateTarget.textContent = 'Envoi…';
        this.send('test', {}, ({ data }) => {
            this.testTarget.disabled = false;
            this.stateTarget.textContent = data.error
                ? data.error
                : 'C’est envoyé : regarde tes notifications. Les rappels, eux, arrivent tout seuls, vérifiés tous les quarts d’heure.';
        });
    }

    show({ granted }) {
        this.allowTarget.hidden = granted;
        this.stateTarget.textContent = granted
            ? 'Activées sur ce téléphone. Les rappels sont vérifiés tous les quarts d’heure.'
            : 'Bloquées sur ce téléphone : autorise-les pour recevoir les rappels.';
    }
}
