import { Controller } from '@hotwired/stimulus';
import { countTo, remember } from '../lib/fx.js';

/*
 * A number that counts up when the page shows (points, XP, cleanliness…): from what it showed
 * last time with a key, or from zero. The server writes the real number: nothing is lost without JS.
 */
export default class extends Controller {
    static values = { to: Number, key: String, duration: { type: Number, default: 900 }, delay: Number };

    connect() {
        const previous = this.hasKeyValue ? remember(this.keyValue, this.toValue) : null;
        this.prefix = this.element.dataset.prefix ?? '';
        this.suffix = this.element.dataset.suffix ?? '';
        this.run(previous ?? 0, this.toValue);
        this.connected = true;
    }

    toValueChanged(value, old) {
        if (this.connected && old !== undefined && old !== value) {
            if (this.hasKeyValue) {
                remember(this.keyValue, value);
            }
            this.run(old, value);
        }
    }

    run(from, to) {
        const options = { duration: from ? this.durationValue * 1.4 : this.durationValue, prefix: this.prefix, suffix: this.suffix };
        this.element.textContent = `${this.prefix}${Math.round(from).toLocaleString('fr-FR')}${this.suffix}`;
        setTimeout(() => {
            countTo(this.element, from, to, options).then(() => {
                if (to > from && from > 0) {
                    this.element.classList.remove('count-bump');
                    void this.element.offsetWidth;
                    this.element.classList.add('count-bump');
                }
            });
        }, this.delayValue + (from ? 450 : 120));
    }
}
