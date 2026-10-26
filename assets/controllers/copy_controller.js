import { Controller } from '@hotwired/stimulus';

/*
 * The invitation link: "Copier" puts it in the clipboard; "Envoyer" opens the phone's sharing (a message, WhatsApp…)
 * with a word to go with it, or copies it where there is no such thing (the app's web view).
 */
export default class extends Controller {
    static targets = ['source', 'label'];
    static values = { text: String, title: String };

    async copy() {
        try {
            await navigator.clipboard.writeText(this.sourceTarget.value);
            this.labelTargets.forEach((label) => { label.textContent = 'Lien copié !'; });
        } catch {
            this.sourceTarget.select();
        }
    }

    async share() {
        if (!navigator.share) {
            await this.copy();
            return;
        }
        try {
            await navigator.share({ title: this.titleValue, text: this.textValue, url: this.sourceTarget.value });
        } catch {
            // Closed without sending: nothing to say.
        }
    }
}
