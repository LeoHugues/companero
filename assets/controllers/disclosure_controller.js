import { Controller } from '@hotwired/stimulus';

/*
 * A <details> that stays as it was left: open or closed, across visits in the session and when
 * the page is refreshed in place (morphing would otherwise close it after each "C'est fait").
 */
export default class extends Controller {
    connect() {
        this.restore();
    }

    remember() {
        try {
            sessionStorage.setItem(this.key, this.element.open ? '1' : '0');
        } catch { /* private mode: never mind */ }
    }

    restore(event) {
        if (event && event.target !== this.element) {
            return;
        }
        try {
            const open = sessionStorage.getItem(this.key);
            if (open !== null) {
                this.element.open = open === '1';
            }
        } catch { /* private mode: never mind */ }
    }

    get key() {
        return `companero:disclosure:${this.element.id}`;
    }
}
