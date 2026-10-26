import { calm } from './lib/fx.js';

/*
 * The cards of "À toi de jouer" move at the tap, not when the server answers:
 * - "Piocher", or "Je prends" on a card of "Prendre soin de la Casa" (or a quest of the Casa): the seats of that card
 *   say my name at once, and a copy of its card slides into the hand — the hand glides to where it goes and opens up for it;
 * - my name tapped ("Je ne m'en occupe plus"): "Je prends" is back at once, and the card leaves the hand — it fades out,
 *   its neighbours close the gap — unless it is counted on me whoever takes it;
 * - meanwhile the page is asked for. It comes back as a Turbo Stream, morphed in place (RefreshInPlaceListener), only once
 *   what the tap started has finished: a card is never cut halfway, the page never jolts under a moving hand. Whatever
 *   the server decided otherwise (a card taken by someone else just before) then comes or goes the same way.
 * A tap the server never got (no network) is undone. Nothing moves when the phone asks for less motion: things just change.
 */

const HAND = '.hand';
const CARD = '[data-hand-card]';
const SEAT = '[data-seat]';

/** What a tap started and has not finished yet: the page coming back waits for it. */
const moving = new Set();
/** For each form submission, how to undo what its tap changed. */
const undos = new WeakMap();

function track(motion) {
    const promise = motion.catch(() => {});
    moving.add(promise);
    promise.finally(() => moving.delete(promise));
}

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

