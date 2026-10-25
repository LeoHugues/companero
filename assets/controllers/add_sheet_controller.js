import { Controller } from '@hotwired/stimulus';

/*
 * "Ajouter", in the middle of the bottom bar: a sheet rises from the bottom with what can be added, without leaving
 * the page. Its content is loaded the first time it opens (a lazy Turbo Frame). It closes with its cross, a tap
 * outside it, Escape — and as soon as one of its choices is followed or its form sent, so that it is not found open
 * again when coming back (in the app, the next screen is pushed above this one).
 */
export default class extends Controller {
    static targets = ['dialog'];

    connect() {
        this.onLeave = () => this.close();
        document.addEventListener('turbo:before-cache', this.onLeave);
        this.dialogTarget.addEventListener('turbo:click', this.onLeave);
        this.dialogTarget.addEventListener('turbo:submit-start', this.onLeave);
    }

    disconnect() {
        document.removeEventListener('turbo:before-cache', this.onLeave);
    }

    open(event) {
        if (event.metaKey || event.ctrlKey || event.shiftKey || typeof this.dialogTarget.showModal !== 'function') {
            return;
        }
        event.preventDefault();
        this.dialogTarget.showModal();
        document.documentElement.classList.add('has-sheet');
    }

    close() {
        if (this.dialogTarget.open) {
            this.dialogTarget.close();
        }
    }

    /** A tap on the dimmed page around the sheet. */
    backdrop(event) {
        if (event.target === this.dialogTarget) {
            this.close();
        }
    }

    closed() {
        document.documentElement.classList.remove('has-sheet');
    }
}
