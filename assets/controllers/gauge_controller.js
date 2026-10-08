import { Controller } from '@hotwired/stimulus';
import { calm, remember } from '../lib/fx.js';

/*
 * A progress bar that rises when the page shows: from where it was last time (a gauge with a
 * key, e.g. the Casa's cleanliness, which then shows its gain), or from zero.
 */
export default class extends Controller {
    static targets = ['fill'];
    static values = { value: Number, key: String, delay: Number };

    connect() {
        const previous = this.hasKeyValue ? remember(this.keyValue, this.valueValue) : null;
        this.rise(previous !== null && previous !== this.valueValue ? previous : 0, this.valueValue);
        this.connected = true;
    }

    /** The page was refreshed in place (Turbo morphing): rise from the old value. */
    valueValueChanged(value, old) {
        if (this.connected && old !== undefined && old !== value) {
            if (this.hasKeyValue) {
                remember(this.keyValue, value);
            }
            this.rise(old, value);
        }
    }

    rise(from, to) {
        if (calm()) {
            return;
        }
        const fill = this.fillTarget;
        const gain = to - from;
        const slow = from > 0;
        fill.style.transition = 'none';
        fill.style.width = `${from}%`;
        void fill.offsetWidth;
        setTimeout(() => {
            fill.style.transition = `width ${slow ? 1300 : 900}ms cubic-bezier(.25, 1, .35, 1)`;
            fill.style.width = `${to}%`;
            this.element.classList.add('is-filling');
        }, this.delayValue + (slow ? 450 : 120));
        fill.addEventListener('transitionend', () => {
            this.element.classList.remove('is-filling');
            if (slow && gain > 0) {
                this.celebrate(gain);
            }
        }, { once: true });
    }

    /** It went up: a glow, and the gain in a bubble above the end of the bar. */
    celebrate(gain) {
        this.element.classList.add('is-up');
        setTimeout(() => this.element.classList.remove('is-up'), 1200);
        const chip = document.createElement('span');
        chip.className = 'gauge-gain';
        chip.textContent = `+${gain} %`;
        chip.style.left = `${this.valueValue}%`;
        chip.setAttribute('aria-hidden', 'true');
        chip.addEventListener('animationend', () => chip.remove(), { once: true });
        this.element.appendChild(chip);
    }
}
