import { Controller } from '@hotwired/stimulus';

/* Ready-made words for a field: a tap on a suggestion writes it in, ready to be completed. */
export default class extends Controller {
    static targets = ['field'];

    use({ params: { text } }) {
        this.fieldTarget.value = text;
        this.fieldTarget.focus();
        this.fieldTarget.setSelectionRange(text.length, text.length);
    }
}
