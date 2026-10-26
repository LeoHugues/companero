import { Controller } from '@hotwired/stimulus';
import { calm } from '../lib/fx.js';

/*
 * A form told one question at a time (creating the coloc, joining it): only one step shows, "Suivant" checks what it
 * asks before going on, Enter does the same, "Retour" goes back. The Casa above says each step's line, with what was
 * just typed in it ({householdName}, {name}: the value of the field of that name). Coming back with errors, the form
 * opens at the first step that has one. Without scripts, every step shows, one after the other, and it still works.
 */
export default class extends Controller {
    static targets = ['step', 'dot', 'next', 'back'];

    connect() {
        this.nextTargets.forEach((button) => { button.hidden = false; });
        const failing = this.stepTargets.findIndex((step) => step.querySelector('[data-form-error]'));
        this.show(Math.max(0, failing), false);
    }

    next(event) {
        event?.preventDefault();
        const fields = [...this.current.querySelectorAll('input, select, textarea')];
        const invalid = fields.find((field) => !field.checkValidity());
        if (invalid) {
            invalid.reportValidity();
            return;
        }
        this.show(this.index + 1);
    }

    back(event) {
        event.preventDefault();
        this.show(this.index - 1);
    }

    /** Enter in a field of a step that is not the last one: on to the next step, not sending the form. */
    enter(event) {
        if (event.key !== 'Enter' || event.target.tagName === 'TEXTAREA' || this.index === this.stepTargets.length - 1) {
            return;
        }
        this.next(event);
    }

    show(index, focus = true) {
        const previous = this.index;
        this.index = Math.min(Math.max(0, index), this.stepTargets.length - 1);
        this.stepTargets.forEach((step, i) => {
            step.hidden = i !== this.index;
            step.classList.toggle('is-back', previous !== undefined && this.index < previous);
        });
        this.dotTargets.forEach((dot, i) => dot.classList.toggle('is-done', i <= this.index));
        this.backTargets.forEach((button) => { button.hidden = this.index === 0; });
        this.prefill();
        this.speak(this.current.dataset.speech);
        if (focus) {
            // (Not when the page opens: the keyboard would cover the Casa saying hello.)
            this.current.querySelector('input:not([type=hidden]), select, textarea')?.focus({ preventScroll: true });
            this.element.scrollIntoView({ block: 'nearest', behavior: calm() ? 'auto' : 'smooth' });
        }
    }

    get current() {
        return this.stepTargets[this.index];
    }

    /** The username, when it comes to it, offered from the first name: "Léa Martin" gives "lea.martin". */
    prefill() {
        const username = this.current.querySelector('[name$="[username]"]');
        const name = this.element.querySelector('[name$="[name]"]');
        if (!username || !name || username.value !== '' || username.dataset.touched) {
            return;
        }
        username.value = name.value.normalize('NFD').replace(/[̀-ͯ]/g, '').toLowerCase().trim()
            .replace(/[^a-z0-9._-]+/g, '.').replace(/^\.+|\.+$/g, '').slice(0, 30);
        username.addEventListener('input', () => { username.dataset.touched = '1'; }, { once: true });
    }

    /** The Casa says the step's line, with what was typed. */
    speak(line) {
        const casa = document.querySelector('[data-controller~="casa"]');
        const bubble = casa?.querySelector('[data-casa-target="speech"]');
        if (!line || !bubble) {
            return;
        }
        const text = line.replace(/\{(\w+)\}/g, (_, field) => this.element.querySelector(`[name$="[${field}]"]`)?.value.trim() || '');
        bubble.textContent = text;
        // At rest too, so that a tap on her ends on this line again.
        casa.dataset.casaRestSpeechValue = text.replace(/[&<>"]/g, (c) => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;' })[c]);
        bubble.classList.remove('animate-pop');
        requestAnimationFrame(() => bubble.classList.add('animate-pop'));
    }
}
