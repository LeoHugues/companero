import { Controller } from '@hotwired/stimulus';
import { burst, buzz, calm } from '../lib/fx.js';

/* A treat given: the phone purrs while the cat munches, then hearts; a tap sends more. */
export default class extends Controller {
    static targets = ['stage'];

    connect() {
        this.timers = [
            setTimeout(() => buzz([30, 50, 30, 50, 30, 50, 30, 50, 30]), 1300),
            setTimeout(() => this.hearts(), 2000),
        ];
        this.stageTarget.addEventListener('pointerup', this.tap);
    }

    disconnect() {
        this.timers.forEach(clearTimeout);
        this.stageTarget.removeEventListener('pointerup', this.tap);
    }

    tap = (event) => {
        const box = this.stageTarget.getBoundingClientRect();
        burst(this.stageTarget, event.clientX - box.left, event.clientY - box.top, { count: 6, spread: 50, kinds: ['heart'] });
        buzz([20, 40, 20]);
    };

    hearts() {
        if (calm()) {
            return;
        }
        const box = this.stageTarget.getBoundingClientRect();
        burst(this.stageTarget, box.width / 2, box.height * 0.45, { count: 12, spread: 110, kinds: ['heart', 'star'], colors: ['#F2A27A', '#F6C453', '#E8692C'] });
    }
}
