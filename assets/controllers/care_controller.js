import { Controller } from '@hotwired/stimulus';

/*
 * "Prendre soin de la Casa": Pour moi, Libres or Tout. Only the cards of the chosen filter stay; a
 * group of urgency tells how many it holds and disappears when none is left. The choice is kept for
 * the session, and again after the page is refreshed in place (Turbo morphing).
 */
const KEY = 'companero:care-filter';

export default class extends Controller {
    static targets = ['button', 'item', 'group', 'empty'];

    connect() {
        this.apply();
    }

    show({ params: { filter } }) {
        try {
            sessionStorage.setItem(KEY, filter);
        } catch { /* private mode: never mind */ }
        this.apply();
    }

    apply() {
        const filter = this.filter;
        let shown = 0;

        this.buttonTargets.forEach((button) => {
            button.setAttribute('aria-pressed', button.dataset.careFilterParam === filter ? 'true' : 'false');
        });
        this.itemTargets.forEach((item) => {
            const match = filter === 'all' || item.dataset[filter] === '1';
            item.hidden = !match;
            shown += match ? 1 : 0;
        });
        this.groupTargets.forEach((group) => {
            const count = this.itemTargets.filter((item) => group.contains(item) && !item.hidden).length;
            group.hidden = count === 0;
            const tally = group.querySelector('[data-care-count]');
            if (tally) {
                tally.textContent = count;
            }
        });
        if (this.hasEmptyTarget) {
            this.emptyTarget.hidden = shown > 0;
            this.emptyTarget.textContent = filter === 'mine'
                ? 'Rien pour toi : la Casa te dit merci. Un coup de main sur une tâche libre ?'
                : 'Rien ici : la Casa est contente. Profites-en !';
        }
    }

    get filter() {
        try {
            const filter = sessionStorage.getItem(KEY);
            return ['mine', 'free', 'all'].includes(filter) ? filter : 'all';
        } catch {
            return 'all';
        }
    }
}