/** Cards dealt into the hand, all at once: the hand glides to the first, then they slide in. */
async function bringIn(dealt) {
    if (calm() || dealt.length === 0) {
        return;
    }
    const widths = dealt.map(hold);
    await unsnapped(dealt[0].closest(HAND), async () => {
        await reveal(dealt[0]);
        await Promise.all(dealt.map((card, i) => arrive(card, widths[i]))).catch(() => {});
    });
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

// ——— At the tap ———

/**
 * The seats of a card turn into my name ("mine") or "Je prends" ("free"), wherever the card shows, but in `except`
 * (a card leaving the hand). Only a look, from the page's templates (templates/task/_seat.html.twig): the real buttons
 * come with the page. A seat with no look for it (a compact card's points) just stays pressed.
 */
function turnSeats(id, state, undo, except = null) {
    document.querySelectorAll(`${SEAT}[data-seat="${CSS.escape(id)}"]`).forEach((seat) => {
        const current = seat.dataset.seatState;
        if (current === state || current === 'taken' || except?.contains(seat)) {
            return;
        }
        const look = document.getElementById(seat.closest('.play-card-compact') ? `seat-${state}-compact` : `seat-${state}`);
        if (!look) {
            return;
        }
        const turned = look.content.firstElementChild.cloneNode(true);
        turned.dataset.seat = id;
        seat.replaceWith(turned);
        if (!calm()) {
            pop(turned);
        }
        undo.push(() => {
            seat.classList.remove('is-pressed');
            turned.replaceWith(seat);
        });
    });
}

/** Where a card goes in the hand: in the order of "Prendre soin de la Casa", the server's own; before the deck otherwise. */
function placeIn(hand, id) {
    const order = [...document.querySelectorAll('[data-care-target="item"] > .play-card')].map((card) => card.id);
    const rank = order.indexOf(`task-${id}`);

    return [...hand.querySelectorAll(CARD)].find((card) => order.indexOf(card.id.replace(/^hand-/, 'task-')) > rank)
        ?? hand.querySelector('#hand-deck');
}

/** Taken, or dealt from the deck: my name on its seats, and a copy of its card (from "Prendre soin de la Casa") into the hand. */
function take(form, id, undo) {
    // The deck, its top card dealt, settles back on its pile (Turbo keeps it disabled while the page is asked for).
    const deck = form.querySelector('.deck');
    if (deck) {
        setTimeout(() => deck.classList.remove('is-dealing'), 420);
        undo.push(() => deck.classList.remove('is-dealing'));
    }
    turnSeats(id, 'mine', undo);
    const hand = document.querySelector(HAND);
    // Its card in "Prendre soin de la Casa", or the one the home page keeps ready for a quest or the deck.
    const card = document.querySelector(`[data-care-target="item"] > #task-${CSS.escape(id)}`)
        ?? document.querySelector(`template[data-dealt="${CSS.escape(id)}"]`)?.content.querySelector('.play-card');
    if (!hand || !card || document.getElementById(`hand-${id}`)) {
        return;
    }
    const slot = document.createElement('li');
    slot.className = 'hand-card';
    slot.id = `hand-${id}`;
    slot.dataset.handCard = '';
    slot.append(card.cloneNode(true));
    // With the blank the server's markup has between its cards: the morphing then finds every card where it is,
    // instead of moving all those after it one by one.
    const next = placeIn(hand, id);
    const blank = document.createTextNode(' ');
    hand.insertBefore(slot, next);
    hand.insertBefore(blank, next);
    undo.push(() => { slot.remove(); blank.remove(); });
    // A card kept ready still says "Je prends": it comes with my name.
    turnSeats(id, 'mine', undo);
    track(bringIn([slot]));
}

/** Let go: "Je prends" again on its seats, and its card out of the hand — unless it is counted on me anyway. */
function letGo(id, undo) {
    const card = document.getElementById(`hand-${id}`);
    const leaving = card?.matches(CARD) && !('countedOn' in card.dataset) ? card : null;
    turnSeats(id, 'free', undo, leaving);
    if (!leaving) {
        return;
    }
    const hand = leaving.closest(HAND);
    const next = leaving.nextElementSibling;
    // Its blank goes with it, as in the server's markup (see take()).
    const blank = leaving.previousSibling?.nodeType === Node.TEXT_NODE ? leaving.previousSibling : null;
    let undone = false;
    undo.push(() => {
        undone = true;
        leaving.getAnimations().forEach((animation) => animation.cancel());
        if (!leaving.isConnected) {
            const at = next?.parentNode === hand ? next : hand.querySelector('#hand-deck');
            if (blank) {
                hand.insertBefore(blank, at);
            }
            hand.insertBefore(leaving, at);
        }
    });
    track((async () => {
        if (!calm()) {
            await unsnapped(hand, () => leave(leaving)).catch(() => {});
        }
        if (!undone) {
            blank?.remove();
            leaving.remove();
        }
    })());
}

// The very first frame: the deck lifts its top card, a seat sinks a little.
document.addEventListener('submit', (event) => {
    event.target.querySelector('.deck')?.classList.add('is-dealing');
    event.target.querySelector(SEAT)?.classList.add('is-pressed');
});

// Turbo sends the form: what it will change is played now.
document.addEventListener('turbo:submit-start', (event) => {
    const form = event.target;
    const { cardMotion: motion, task: id } = form.dataset;
    if (!motion || !id) {
        return;
    }
    const undo = [];
    undos.set(event.detail.formSubmission, undo);
    if (motion === 'let-go') {
        letGo(id, undo);
    } else {
        take(form, id, undo);
    }
});

// The server never got it (no network, or it refused): back as it was. An answer, even an error page, shows as usual.
document.addEventListener('turbo:submit-end', (event) => {
    const undo = undos.get(event.detail.formSubmission);
    if (undo && event.detail.success === false) {
        undo.reverse().forEach((step) => step());
        event.target.querySelector(SEAT)?.classList.remove('is-pressed');
    }
});

/*
 * Sent back to the same page, a form gets its new <main> as a Turbo Stream, morphed in place
 * (RefreshInPlaceListener): its rendering is wrapped, to let what the tap started finish first,
 * then the leaving cards go, and the new ones come in after.
 */
document.addEventListener('turbo:before-stream-render', (event) => {
    const stream = event.target;
    if (stream.action !== 'update') {
        return;
    }
    const render = event.detail.render;
    event.detail.render = async (element) => {
        // Never longer than the longest motion: a page in the background plays no frame, it would wait forever.
        await Promise.race([Promise.all(moving), new Promise((resolve) => { setTimeout(resolve, 1600); })]);
        const hand = document.querySelector(HAND);
        const next = stream.templateContent;
        if (calm() || !hand || !stream.targetElements.some((target) => target.contains(hand)) || !next.querySelector(HAND)) {
            await render(element);
            return;
        }

        const after = ids(next);
        const before = ids(document);
        const oldSeats = seats(document);
        const newSeats = seats(next);
        const arriving = [...after].filter((id) => !before.has(id));
        const changed = [...newSeats.keys()].filter((id) => oldSeats.has(id) && oldSeats.get(id) !== newSeats.get(id));
        const leaving = [...document.querySelectorAll(`${HAND} ${CARD}`)].filter((card) => !after.has(card.id));

        if (leaving.length > 0) {
            await unsnapped(hand, () => Promise.all(leaving.map(leave)).catch(() => {}));
        }
        await render(element);
        play(arriving, changed);
    };
});

function play(arriving, changed) {
    const dealt = arriving.map((id) => document.getElementById(id)).filter((card) => card?.matches(CARD));
    bringIn(dealt);
    // In the hand, a card dealt brings its own seat along: only the seats of the cards already there pop.
    changed.forEach((id) => {
        document.querySelectorAll(`${SEAT}[data-seat="${CSS.escape(id)}"]`).forEach((seat) => {
            if (!dealt.some((card) => card.contains(seat))) {
                pop(seat);
            }
        });
    });
}
