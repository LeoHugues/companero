import { Controller } from '@hotwired/stimulus';
import { calm } from '../lib/fx.js';

/*
 * The Casa's quests on the home page: one free task at a time, under her cleanliness. "Une autre ?"
 * shows the next one, and her bubble says it — and keeps saying it once she has answered a tap.
 * Taken ("Je prends"), a quest gives way to the next one at once, without waiting for the page;
 * the last one folds away and the Casa goes back to her mood. A tap the server never got brings it back.
 */
export default class extends Controller {
    static targets = ['quest'];

    next() {
        const next = this.after(this.current);
        if (next) {
            this.show(next);
        }
    }

    taken({ target }) {
        const quest = target.closest('[data-quest-target="quest"]');
        if (!quest) {
            return;
        }
        quest.dataset.taken = '';
        const next = this.after(quest);
        if (next) {
            this.show(next);
        } else {
            this.fold(quest);
        }
    }

    settled({ target, detail }) {
        const quest = target.closest('[data-quest-target="quest"]');
        if (quest && detail.success === false) {
            delete quest.dataset.taken;
            quest.getAnimations().forEach((animation) => animation.cancel());
            this.show(quest);
        }
    }

    /** The quest after this one, round and round, leaving out those just taken; null if none is left. */
    after(quest) {
        const quests = this.questTargets.filter((other) => other === quest || !('taken' in other.dataset));
        const next = quests[(quests.indexOf(quest) + 1) % quests.length];

        return next === quest && 'taken' in quest.dataset ? null : next;
    }

    show(next) {
        this.questTargets.forEach((quest) => { quest.hidden = quest !== next; });
        next.classList.remove('is-coming');
        void next.offsetWidth;
        next.classList.add('is-coming');
        this.say(next.dataset.line);
    }

    /** The last quest taken: it folds away, the face of the card closing up over it, and the Casa goes back to her own words. */
    fold(quest) {
        this.say(this.casa?.dataset.ownSpeech);
        if (calm()) {
            quest.hidden = true;
            return;
        }
        const gap = parseFloat(getComputedStyle(quest.parentElement).rowGap) || 0;
        quest.style.overflow = 'hidden';
        const folding = quest.animate(
            [
                { height: `${quest.offsetHeight}px`, opacity: 1, marginTop: '0px' },
                { height: '0px', opacity: 0, marginTop: `${-gap}px`, paddingTop: '0px', paddingBottom: '0px' },
            ],
            { duration: 320, easing: 'cubic-bezier(.4, 0, .2, 1)', fill: 'forwards' },
        );
        folding.finished.then(() => {
            // Hidden for good — never left folded by an animation the page coming back would not know about.
            quest.hidden = 'taken' in quest.dataset;
            folding.cancel();
            quest.style.removeProperty('overflow');
        }, () => quest.style.removeProperty('overflow'));
    }

    /** Her bubble says it, and keeps saying it (the line is HTML, from the server). */
    say(line) {
        const casa = this.casa;
        const speech = casa?.querySelector('[data-casa-target="speech"]');
        if (!casa || !speech || line === undefined) {
            return;
        }
        casa.dataset.casaRestSpeechValue = line;
        speech.innerHTML = line;
        speech.classList.remove('animate-pop');
        void speech.offsetWidth;
        speech.classList.add('animate-pop');
    }

    get current() {
        return this.questTargets.find((quest) => !quest.hidden) ?? this.questTargets[0];
    }

    get casa() {
        return this.element.querySelector('[data-controller~="casa"]');
    }
}
