import { Controller } from '@hotwired/stimulus';
import { calm } from '../lib/fx.js';

/*
 * A card the Casa names (TaskLabels::mention), like a card linked in a game's chat. As it shows, its tiny
 * card is dealt (app.css) and its letters are shuffled — suits and a few glyphs flicker on them — then
 * settle one after the other, left to right; a rare one then catches the light. Each letter keeps its own
 * place (the glyph is drawn over it, unread by screen readers), so the bubble never changes size.
 */
const GLYPHS = '♠♥♦♣◆★#%&*+=?/<>01';
const FLICKER = 55;

export default class extends Controller {
    connect() {
        const name = this.element.querySelector('.card-ref-name');
        if (calm() || !name) {
            return;
        }
        this.name = name;
        this.text = name.textContent;
        const letters = [];
        name.textContent = '';
        for (const char of this.text) {
            if (/\s/.test(char)) {
                name.append(char);
                continue;
            }
            const letter = document.createElement('span');
            letter.className = 'card-ref-letter';
            letter.textContent = char;
            letter.dataset.glyph = this.glyph();
            name.append(letter);
            letters.push(letter);
        }

        // About two thirds of a second whatever the length: the first letter after a beat, then a steady pace.
        const pace = Math.min(34, 520 / Math.max(1, letters.length));
        const start = performance.now();
        let flickered = start;
        const tick = (now) => {
            const flicker = now - flickered >= FLICKER;
            flickered = flicker ? now : flickered;
            let shuffling = 0;
            letters.forEach((letter, i) => {
                if (!('glyph' in letter.dataset)) {
                    return;
                }
                if (now - start >= 160 + i * pace) {
                    delete letter.dataset.glyph;
                    letter.classList.add('is-set');
                } else {
                    shuffling++;
                    if (flicker) {
                        letter.dataset.glyph = this.glyph();
                    }
                }
            });
            if (shuffling > 0) {
                this.frame = requestAnimationFrame(tick);
            } else {
                // The last letters' little flash first, then plain text again.
                this.frame = null;
                this.timer = setTimeout(() => this.settle(), 260);
            }
        };
        this.frame = requestAnimationFrame(tick);
    }

    disconnect() {
        cancelAnimationFrame(this.frame);
        clearTimeout(this.timer);
        this.settle();
    }

    settle() {
        if (this.name && this.name.textContent === this.text) {
            this.name.textContent = this.text;
        }
    }

    glyph() {
        return GLYPHS[Math.floor(Math.random() * GLYPHS.length)];
    }
}
