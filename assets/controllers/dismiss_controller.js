import { Controller } from '@hotwired/stimulus';

/* Lets a flash message fade away on its own. */
export default class extends Controller {
    static values = { after: { type: Number, default: 4000 } };

    connect() {
        this.timeout = setTimeout(() => this.element.remove(), this.afterValue);
    }

    disconnect() {
        clearTimeout(this.timeout);
    }
}
