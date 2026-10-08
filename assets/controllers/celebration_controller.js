import { Controller } from '@hotwired/stimulus';
import { burst, buzz, calm, confetti } from '../lib/fx.js';

/*
 * What was just earned: the points slide in from the top with a burst of stars; then each
 * reward opens in turn — a surprise, a new level, a yellow card received —, its box shaking
 * until it is tapped. See templates/_celebration.html.twig.
 */
export default class extends Controller {
    static targets = ['toast', 'dialog'];

    connect() {
        this.queue = [...this.dialogTargets];
        if (this.hasToastTarget) {
            requestAnimationFrame(() => this.cheer(this.toastTarget));
        }
        // The points first, then the boxes.
        this.timer = setTimeout(() => this.next(), this.hasToastTarget ? 1100 : 250);
    }

    disconnect() {
        clearTimeout(this.timer);
    }

    cheer(toast) {
        const box = toast.getBoundingClientRect();
        burst(document.body, box.left + 26 + window.scrollX, box.top + box.height / 2 + window.scrollY, { count: 8, spread: 46, kinds: ['star'] });
        buzz('success');
    }

    next() {
        const dialog = this.queue.shift();
        if (!dialog) {
            return;
        }
        dialog.addEventListener('close', () => {
            this.timer = setTimeout(() => this.next(), 250);
        }, { once: true });
        dialog.addEventListener('click', (event) => {
            // A tap on the backdrop closes it, once opened.
            if (event.target === dialog && dialog.classList.contains('is-open')) {
                dialog.close();
            }
        });
        dialog.showModal();
        if (!dialog.querySelector('.reward-box')) {
            this.reveal(dialog);
        }
    }

    open(event) {
        const dialog = event.currentTarget.closest('dialog');
        if (dialog.classList.contains('is-open')) {
            return;
        }
        const card = dialog.querySelector('.reward-card');
        const box = event.currentTarget.getBoundingClientRect();
        const origin = card.getBoundingClientRect();
        dialog.classList.add('is-opening');
        setTimeout(() => {
            this.reveal(dialog);
            if (!calm()) {
                burst(card, box.left + box.width / 2 - origin.left, box.top + box.height / 2 - origin.top, { count: 16, spread: 120 });
            }
        }, calm() ? 0 : 380);
    }

    reveal(dialog) {
        dialog.classList.remove('is-opening');
        dialog.classList.add('is-open');
        if (dialog.dataset.confetti) {
            confetti({ parent: dialog });
        }
        buzz(dialog.dataset.hapticOnOpen ?? 'reward');
        dialog.querySelector('form button[type=submit]')?.focus();
    }
}
