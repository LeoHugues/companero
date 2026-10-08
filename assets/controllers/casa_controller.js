import { Controller } from '@hotwired/stimulus';
import { buzz, burst, calm, particle } from '../lib/fx.js';

/*
 * La Casa comes alive: she looks around, sneezes when dusty, answers a tap, giggles under the
 * feather duster when a finger slides over her, and the cats meow when touched.
 * The server renders her mood and the "reacting" state (a task just done); this controller
 * only adds what answers the touch, then calms her down.
 */
const DUSTER = `<svg viewBox="0 0 58 58" width="58" height="58" aria-hidden="true">
    <path d="M14 54 L30 28" stroke="#8C5A3C" stroke-width="5" stroke-linecap="round"/>
    <path d="M12 56 L18 47" stroke="#2E1E14" stroke-width="5" stroke-linecap="round"/>
    <g transform="translate(36 18) rotate(32)">
        <ellipse cx="0" cy="-2" rx="9" ry="17" fill="#E8692C"/>
        <ellipse cx="-9" cy="2" rx="7" ry="14" fill="#F6C453" transform="rotate(-24)"/>
        <ellipse cx="9" cy="2" rx="7" ry="14" fill="#F2A27A" transform="rotate(24)"/>
        <ellipse cx="0" cy="4" rx="7" ry="11" fill="#D9B48C"/>
        <rect x="-6" y="12" width="12" height="7" rx="2" fill="#B4521F"/>
    </g>
</svg>`;

/** The top of the scene's viewBox (templates/components/Casa.html.twig). */
const SCENE_TOP = 22;

const MEOWS = ['Miaou !', 'Mrrrou ?', 'Prrrr…', 'Miaou ♥', 'Mrrr !'];
const SLEEPY_MEOWS = ['Zzz…', 'Mmrr…', '…'];
const DUSTED = [
    'Ahhh, merci ! Ça fait du bien.',
    'Hihi, ça chatouille ! Encore !',
    'Je me sens déjà plus belle !',
];

export default class extends Controller {
    static targets = ['speech', 'body', 'gaze', 'dust', 'cat', 'stage'];
    static values = {
        reacting: Boolean,
        restSpeech: String,
        taps: Array,
        mood: String,
        asleep: Boolean,
        duration: { type: Number, default: 2600 },
    };

    connect() {
        if (!calm() && !this.asleepValue) {
            this.later(() => this.lookAround(), 1800);
            if (['dusty', 'neglected'].includes(this.moodValue)) {
                this.later(() => this.sneeze(), 7000 + Math.random() * 6000);
            }
        }
    }

    disconnect() {
        this.timers?.forEach(clearTimeout);
        this.timers?.clear();
        this.duster?.remove();
    }

    reactingValueChanged(reacting) {
        if (reacting) {
            // Her joy is felt with the points (the celebration's "success"): no second buzz.
            this.later(() => this.calmDown(), this.durationValue);
        }
    }

    calmDown() {
        this.say(this.restSpeechValue, 0);
        this.swapBodyAnimation('animate-breathe');
        // Ready for the next one: a page refreshed in place (morphing) sets it back to true.
        this.reactingValue = false;
    }

    // ——— On her own ———

    lookAround() {
        if (this.hasGazeTarget && !this.pressed) {
            const glances = [[0, 0], [-3, 0], [3, 0], [-2, -2], [2, 1], [0, 2]];
            const [x, y] = glances[Math.floor(Math.random() * glances.length)];
            this.gazeTarget.style.transform = `translate(${x}px, ${y}px)`;
        }
        this.later(() => this.lookAround(), 2200 + Math.random() * 3200);
    }

    sneeze() {
        this.animateBody('casa-sneeze', 650);
        buzz('sneeze');
        const { x, y } = this.toStage(170, 150);
        for (let i = 0; i < 6; i++) {
            particle(this.stageTarget, x + (Math.random() - 0.5) * 30, y, { size: 8 + Math.random() * 8, dx: (Math.random() - 0.5) * 60, dy: -10 - Math.random() * 25 });
        }
        this.later(() => this.sneeze(), 14000 + Math.random() * 12000);
    }

    // ——— Touch: a tap, or a finger sliding over her (the feather duster) ———

