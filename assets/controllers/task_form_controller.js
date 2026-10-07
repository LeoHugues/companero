import { Controller } from '@hotwired/stimulus';

/* Shows only the scheduling fields that match the selected kind of task. */
export default class extends Controller {
    static targets = ['section'];

    connect() {
        this.toggle();
    }

    toggle() {
        const checked = this.element.querySelector('input[name$="[kind]"]:checked');
        const kind = checked ? checked.value : 'one_off';

        this.sectionTargets.forEach((section) => {
            section.hidden = section.dataset.kind !== kind;
        });
    }
}
