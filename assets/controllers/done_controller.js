import { Controller } from '@hotwired/stimulus';
import { burst } from '../lib/fx.js';

/*
 * "C'est fait": the button answers at once — it squashes, turns green, throws stars, and its card
 * turns over to show its back — while the page refreshes. Its vibration comes with the tap (data-haptic="press").
 */
export default class extends Controller {
    celebrate(event) {
        const button = event.submitter ?? this.element.querySelector('button');
        if (!button) {
            return;
        }
        button.classList.add('is-done-pressed');
        this.element.closest('.play-card')?.classList.add('is-turning');
        const box = button.getBoundingClientRect();
        burst(document.body, box.left + box.width / 2 + window.scrollX, box.top + box.height / 2 + window.scrollY, { count: 10, spread: 56, kinds: ['star'], colors: ['#3E9B62', '#F6C453', '#E8692C'] });
    }
}