    press(event) {
        if (event.button > 0) {
            return;
        }
        this.pressed = { x: event.clientX, y: event.clientY, travelled: 0, last: [event.clientX, event.clientY] };
    }

    move(event) {
        if (!this.pressed) {
            this.follow(event);
            return;
        }
        const [lastX, lastY] = this.pressed.last;
        const step = Math.hypot(event.clientX - lastX, event.clientY - lastY);
        this.pressed.last = [event.clientX, event.clientY];
        this.pressed.travelled += step;
        if (!this.dusting && Math.hypot(event.clientX - this.pressed.x, event.clientY - this.pressed.y) > 12) {
            this.startDusting(event);
        }
        if (this.dusting) {
            this.dust(event, step);
        }
    }

    release(event) {
        if (this.petting) {
            this.petting = false;
        } else if (this.pressed && !this.dusting) {
            this.tap(event);
        }
        this.stopDusting();
        this.pressed = null;
    }

    cancel() {
        this.stopDusting();
        this.pressed = null;
    }

    tap(event) {
        const lines = this.tapsValue;
        if (lines.length) {
            let line;
            do {
                line = lines[Math.floor(Math.random() * lines.length)];
            } while (lines.length > 1 && line === this.lastTap);
            this.lastTap = line;
            this.say(line);
        }
        this.animateBody('casa-giggle', 700);
        buzz('giggle');
        const { x, y } = this.pointIn(event);
        for (let i = 0; i < 3; i++) {
            particle(this.stageTarget, x + (i - 1) * 14, y - 6, { kind: 'heart', size: 14 + i * 3, color: i % 2 ? '#F6C453' : '#E8692C', dx: (i - 1) * 18 });
        }
    }

    startDusting(event) {
        this.dusting = { since: 0, giggled: false, total: 0 };
        if (!this.duster) {
            this.duster = document.createElement('div');
            this.duster.className = 'casa-duster';
            this.duster.innerHTML = DUSTER;
            this.stageTarget.appendChild(this.duster);
        }
        this.stageTarget.setPointerCapture?.(event.pointerId);
        this.duster.classList.add('is-on');
        this.placeDuster(event);
    }

    dust(event, step) {
        this.placeDuster(event);
        const { x, y } = this.pointIn(event);
        this.dusting.since += step;
        this.dusting.total += step;
        if (this.dusting.since > 16) {
            this.dusting.since = 0;
            particle(this.stageTarget, x + 10, y - 14, { size: 6 + Math.random() * 9, dx: (Math.random() - 0.5) * 50, dy: -12 - Math.random() * 30 });
            // The feather duster's grain under the finger.
            buzz('dust');
            if (Math.random() < 0.25) {
                particle(this.stageTarget, x + 14, y - 20, { kind: 'star', size: 10, color: '#F6C453', dx: (Math.random() - 0.5) * 40, dy: -30 });
            }
        }
        // The dust near the duster goes away.
        const scene = this.toScene(event.clientX, event.clientY);
        this.dustTargets.forEach((spot) => {
            if (Math.hypot(spot.cx.baseVal.value - scene.x, spot.cy.baseVal.value - scene.y) < 34) {
                spot.classList.add('is-dusted');
            }
        });
        if (!this.dusting.giggled && this.dusting.total > 160) {
            this.dusting.giggled = true;
            this.say('Hihi, ça chatouille !');
            this.animateBody('casa-giggle', 700);
            buzz('giggle');
        }
        if (this.dusting.total > 650 && !this.dusting.done) {
            this.dusting.done = true;
            this.shine();
        }
    }

    stopDusting() {
        if (!this.dusting) {
            return;
        }
        this.duster?.classList.remove('is-on');
        this.dusting = null;
    }

    /** Dusted all over: she shines for a while — the real cleanliness comes from the tasks. */
    shine() {
        this.dustTargets.forEach((spot) => spot.classList.add('is-dusted'));
        const { x, y } = this.toStage(170, 110);
        burst(this.stageTarget, x, y, { count: 14, spread: 90 });
        this.say(DUSTED[Math.floor(Math.random() * DUSTED.length)]);
        this.animateBody('animate-cat-hop', 620);
        buzz('sparkle');
        this.later(() => this.dustTargets.forEach((spot) => spot.classList.remove('is-dusted')), 30000);
    }

