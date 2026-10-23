import './stimulus_bootstrap.js';
// The CSRF double-submit, on every page from the start: Symfony's forms would otherwise load it lazily,
// and once a form has used it, the home page's own forms (sent without it) were turned away until the session ended.
import './controllers/csrf_protection_controller.js';
import './styles/app.css';
import './card_motion.js';
import { buzz } from './lib/fx.js';

/*
 * A page refreshed in place (morphing) leaves alone what has not changed: comparing a card, or a drawing of the
 * Casa, with its new version is much quicker than morphing it piece by piece — the home page holds some two thousand
 * elements, half of them in drawings, and a phone froze while each one was gone through. Never around a field one
 * can type in or tick: what was typed lives outside its attributes, and the morphing is what empties it once sent.
 */
const FIELDS = 'input:not([type=hidden]), textarea, select';
document.addEventListener('turbo:before-morph-element', (event) => {
    const { currentElement, newElement } = event.detail;
    if (newElement && currentElement.firstElementChild && currentElement.isEqualNode(newElement)
        && !currentElement.matches(FIELDS) && !currentElement.querySelector(FIELDS)) {
        event.preventDefault();
    }
});

/*
 * Every tap has its little tick: buttons, chips, links, switches. An element can ask for another
 * feel with data-haptic="…" (see buzz() in lib/fx.js), or for none with data-haptic="none".
 */
document.addEventListener('click', (event) => {
    const target = event.target.closest('[data-haptic], button, a, summary, label, [role=button], input[type=checkbox], input[type=radio]');
    if (!target || target.closest('[disabled]')) {
        return;
    }
    const effect = target.dataset.haptic ?? (target.matches('input[type=checkbox], label:has(input[type=checkbox])') ? 'toggle' : 'tick');
    if (effect !== 'none') {
        buzz(effect);
    }
}, true);
