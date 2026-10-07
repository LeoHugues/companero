import { Controller } from '@hotwired/stimulus';

/* Turns a hidden weekly title card face up. */
export default class extends Controller {
    static targets = ['cover', 'card'];

    show() {
        this.coverTarget.hidden = true;
        this.cardTarget.hidden = false;
    }
}