    // ——— The cats ———

    pet(event) {
        const cat = event.currentTarget;
        this.petting = !this.dusting;
        if (this.dusting) {
            return;
        }
        const meow = cat.parentElement.querySelector('[data-casa-target="meow"]');
        const lines = this.asleepValue ? SLEEPY_MEOWS : MEOWS;
        if (meow) {
            meow.textContent = lines[Math.floor(Math.random() * lines.length)];
            meow.classList.remove('is-saying');
            void meow.getBBox?.();
            requestAnimationFrame(() => meow.classList.add('is-saying'));
        }
        if (!this.asleepValue) {
            cat.classList.remove('animate-cat-hop');
            requestAnimationFrame(() => cat.classList.add('animate-cat-hop'));
            const { x, y } = this.pointIn(event);
            particle(this.stageTarget, x, y - 10, { kind: 'heart', size: 14, color: '#F2A27A', dx: 6 });
        }
        buzz(this.asleepValue ? 'tick' : 'purr');
    }

    // ——— Helpers ———

    follow(event) {
        // With a mouse, she follows the pointer with her eyes.
        if (event.pointerType !== 'mouse' || !this.hasGazeTarget || calm()) {
            return;
        }
        const scene = this.toScene(event.clientX, event.clientY);
        const dx = Math.max(-3.5, Math.min(3.5, (scene.x - 170) / 30));
        const dy = Math.max(-2.5, Math.min(2.5, (scene.y - 126) / 30));
        this.gazeTarget.style.transform = `translate(${dx}px, ${dy}px)`;
    }

    say(text, revert = 3600) {
        if (!this.hasSpeechTarget) {
            return;
        }
        clearTimeout(this.speechTimer);
        this.speechTarget.textContent = text;
        this.speechTarget.classList.remove('animate-pop');
        requestAnimationFrame(() => this.speechTarget.classList.add('animate-pop'));
        if (revert) {
            this.speechTimer = this.later(() => this.say(this.restSpeechValue, 0), revert);
        }
    }

    animateBody(className, duration) {
        if (calm()) {
            return;
        }
        const resting = this.restingAnimation();
        this.bodyTarget.classList.remove(resting, className);
        void this.bodyTarget.getBBox?.();
        this.bodyTarget.classList.add(className);
        clearTimeout(this.bodyTimer);
        this.bodyTimer = this.later(() => this.swapBodyAnimation(resting), duration);
    }

    restingAnimation() {
        return this.asleepValue ? 'casa-snore' : 'animate-breathe';
    }

    swapBodyAnimation(className) {
        this.bodyTarget.classList.remove('animate-bounce-joy', 'casa-giggle', 'casa-sneeze', 'animate-cat-hop', 'animate-breathe', 'casa-snore');
        this.bodyTarget.classList.add(className === 'animate-breathe' ? this.restingAnimation() : className);
    }

    placeDuster(event) {
        const { x, y } = this.pointIn(event);
        this.duster.style.transform = `translate(${x}px, ${y}px)`;
    }

    /** A pointer event, in px within the stage. */
    pointIn(event) {
        const box = this.stageTarget.getBoundingClientRect();
        return { x: event.clientX - box.left, y: event.clientY - box.top };
    }

    /** Screen coordinates, in the scene's (340 wide, from y = 22: see the viewBox). */
    toScene(clientX, clientY) {
        const box = this.stageTarget.getBoundingClientRect();
        const scale = 340 / box.width;
        return { x: (clientX - box.left) * scale, y: (clientY - box.top) * scale + SCENE_TOP };
    }

    /** A point of the scene, in px within the stage. */
    toStage(x, y) {
        const scale = this.stageTarget.getBoundingClientRect().width / 340;
        return { x: x * scale, y: (y - SCENE_TOP) * scale };
    }

    later(callback, delay) {
        // Values may change before connect(): the timers are set up on first use.
        this.timers ??= new Set();
        const timer = setTimeout(() => {
            this.timers.delete(timer);
            callback();
        }, delay);
        this.timers.add(timer);
        return timer;
    }
}
