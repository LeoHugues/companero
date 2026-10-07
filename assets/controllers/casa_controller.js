import { Controller } from '@hotwired/stimulus';

/*
 * La Casa jumps for joy when a task has just been done, then goes back to her usual self.
 * The server renders the "reacting" state; this controller only ends it.
 */
export default class extends Controller {
    static targets = ['speech', 'body'];
    static values = { reacting: Boolean, restSpeech: String, duration: { type: Number, default: 2600 } };

    reactingValueChanged(reacting) {
        clearTimeout(this.timeout);
        if (reacting) {
            this.timeout = setTimeout(() => this.calmDown(), this.durationValue);
        }
    }

    disconnect() {
        clearTimeout(this.timeout);
    }

    calmDown() {
        if (this.hasSpeechTarget) {
            this.speechTarget.textContent = this.restSpeechValue;
        }
        this.bodyTarget.classList.replace('animate-bounce-joy', 'animate-breathe');
    }
}
