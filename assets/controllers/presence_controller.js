import { Controller } from '@hotwired/stimulus';

/* Days of presence: says what the weekly goal becomes as the slider moves. */
export default class extends Controller {
    static targets = ['slider', 'days', 'help'];

    connect() {
        this.describe();
    }

    describe() {
        const days = parseInt(this.sliderTarget.value, 10) || 0;
        const goalInput = this.element.querySelector('input[name$="[weeklyGoal]"]:checked');
        const goal = goalInput ? parseInt(goalInput.value, 10) : 0;

        this.daysTarget.textContent = days === 7 ? 'toute la semaine' : `${days} j / 7`;
        this.helpTarget.textContent = days === 0
            ? 'Absent·e toute la semaine : pas d’objectif, ta série est mise en pause.'
            : `Objectif de la semaine : ${Math.round(goal * days / 7)} pts. Il reste ainsi les semaines suivantes.`;
    }
}
