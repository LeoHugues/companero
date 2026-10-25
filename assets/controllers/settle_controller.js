import { Controller } from '@hotwired/stimulus';
import { burst } from '../lib/fx.js';

/*
 * "Régler": the button answers at once — it fills with sage and throws a few stars, the row fades — while the
 * balances come back even. Its vibration comes with the tap (data-haptic="press").
 */
export default class extends Controller {
    settle(event) {
        const row = event.target.closest('.settle-row');
        const button = event.submitter ?? event.target.querySelector('button');
        if (!row || !button) {
            return;
        }
        row.classList.add('is-settled');
        const box = button.getBoundingClientRect();
        burst(document.body, box.left + box.width / 2 + window.scrollX, box.top + box.height / 2 + window.scrollY, { count: 8, spread: 50, kinds: ['star'], colors: ['#6E9A4C', '#F6C453', '#E8692C'] });
    }
}
