import { Controller } from '@hotwired/stimulus';
import { burst } from '../lib/fx.js';

/*
 * "C'est acheté": the round box answers at once — it fills with sage and throws a few stars, the name is crossed out
 * and the row fades — while the list comes back without it. Its vibration comes with the tap (data-haptic="press").
 */
export default class extends Controller {
    buy(event) {
        const row = event.target.closest('.shopping-row');
        const button = event.submitter ?? event.target.querySelector('button');
        if (!row || !button) {
            return;
        }
        row.classList.add('is-bought');
        const box = button.getBoundingClientRect();
        burst(document.body, box.left + box.width / 2 + window.scrollX, box.top + box.height / 2 + window.scrollY, { count: 7, spread: 44, kinds: ['star'], colors: ['#6E9A4C', '#F6C453', '#E8692C'] });
    }
}
