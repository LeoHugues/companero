import { Controller } from '@hotwired/stimulus';

/*
 * The Casa's quests on the home page: one free task at a time, under her cleanliness. "Une autre ?"
 * shows the next one, and her bubble says it — and keeps saying it once she has answered a tap.
 */
export default class extends Controller {
    static targets = ['quest'];

    connect() {
        const casa = this.casa;
        if (casa && casa.dataset.casaReactingValue !== 'true' && this.current) {
            casa.dataset.casaRestSpeechValue = this.current.dataset.line;
        }
    }

    next() {
        const quests = this.questTargets;
        const index = quests.indexOf(this.current);
        const next = quests[(index + 1) % quests.length];
        quests.forEach((quest) => { quest.hidden = quest !== next; });
        next.classList.remove('is-coming');
        void next.offsetWidth;
        next.classList.add('is-coming');

        const casa = this.casa;
        const speech = casa?.querySelector('[data-casa-target="speech"]');
        if (casa && speech) {
            casa.dataset.casaRestSpeechValue = next.dataset.line;
            speech.textContent = next.dataset.line;
            speech.classList.remove('animate-pop');
            void speech.offsetWidth;
            speech.classList.add('animate-pop');
        }
    }

    get current() {
        return this.questTargets.find((quest) => !quest.hidden) ?? this.questTargets[0];
    }

    get casa() {
        return this.element.querySelector('[data-controller~="casa"]');
    }
}
