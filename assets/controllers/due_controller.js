import { Controller } from '@hotwired/stimulus';

/*
 * "Pour quand ?" in a tap: not pressing, today, tomorrow, this weekend — in the evening, at 8 p.m. — or a date of one's
 * own, which shows the field. Without scripts, the field alone.
 */
const EVENING = 20;
const pad = (n) => String(n).padStart(2, '0');
const local = (date) => `${date.getFullYear()}-${pad(date.getMonth() + 1)}-${pad(date.getDate())}T${pad(date.getHours())}:${pad(date.getMinutes())}`;

export default class extends Controller {
    static targets = ['chip', 'field', 'input'];

    connect() {
        this.show(this.inputTarget.value ? 'date' : 'none');
    }

    choose({ params: { when } }) {
        const day = new Date();
        day.setHours(EVENING, 0, 0, 0);
        if (when === 'tomorrow') {
            day.setDate(day.getDate() + 1);
        } else if (when === 'weekend') {
            // Saturday evening; on Sunday, this very evening.
            day.setDate(day.getDate() + (day.getDay() === 0 ? 0 : 6 - day.getDay()));
        }
        if (when === 'none') {
            this.inputTarget.value = '';
        } else if (when !== 'date') {
            this.inputTarget.value = local(day);
        }
        this.show(when);
        if (when === 'date') {
            this.inputTarget.focus();
        }
    }

    show(when) {
        this.chipTargets.forEach((chip) => {
            const chosen = chip.dataset.dueWhenParam === when;
            chip.setAttribute('aria-pressed', chosen ? 'true' : 'false');
            chip.classList.toggle('chip-active', chosen);
        });
        this.fieldTarget.hidden = when !== 'date';
    }
}
