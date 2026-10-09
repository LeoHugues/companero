import { Controller } from '@hotwired/stimulus';

/*
 * A search box over a list: only the items whose words match what is typed stay visible,
 * accents and case aside. A group (a room, a category) disappears when none of its items is
 * left, and opens when something is typed. The "create" link carries what was typed, as a title.
 */
const normalize = (text) => text.normalize('NFD').replace(/[̀-ͯ]/g, '').toLowerCase().trim();

export default class extends Controller {
    static targets = ['input', 'item', 'group', 'empty', 'create'];

    connect() {
        this.filter();
    }

    filter() {
        const query = normalize(this.inputTarget.value);
        const words = query.split(/\s+/).filter(Boolean);
        let shown = 0;

        this.itemTargets.forEach((item) => {
            const text = normalize(item.dataset.filterText ?? item.textContent);
            const match = words.every((word) => text.includes(word));
            item.hidden = !match;
            shown += match ? 1 : 0;
        });

        this.groupTargets.forEach((group) => {
            const items = this.itemTargets.filter((item) => group.contains(item));
            group.hidden = items.length > 0 && items.every((item) => item.hidden);
            if (words.length > 0 && 'open' in group) {
                group.open = true;
            }
        });

        if (this.hasEmptyTarget) {
            this.emptyTarget.hidden = shown > 0;
        }
        this.createTargets.forEach((link) => {
            const url = new URL(link.href, window.location.href);
            if (query) {
                url.searchParams.set('titre', this.inputTarget.value.trim());
            } else {
                url.searchParams.delete('titre');
            }
            link.href = url.toString();
        });
    }

    clear() {
        this.inputTarget.value = '';
        this.filter();
        this.inputTarget.focus();
    }
}
