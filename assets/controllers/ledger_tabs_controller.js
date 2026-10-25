import { Controller } from '@hotwired/stimulus';

/*
 * An account's two tabs, Dépenses and Équilibres: a tap switches at once, without a new page (nor a new screen in the
 * app), and the address follows (?vue=equilibres), so that the page refreshed in place after "Régler" stays on it.
 */
export default class extends Controller {
    static targets = ['tab', 'panel'];

    show(event) {
        const tab = event.currentTarget;
        if (event.metaKey || event.ctrlKey || event.shiftKey) {
            return;
        }
        event.preventDefault();
        const name = tab.dataset.tab;
        this.tabTargets.forEach((other) => {
            if (other.dataset.tab === name) {
                other.setAttribute('aria-current', 'page');
            } else {
                other.removeAttribute('aria-current');
            }
        });
        this.panelTargets.forEach((panel) => {
            panel.hidden = panel.dataset.tab !== name;
        });
        window.history.replaceState(window.history.state, '', tab.href);
    }
}
