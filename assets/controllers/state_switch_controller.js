import { Controller } from '@hotwired/stimulus';
import { buzz, calm } from '../lib/fx.js';

/*
 * The card of the house's state on the home page has two faces: the Casa's cleanliness (by default)
 * and the goal of the house, turned by a small inlaid switch whose thumb follows data-face, or by a
 * sideways swipe on the card.
 *
 * The faces cross over: the one leaving slides out and fades while the one coming slides in from the
 * side the thumb goes to. The stage is as tall as the taller face (its drawer folded), so turning the card
 * does not move what lies below; when it must (the drawer of the points open), its height glides instead
 * of jolting. The face that comes in counts its gauges and numbers up again.
 * The choice holds while the page is refreshed in place (morphing would put the cleanliness back after
 * each "C'est fait"), not from one visit to the next.
 */
const DURATION = 420; // ms: the height's glide, and the crossing of the faces
const SWIPE = 48; // px sideways, at least, for a swipe

export default class extends Controller {
    static targets = ['tab', 'panel', 'stage'];

    connect() {
        this.face = this.panelTargets.find((panel) => !panel.hidden)?.dataset.face;
        this.onResize = () => this.measure();
        window.addEventListener('resize', this.onResize);
        this.measure();
        // The font changes the faces' heights once it has come.
        document.fonts?.ready.then(() => this.measure());
    }

    disconnect() {
        window.removeEventListener('resize', this.onResize);
        this.finish();
    }

    show({ params: { face } }) {
        this.turn(face);
    }

    turn(face) {
        if (!face || face === this.face) {
            return;
        }
        this.finish();
        const leaving = this.current;
        this.face = face;
        const coming = this.current;

        if (calm() || !leaving || !this.hasStageTarget) {
            this.apply();
            this.replay(coming);
            return;
        }

        const stage = this.stageTarget;
        const from = stage.offsetHeight;
        stage.style.height = `${from}px`;
        // Towards Objectif (the second face) the new one comes from the right, back to Propreté from the left.
        stage.style.setProperty('--dir', this.panelTargets.indexOf(coming) > this.panelTargets.indexOf(leaving) ? 1 : -1);
        stage.classList.add('is-switching');

        this.apply(leaving);
        leaving.classList.add('is-leaving');
        coming.classList.add('is-coming');
        this.replay(coming);

        // The new face's own height, now that it is in the flow and the old one is not (never under the stage's floor).
        const to = Math.max(coming.offsetHeight, parseFloat(stage.style.minHeight) || 0);
        void stage.offsetHeight;
        stage.style.height = `${to}px`;

        this.pending = { leaving, coming, timer: setTimeout(() => this.finish(), DURATION) };
    }

    /** The crossing is over (or cut short by another one): the stage lets go of its height. */
    finish() {
        if (!this.pending) {
            return;
        }
        const { leaving, coming, timer } = this.pending;
        this.pending = null;
        clearTimeout(timer);
        leaving.classList.remove('is-leaving');
        leaving.hidden = leaving.dataset.face !== this.face;
        coming.classList.remove('is-coming');
        if (this.hasStageTarget) {
            this.stageTarget.classList.remove('is-switching');
            this.stageTarget.style.height = '';
        }
    }

    /** After a refresh in place: back to the face that was showing. */
    restore(event) {
        if (event.target === this.element) {
            this.finish();
            this.apply();
            this.measure();
        }
    }

    /** The stage's floor: the taller of the two faces, the drawer of the points folded. */
    measure() {
        if (!this.hasStageTarget || this.pending) {
            return;
        }
        const heights = this.panelTargets.map((panel) => {
            const hidden = panel.hidden;
            if (hidden) {
                Object.assign(panel.style, { position: 'absolute', inset: '0 0 auto', visibility: 'hidden' });
                panel.hidden = false;
            }
            let height = panel.offsetHeight;
            const drawer = panel.querySelector('details[open]');
            if (drawer) {
                height -= drawer.offsetHeight - drawer.querySelector('summary').offsetHeight;
            }
            if (hidden) {
                panel.hidden = true;
                Object.assign(panel.style, { position: '', inset: '', visibility: '' });
            }
            return height;
        });
        this.stageTarget.style.minHeight = `${Math.max(...heights)}px`;
    }

    /** The switch and the faces as they should be; `leaving` stays shown while it slides out. */
    apply(leaving = null) {
        this.element.dataset.face = this.face;
        this.tabTargets.forEach((tab) => tab.setAttribute('aria-selected', tab.dataset.stateSwitchFaceParam === this.face ? 'true' : 'false'));
        this.panelTargets.forEach((panel) => { panel.hidden = panel.dataset.face !== this.face && panel !== leaving; });
    }

    /** A sideways swipe on the card turns it: to the left, the goal; to the right, the cleanliness. */
    touchStart(event) {
        const touch = event.touches[0];
        this.swipe = event.touches.length === 1 ? { x: touch.clientX, y: touch.clientY } : null;
    }

    touchEnd(event) {
        const start = this.swipe;
        this.swipe = null;
        const touch = event.changedTouches[0];
        if (!start || !touch) {
            return;
        }
        const dx = touch.clientX - start.x;
        const dy = touch.clientY - start.y;
        if (Math.abs(dx) < SWIPE || Math.abs(dx) < Math.abs(dy) * 1.5) {
            return;
        }
        const faces = this.panelTargets.map((panel) => panel.dataset.face);
        const next = faces[faces.indexOf(this.face) + (dx < 0 ? 1 : -1)];
        if (next) {
            buzz('tick');
            this.turn(next);
        }
    }

    /** What was hidden did not fill up: its gauges rise and its numbers count again, from zero. */
    replay(panel) {
        panel.querySelectorAll('[data-controller~="gauge"]').forEach((element) => {
            const gauge = this.application.getControllerForElementAndIdentifier(element, 'gauge');
            gauge?.rise(0, gauge.valueValue);
        });
        panel.querySelectorAll('[data-controller~="count-up"]').forEach((element) => {
            const count = this.application.getControllerForElementAndIdentifier(element, 'count-up');
            count?.run(0, count.toValue);
        });
    }

    get current() {
        return this.panelTargets.find((panel) => panel.dataset.face === this.face);
    }
}
