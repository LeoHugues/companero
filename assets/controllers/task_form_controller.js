import { Controller } from '@hotwired/stimulus';

/* Shows only the fields that match the selected kind (and category) of task, and whether it is already done. */
export default class extends Controller {
    static targets = ['section', 'category', 'done', 'doneSection', 'notDoneSection'];

    connect() {
        this.toggle();
    }

    toggle() {
        const checked = this.element.querySelector('input[name$="[kind]"]:checked');
        const kind = checked ? checked.value : 'one_off';

        this.sectionTargets.forEach((section) => {
            section.hidden = !section.dataset.kind.split(' ').includes(kind);
        });

        const category = this.element.querySelector('input[name$="[category]"]:checked');
        this.categoryTargets.forEach((section) => {
            section.hidden = !category || section.dataset.category !== category.value;
        });

        const done = this.hasDoneTarget && this.doneTarget.checked;
        this.doneSectionTargets.forEach((section) => {
            section.hidden = !done;
        });
        this.notDoneSectionTargets.forEach((section) => {
            section.hidden = done;
        });
    }
}
