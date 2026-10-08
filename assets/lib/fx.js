/*
 * Little rewarding effects shared by the controllers: particles (confetti, hearts, stars,
 * puffs of dust), a buzz of the phone, numbers that count up. Nothing moves when the phone
 * asks for less motion.
 */

export const calm = () => window.matchMedia('(prefers-reduced-motion: reduce)').matches;

/** A short vibration on phones that allow it (Android, the app's WebView). */
export function buzz(pattern = 12) {
    if (!calm() && 'vibrate' in navigator) {
        try { navigator.vibrate(pattern); } catch { /* not allowed: never mind */ }
    }
}

const HEART = '<svg viewBox="0 0 24 22" width="100%" height="100%"><path d="M12 21 C5 15 1 11 1 6.5 A5.5 5.5 0 0 1 12 4 A5.5 5.5 0 0 1 23 6.5 C23 11 19 15 12 21 Z" fill="currentColor"/></svg>';
const STAR = '<svg viewBox="0 0 22 22" width="100%" height="100%"><path d="M11 0 l3 8 8 3 -8 3 -3 8 -3 -8 -8 -3 8 -3z" fill="currentColor"/></svg>';

/**
 * One particle in `parent` (positioned), at x, y (px, relative to it).
 * kind: 'puff' (dust), 'heart', 'star'.
 */
export function particle(parent, x, y, { kind = 'puff', size = 10, color = '#B9A58C', dx = 0, dy = -20 } = {}) {
    if (calm()) {
        return;
    }
    const el = document.createElement('span');
    el.className = `casa-particle casa-particle-${kind}`;
    el.setAttribute('aria-hidden', 'true');
    Object.assign(el.style, { left: `${x}px`, top: `${y}px`, width: `${size}px`, height: `${size}px`, color });
    el.style.setProperty('--dx', `${dx}px`);
    el.style.setProperty('--dy', `${dy}px`);
    if (kind === 'puff') {
        Object.assign(el.style, { borderRadius: '50%', background: color });
    } else {
        el.innerHTML = kind === 'heart' ? HEART : STAR;
    }
    el.addEventListener('animationend', () => el.remove(), { once: true });
    parent.appendChild(el);
}

/** A burst of stars and hearts around a point. */
export function burst(parent, x, y, { count = 10, colors = ['#E8692C', '#F6C453', '#F2A27A'], spread = 70, kinds = ['star', 'heart'] } = {}) {
    for (let i = 0; i < count; i++) {
        const angle = (Math.PI * 2 * i) / count + Math.random() * 0.6;
        const distance = spread * (0.6 + Math.random() * 0.6);
        particle(parent, x, y, {
            kind: kinds[i % kinds.length],
            size: 10 + Math.random() * 10,
            color: colors[i % colors.length],
            dx: Math.cos(angle) * distance,
            dy: Math.sin(angle) * distance,
        });
    }
}

const CONFETTI_COLORS = ['#E8692C', '#F6C453', '#3E9B62', '#3D7FA8', '#8E55BF', '#F2A27A'];

/** Confetti raining over the whole screen, for the big moments. */
/** parent: the page, or an open <dialog> (it sits in the top layer, above the page). */
export function confetti({ count = 70, colors = CONFETTI_COLORS, duration = 2200, parent = document.body } = {}) {
    if (calm()) {
        return;
    }
    const layer = document.createElement('div');
    layer.className = 'confetti-layer';
    layer.setAttribute('aria-hidden', 'true');
    for (let i = 0; i < count; i++) {
        const piece = document.createElement('span');
        piece.className = 'confetti-piece';
        const w = 6 + Math.random() * 6;
        Object.assign(piece.style, {
            left: `${Math.random() * 100}%`,
            width: `${w}px`,
            height: `${w * (0.4 + Math.random() * 0.8)}px`,
            background: colors[i % colors.length],
            borderRadius: Math.random() < 0.3 ? '50%' : '2px',
            animationDuration: `${duration * (0.7 + Math.random() * 0.6)}ms`,
            animationDelay: `${Math.random() * 400}ms`,
        });
        piece.style.setProperty('--drift', `${(Math.random() - 0.5) * 160}px`);
        piece.style.setProperty('--spin', `${(Math.random() < 0.5 ? -1 : 1) * (360 + Math.random() * 720)}deg`);
        layer.appendChild(piece);
    }
    parent.appendChild(layer);
    setTimeout(() => layer.remove(), duration * 1.4 + 500);
}

/** Counts a number up (or down) in an element's text, French style ("1 250"). */
export function countTo(el, from, to, { duration = 900, prefix = '', suffix = '' } = {}) {
    const format = (n) => `${prefix}${Math.round(n).toLocaleString('fr-FR')}${suffix}`;
    if (calm() || from === to) {
        el.textContent = format(to);
        return Promise.resolve();
    }
    return new Promise((resolve) => {
        const start = performance.now();
        const step = (now) => {
            const t = Math.min(1, (now - start) / duration);
            const eased = 1 - Math.pow(1 - t, 3);
            el.textContent = format(from + (to - from) * eased);
            if (t < 1) {
                requestAnimationFrame(step);
            } else {
                resolve();
            }
        };
        requestAnimationFrame(step);
    });
}

/** What was shown last time for a key (a gauge, a counter), to animate from there. */
export function remember(key, value) {
    try {
        const previous = sessionStorage.getItem(`companero:${key}`);
        sessionStorage.setItem(`companero:${key}`, String(value));
        return previous === null ? null : Number(previous);
    } catch {
        return null;
    }
}
