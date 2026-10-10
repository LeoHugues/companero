import { Controller } from '@hotwired/stimulus';
import { burst, buzz } from '../lib/fx.js';

/*
 * The cards of the tour (templates/onboarding/tour/_demo_card.html.twig): not real tasks, only to
 * try them out. "Je prends" puts your face on it, "C'est fait" throws stars and starts it again;
 * on the gliding card, a slider ages it half a day at a time, as a real one ages between two times it is done
 * (fresh, soon, due on the day, late after it — see Urgency and TaskLabels).
 */
const RING = 157.1;
const ALERTS = { fresh: 'ok', soon: 'warning', due: 'warning', late: 'danger' };

export default class extends Controller {
    static targets = ['card', 'medallion', 'ring', 'badge', 'pill', 'pillIcon', 'pillLabel', 'seat', 'taken', 'done', 'slider', 'days', 'message'];
    static values = { rhythm: { type: Number, default: 2 }, age: { type: Number, default: 2 } };

    connect() {
        this.show(this.hasSliderTarget ? parseFloat(this.sliderTarget.value) || 0 : this.ageValue);
    }

    take() {
        this.seatTarget.hidden = true;
        this.takenTarget.hidden = false;
        buzz('tick');
    }

    leave() {
        this.takenTarget.hidden = true;
        this.seatTarget.hidden = false;
        buzz('tick');
    }

    done() {
        const button = this.doneTarget;
        button.classList.add('is-done-pressed');
        const box = button.getBoundingClientRect();
        burst(document.body, box.left + box.width / 2 + window.scrollX, box.top + box.height / 2 + window.scrollY, { count: 10, spread: 56, kinds: ['star'], colors: ['#3E9B62', '#F6C453', '#E8692C'] });
        buzz('press');
        if (this.hasMessageTarget) {
            this.messageTarget.hidden = false;
        }
        if (this.hasSliderTarget) {
            this.sliderTarget.value = 0;
        }
        if (this.hasTakenTarget) {
            this.takenTarget.hidden = true;
            this.seatTarget.hidden = false;
        }
        this.show(0);
        setTimeout(() => button.classList.remove('is-done-pressed'), 900);
    }

    age() {
        this.show(parseFloat(this.sliderTarget.value) || 0);
        buzz('tick');
    }

    show(days) {
        const rhythm = this.rhythmValue;
        // Due once the rhythm is reached, late a day later (the margin); "soon" from 60 % of it.
        const state = days >= rhythm + 1 ? 'late' : days >= rhythm ? 'due' : days >= rhythm * 0.6 ? 'soon' : 'fresh';
        const alert = ALERTS[state];
        const left = state === 'due' ? 8 : Math.max(0, Math.min(100, 100 - (days / rhythm) * 100));

        this.cardTarget.classList.remove('task-card-ok', 'task-card-warning', 'task-card-danger');
        this.cardTarget.classList.add(`task-card-${alert}`);
        this.medallionTarget.className = `medallion medallion-${alert} medallion-${state}`;
        this.ringTarget.style.strokeDashoffset = (RING * (1 - left / 100)).toFixed(1);
        this.badgeTarget.hidden = state !== 'late';
        this.pillTarget.className = `urgency-pill urgency-pill-${alert}`;
        this.pillIconTargets.forEach((icon) => { icon.hidden = icon.dataset.alert !== alert; });
        this.pillLabelTarget.textContent = this.label(state, days, rhythm);

        if (this.hasDaysTarget) {
            this.daysTarget.textContent = this.since(days);
        }
    }

    label(state, days, rhythm) {
        switch (state) {
            case 'late': return `En retard de ${Math.floor(days - rhythm)} j`;
            case 'due': return 'Aujourd’hui';
            case 'soon': return 'Bientôt';
            default: {
                const until = rhythm - days;
                if (days <= 0.5) {
                    return 'Tout propre';
                }

                return until <= 1 ? 'Demain' : `Dans ${Math.ceil(until)} j`;
            }
        }
    }

    since(days) {
        const whole = Math.floor(days);
        const half = days - whole >= 0.5;
        if (days === 0) {
            return 'Faite aujourd’hui';
        }
        if (whole === 0) {
            return 'Faite il y a 12 h';
        }
        if (whole === 1 && !half) {
            return 'Faite hier';
        }

        return `Faite il y a ${whole} jour${whole > 1 ? 's' : ''}${half ? ' et demi' : ''}`;
    }
}
