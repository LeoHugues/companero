import { Controller } from '@hotwired/stimulus';

/* − / + buttons around the points field, with the matching effort in minutes. */
export default class extends Controller {
    static targets = ['input', 'hint'];
    static values = { minutesPerPoint: { type: Number, default: 5 } };

    connect() {
        this.describe();
    }

    increment() {
        this.inputTarget.stepUp();
        this.describe();
    }

    decrement() {
        this.inputTarget.stepDown();
        this.describe();
    }

    describe() {
        const minutes = (parseInt(this.inputTarget.value, 10) || 0) * this.minutesPerPointValue;
        const duration = minutes >= 60 ? `${Math.floor(minutes / 60)} h${minutes % 60 ? ` ${minutes % 60}` : ''}` : `${minutes} min`;
        this.hintTarget.textContent = `≈ ${duration} d’effort`;
    }
}
