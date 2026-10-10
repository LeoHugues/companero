import { calm } from './lib/fx.js';

/*
 * The cards of "À toi de jouer" move instead of jumping when the page is refreshed in place (morphing):
 * - a tap answers at once: the deck lifts its top card, a seat ("Je prends", or my name) sinks a little;
 * - before the new page shows, a card leaving the hand fades out, then its neighbours close the gap;
 * - after, the hand slides to where a card dealt goes, then the card slides in from the deck's side;
 *   a seat that changed ("Je prends" back, or a name) pops in.
 * Nothing moves when the phone asks for less motion.
 */

const HAND = '.hand';
const CARD = '[data-hand-card]';
const SEAT = '[data-seat]';

const ids = (root) => new Set([...(root?.querySelectorAll(`${HAND} ${CARD}`) ?? [])].map((card) => card.id));

function seats(root) {
    const states = new Map();
    root.querySelectorAll(SEAT).forEach((seat) => {
        if (!states.has(seat.dataset.seat)) {
            states.set(seat.dataset.seat, seat.dataset.seatState);
        }
    });

    return states;
}

/** The room a card takes in the hand: its width and the gap after it. */
function room(card) {
    return card.getBoundingClientRect().width + (parseFloat(getComputedStyle(card.parentElement).columnGap) || 0);
}

function leave(card) {
    // The card itself may be turning over ("C'est fait"): only its slot fades and moves, never its own transform.
    card.animate([{ opacity: 1, transform: 'none' }, { opacity: 0, transform: 'translateY(14px) scale(.94)' }], { duration: 200, easing: 'ease-in', fill: 'forwards' });

    return card.animate([{ marginRight: '0px' }, { marginRight: `${-room(card)}px` }], { duration: 240, delay: 140, easing: 'cubic-bezier(.4, 0, .2, 1)', fill: 'forwards' }).finished;
}

/** A card dealt waits unseen, taking no room, while the hand slides to where it goes. */
function hold(card) {
    const width = room(card);
    card.style.opacity = '0';
    card.style.marginRight = `${-width}px`;

    return width;
}

function arrive(card, width) {
    card.animate([{ marginRight: `${-width}px` }, { marginRight: '0px' }], { duration: 300, easing: 'cubic-bezier(.2, .8, .2, 1)' });
    const slide = card.animate(
        [
            { opacity: 0, transform: 'translate(56px, -10px) rotate(7deg) scale(.9)' },
            { opacity: 1, transform: 'translate(-4px, 0) rotate(-1deg) scale(1.01)', offset: 0.7 },
            { opacity: 1, transform: 'none' },
        ],
        { duration: 480, delay: 60, easing: 'cubic-bezier(.25, .8, .3, 1)', fill: 'backwards' },
    );
    card.style.removeProperty('opacity');
    card.style.removeProperty('margin-right');

    return slide.finished;
}

/**
 * The hand glides to the card dealt — sideways only, the page itself does not move — in a bounded time
 * (a native smooth scroll took a second across a full hand), and says when it is there.
 */
function reveal(card) {
    const hand = card.closest(HAND);
    const padding = parseFloat(getComputedStyle(hand).scrollPaddingLeft) || 0;
    const from = hand.scrollLeft;
    const to = Math.min(hand.scrollWidth - hand.clientWidth, Math.max(0, from + card.getBoundingClientRect().left - hand.getBoundingClientRect().left - padding));
    const distance = to - from;
    if (Math.abs(distance) < 2) {
        return Promise.resolve();
    }
    const duration = Math.min(600, 260 + Math.abs(distance) * 0.12);
    const ease = (t) => (t < 0.5 ? 4 * t * t * t : 1 - (-2 * t + 2) ** 3 / 2);
    const start = performance.now();

    return new Promise((resolve) => {
        const step = (now) => {
            const t = Math.min(1, (now - start) / duration);
            hand.scrollLeft = from + distance * ease(t);
            t < 1 ? requestAnimationFrame(step) : resolve();
        };
        requestAnimationFrame(step);
    });
}

/** While cards come and go, the hand stops snapping: it would hold on to a neighbour that moves, and jolt. */
async function unsnapped(hand, motion) {
    hand.style.scrollSnapType = 'none';
    try {
        await motion();
    } finally {
        hand.style.removeProperty('scroll-snap-type');
    }
}

function pop(seat) {
    seat.animate(
        [
            { opacity: 0, transform: 'scale(.6)' },
            { opacity: 1, transform: 'scale(1.06)', offset: 0.65 },
            { opacity: 1, transform: 'scale(1)' },
        ],
        { duration: 340, easing: 'ease-out' },
    );
}

// A tap answers at once, while the page is asked for.
document.addEventListener('submit', (event) => {
    event.target.querySelector('.deck')?.classList.add('is-dealing');
    event.target.querySelector(SEAT)?.classList.add('is-pressed');
});

/*
 * Sent back to the same page, a form gets its new <main> as a Turbo Stream, morphed in place
 * (RefreshInPlaceListener): its rendering is wrapped, to let the leaving cards go first and bring the new ones in after.
 */
document.addEventListener('turbo:before-stream-render', (event) => {
    const stream = event.target;
    const hand = document.querySelector(HAND);
    if (calm() || !hand || stream.action !== 'update' || !stream.targetElements.some((target) => target.contains(hand))) {
        return;
    }
    const next = stream.templateContent;
    if (!next.querySelector(HAND)) {
        return;
    }

    const before = ids(document);
    const after = ids(next);
    const oldSeats = seats(document);
    const newSeats = seats(next);
    const arriving = [...after].filter((id) => !before.has(id));
    const changed = [...newSeats.keys()].filter((id) => oldSeats.has(id) && oldSeats.get(id) !== newSeats.get(id));
    const leaving = [...document.querySelectorAll(`${HAND} ${CARD}`)].filter((card) => !after.has(card.id));

    const render = event.detail.render;
    event.detail.render = async (element) => {
        if (leaving.length > 0) {
            await unsnapped(hand, () => Promise.all(leaving.map(leave)).catch(() => {}));
        }
        await render(element);
        play(arriving, changed);
    };
});

function play(arriving, changed) {
    const dealt = arriving.map((id) => document.getElementById(id)).filter((card) => card?.matches(CARD));
    if (dealt.length > 0) {
        const widths = dealt.map(hold);
        unsnapped(dealt[0].closest(HAND), async () => {
            await reveal(dealt[0]);
            await Promise.all(dealt.map((card, i) => arrive(card, widths[i]))).catch(() => {});
        });
    }
    // In the hand, a card dealt brings its own seat along: only the seats of the cards already there pop.
    changed.forEach((id) => {
        document.querySelectorAll(`${SEAT}[data-seat="${CSS.escape(id)}"]`).forEach((seat) => {
            if (!dealt.some((card) => card.contains(seat))) {
                pop(seat);
            }
        });
    });
}
