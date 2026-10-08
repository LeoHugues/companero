import './stimulus_bootstrap.js';
import './styles/app.css';
import { buzz } from './lib/fx.js';

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
