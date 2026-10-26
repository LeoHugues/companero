import { Controller } from '@hotwired/stimulus';

/* How many boxes are ticked, said as they are ticked (the chores a coloc starts with). */
export default class extends Controller {
    static targets = ['count'];

    count() {
        this.countTarget.textContent = this.element.querySelectorAll('input[type=checkbox]:checked').length;
    }
}
