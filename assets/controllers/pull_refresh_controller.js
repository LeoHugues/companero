import { Controller } from '@hotwired/stimulus';
import { visit } from '@hotwired/turbo';
import { buzz, calm } from '../lib/fx.js';

/*
 * Pull to refresh, the Casa's way, in place of the browser's spinner (and of the Android app's, turned
 * off in src/Native/NativeConfiguration.php). Pulled down from the top of the page, a little Casa comes
 * down from the sky in her bubble while a terracotta ring fills around her; once it is full she smiles.
 * Let go there, she hops while the page refreshes in place (Turbo morphing: what is on screen stays,
 * the gauges rise from where they were), then closes her eyes with joy and goes back up.
 *
 * The element is permanent (data-turbo-permanent): the morphing leaves it, and so its state, alone.
 */
const START = 10; // px of finger before it counts as a pull, not a tap
const REACH = 150; // how far she can come down, at most (she starts 64px above the screen)
const READY = 92; // where she is far enough: let go and it refreshes
const REST = 84; // where she waits while the page refreshes

export default class extends Controller {
    static targets = ['arc'];

    connect() {
        this.onStart = this.start.bind(this);
        this.onMove = this.move.bind(this);
        this.onEnd = this.end.bind(this);
        window.addEventListener('touchstart', this.onStart, { passive: true });
        window.addEventListener('touchmove', this.onMove, { passive: true });
        window.addEventListener('touchend', this.onEnd, { passive: true });
        window.addEventListener('touchcancel', this.onEnd, { passive: true });
        this.length = this.hasArcTarget ? Number(this.arcTarget.getAttribute('pathLength')) : 0;
    }

    disconnect() {
        window.removeEventListener('touchstart', this.onStart);
        window.removeEventListener('touchmove', this.onMove);
        window.removeEventListener('touchend', this.onEnd);
        window.removeEventListener('touchcancel', this.onEnd);
    }

    start(event) {
        this.touch = null;
        if (this.busy || event.touches.length !== 1 || window.scrollY > 0 || this.blocked(event.target)) {
            return;
        }
        const { clientX, clientY } = event.touches[0];
        this.touch = { x: clientX, y: clientY, pulling: false };
    }

    move(event) {
        const touch = this.touch;
        if (!touch) {
            return;
        }
        const dx = event.touches[0].clientX - touch.x;
        const dy = event.touches[0].clientY - touch.y;
        if (!touch.pulling) {
            // Sideways (the hand of cards, the Casa's duster) or up: not a pull.
            if (Math.abs(dx) > START && Math.abs(dx) > dy) {
                this.touch = null;
                return;
            }
            if (dy < START || window.scrollY > 0) {
                if (dy < -START) {
                    this.touch = null;
                }
                return;
            }
            touch.pulling = true;
            touch.y += START;
            this.element.classList.add('is-pulling');
        }
        // The further, the harder: she slows down as she comes.
        const pull = Math.max(0, dy - START);
        this.show(REACH * (1 - Math.exp(-pull / 150)));
    }

    end() {
        const touch = this.touch;
        this.touch = null;
        if (!touch?.pulling) {
            return;
        }
        this.element.classList.remove('is-pulling');
        if (this.ready) {
            this.refresh();
        } else {
            this.hide();
        }
    }

    /** She comes down by `distance` px; the ring fills up to READY. */
    show(distance) {
        const progress = Math.min(1, distance / READY);
        this.element.style.setProperty('--pull', `${distance}px`);
        this.element.style.setProperty('--progress', progress);
        if (this.hasArcTarget) {
            this.arcTarget.style.strokeDashoffset = `${this.length * (1 - progress)}`;
        }
        const ready = progress >= 1;
        if (ready !== this.ready) {
            this.ready = ready;
            this.element.classList.toggle('is-ready', ready);
            if (ready) {
                buzz('tick');
            }
        }
    }

    hide() {
        this.ready = false;
        this.element.classList.remove('is-ready', 'is-refreshing', 'is-done');
        this.element.style.setProperty('--pull', '0px');
        this.element.style.setProperty('--progress', 0);
    }

    async refresh() {
        this.busy = true;
        this.element.classList.add('is-refreshing');
        this.element.style.setProperty('--pull', `${REST}px`);
        buzz('press');

        const shown = Date.now();
        const loaded = new Promise((resolve) => {
            document.addEventListener('turbo:load', resolve, { once: true });
            setTimeout(resolve, 12000);
        });
        visit(window.location.href, { action: 'replace' });
        await loaded;
        // Long enough to see her hop, even when the page comes back at once.
        await new Promise((resolve) => setTimeout(resolve, Math.max(0, (calm() ? 0 : 750) - (Date.now() - shown))));

        this.element.classList.remove('is-refreshing');
        this.element.classList.add('is-done');
        buzz('success');
        await new Promise((resolve) => setTimeout(resolve, calm() ? 0 : 650));
        this.hide();
        this.busy = false;
    }

    /** Where a pull is something else: a field, an open dialog, anything that asks not to; or something typed would be lost. */
    blocked(target) {
        return target.closest?.('input, textarea, select, [contenteditable], [data-pull-refresh="off"]')
            || document.querySelector('dialog[open]')
            || [...document.querySelectorAll('input, textarea, select')].some((field) => {
                if (field.type === 'checkbox' || field.type === 'radio') {
                    return field.checked !== field.defaultChecked;
                }
                if (field instanceof HTMLSelectElement) {
                    return [...field.options].some((option) => option.selected !== option.defaultSelected);
                }
                return field.value !== field.defaultValue;
            });
    }
}
