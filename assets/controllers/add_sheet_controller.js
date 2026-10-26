import { Controller } from '@hotwired/stimulus';

/*
 * "Ajouter", in the middle of the bottom bar: a sheet rises from the bottom with what can be added, without leaving
 * the page. Its content (a lazy Turbo Frame) is loaded once, a little after the first page, and the sheet is kept from
 * page to page (data-turbo-permanent): it opens at once. It closes with its cross, a tap outside it, Escape — and as
 * soon as one of its choices is followed or its form sent, so that it is not found open again when coming back (in the
 * app, the next screen is pushed above this one). Once something is said missing, its field is emptied and its
 * suggestions come again, fresh, for the next time.
 */
export default class extends Controller {
    static targets = ['dialog', 'frame'];

    connect() {
        this.onLeave = () => this.close();
        document.addEventListener('turbo:before-cache', this.onLeave);
        // Loaded ahead, once the page has settled: the first tap finds it ready.
        if (this.hasFrameTarget && this.frameTarget.querySelector('.add-sheet-loading')) {
            this.preload = setTimeout(() => { this.frameTarget.loading = 'eager'; }, 1500);
        }
    }

    disconnect() {
        document.removeEventListener('turbo:before-cache', this.onLeave);
        clearTimeout(this.preload);
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

    /** What is said missing comes back to the page the sheet is open on, whichever page loaded it. */
    stamp(event) {
        const back = event.target.querySelector('[data-add-sheet-back]');
        if (back) {
            back.value = window.location.pathname + window.location.search;
        }
    }

    sent(event) {
        if (!event.detail.success || !event.target.closest('turbo-frame')) {
            return;
        }
        event.target.reset();
        this.frameTarget.reload();
    }
}
