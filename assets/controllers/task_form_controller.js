import { Controller } from '@hotwired/stimulus';

/* Shows only the fields that match the selected kind (and category) of task. */
export default class extends Controller {
    static targets = ['section', 'category'];

    connect() {
        this.toggle();
    }

    toggle() {
        const checked = this.element.querySelector('input[name$="[kind]"]:checked');
        const kind = checked ? checked.value : 'one_off';

        this.sectionTargets.forEach((section) => {
            section.hidden = section.dataset.kind !== kind;
        });

        const category = this.element.querySelector('input[name$="[category]"]:checked');
        this.categoryTargets.forEach((section) => {
            section.hidden = !category || section.dataset.category !== category.value;
        });
    }
}
