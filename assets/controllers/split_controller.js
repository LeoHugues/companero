import { Controller } from '@hotwired/stimulus';

/*
 * The expense form, alive: each one's part worked out as the amount is typed and the people ticked — equally, by
 * parts (− and +), or by amounts, with what is left to split —, the same way the server does it (App\Ledger\Allocation:
 * the cents left over go to the largest remainders, the first ones first). A dépense, a revenu or a transfert change
 * the words and the groups shown; another currency shows its exchange rate and what the amount comes to.
 */
const parse = (typed, scale = 2) => {
    let value = (typed ?? '').replace(/[\s  ]/g, '').replace(/^[^\d,.]+|[^\d]+$/g, '');
    const comma = value.lastIndexOf(',');
    const dot = value.lastIndexOf('.');
    if (comma >= 0 && dot >= 0) {
        value = value.replaceAll(comma > dot ? '.' : ',', '');
    }
    value = value.replace(',', '.');
    if (value.startsWith('.')) {
        value = `0${value}`;
    }
    return new RegExp(`^\\d+(\\.\\d{1,${scale}})?$`).test(value) ? Number(value) : null;
};
const cents = (typed) => {
    const value = parse(typed);
    return value === null ? null : Math.round(value * 100);
};

/** Splits cents by weights without losing one, like App\Ledger\Allocation::split(). */
const split = (total, weights) => {
    const sum = weights.reduce((a, b) => a + b, 0);
    const shares = weights.map((weight) => Math.floor((total * weight) / sum));
    const remainders = weights.map((weight, i) => total * weight - shares[i] * sum);
    let left = total - shares.reduce((a, b) => a + b, 0);
    const order = weights.map((_, i) => i).sort((a, b) => remainders[b] - remainders[a] || a - b);
    for (const i of order) {
        if (left-- <= 0) {
            break;
        }
        shares[i] += 1;
    }
    return shares;
};

export default class extends Controller {
    static targets = ['amount', 'currency', 'rate', 'rateFrom', 'rateInput', 'converted', 'kindText', 'title', 'section', 'list', 'row', 'check', 'weight', 'part', 'share', 'remainder', 'all'];
    static values = { ledgerCurrency: String, symbols: Object, kind: String, mode: String };

    connect() {
        this.update();
    }

    update() {
        this.kindValue = this.element.querySelector('[name$="[kind]"]:checked')?.value ?? this.kindValue;
        this.modeValue = this.element.querySelector('[name$="[splitMode]"]:checked')?.value ?? this.modeValue;
        const kind = this.kindValue;
        const currency = this.hasCurrencyTarget ? this.currencyTarget.value : this.ledgerCurrencyValue;

        this.kindTextTargets.forEach((text) => {
            text.textContent = text.dataset[kind] ?? text.textContent;
        });
        if (this.hasTitleTarget) {
            this.titleTarget.placeholder = this.titleTarget.dataset[kind] ?? this.titleTarget.placeholder;
        }
        this.sectionTargets.forEach((section) => {
            section.hidden = !section.dataset.kind.split(' ').includes(kind);
        });

        const amount = this.hasAmountTarget ? cents(this.amountTarget.value) : null;
        this.showRate(currency, amount);
        this.showSplit(currency, amount);
    }

    /** Another currency: one unit of it in the account's, and what the amount comes to. */
    showRate(currency, amount) {
        if (!this.hasRateTarget) {
            return;
        }
        const foreign = currency !== this.ledgerCurrencyValue;
        this.rateTarget.hidden = !foreign;
        if (!foreign) {
            return;
        }
        this.rateFromTarget.textContent = this.symbol(currency);
        const rate = parse(this.rateInputTarget.value, 6);
        if (amount !== null && rate) {
            this.convertedTarget.textContent = `Soit ${this.format(Math.round(amount * rate), this.ledgerCurrencyValue)} pour le compte.`;
        }
    }

    showSplit(currency, amount) {
        if (!this.hasListTarget) {
            return;
        }
        const mode = this.modeValue;
        this.listTarget.dataset.mode = mode;
        const included = this.rowTargets.map((row) => row.querySelector('[data-split-target="check"]').checked);
        this.rowTargets.forEach((row, i) => row.classList.toggle('is-out', !included[i]));
        if (this.hasAllTarget) {
            this.allTarget.textContent = included.every(Boolean) ? 'Personne' : 'Tout le monde';
        }

        const shares = this.rowTargets.map(() => null);
        let remainder = '';
        let state = '';
        const count = included.filter(Boolean).length;
        if (mode === 'amounts') {
            const parts = this.rowTargets.map((row, i) => (included[i] ? cents(row.querySelector('[data-split-target="part"]').value) ?? 0 : 0));
            if (amount !== null && count > 0) {
                const gap = amount - parts.reduce((a, b) => a + b, 0);
                remainder = gap > 0 ? `Il reste ${this.format(gap, currency)} à répartir.` : gap < 0 ? `C’est ${this.format(-gap, currency)} de trop.` : 'Le compte est bon.';
                state = gap === 0 ? 'is-even' : 'is-off';
            }
        } else {
            const weights = this.rowTargets.map((row, i) => {
                if (!included[i]) {
                    return 0;
                }
                return mode === 'shares' ? Math.max(0, parseInt(row.querySelector('[data-split-target="weight"]').value, 10) || 0) : 1;
            });
            const sum = weights.reduce((a, b) => a + b, 0);
            if (amount !== null && amount > 0 && sum > 0) {
                split(amount, weights).forEach((share, i) => {
                    shares[i] = weights[i] > 0 ? share : null;
                });
                // "≈" when the cents do not fall evenly: one of them pays a cent more.
                const each = (by) => `${amount % by ? '≈ ' : ''}${this.format(Math.round(amount / by), currency)}`;
                remainder = mode === 'shares'
                    ? `${sum} part${sum > 1 ? 's' : ''} : ${each(sum)} la part.`
                    : `${count} personne${count > 1 ? 's' : ''} : ${each(count)}${count > 1 ? ' chacune' : ''}.`;
            }
        }
        if (count === 0) {
            remainder = 'Coche au moins une personne.';
            state = 'is-off';
        }

        this.shareTargets.forEach((output, i) => {
            output.textContent = shares[i] === null ? '' : this.format(shares[i], currency);
        });
        this.remainderTarget.textContent = remainder;
        this.remainderTarget.className = `split-remainder ${state}`;
    }

    /** "Tout le monde", or "Personne" when everyone is ticked. */
    all() {
        const everyone = this.checkTargets.every((check) => check.checked);
        this.checkTargets.forEach((check) => {
            check.checked = !everyone;
        });
        this.update();
    }

    more(event) {
        this.step(event, 1);
    }

    less(event) {
        this.step(event, -1);
    }

    step(event, by) {
        const row = event.currentTarget.closest('[data-split-target="row"]');
        const input = row.querySelector('[data-split-target="weight"]');
        const weight = Math.min(99, Math.max(0, (parseInt(input.value, 10) || 0) + by));
        input.value = weight;
        // A part given is a person in it; none left, out of it.
        row.querySelector('[data-split-target="check"]').checked = weight > 0;
        this.update();
    }

    symbol(currency) {
        return this.symbolsValue[currency] ?? currency;
    }

    /** "1 234,50 €", as App\Ledger\Currency::format() writes it. */
    format(amount, currency) {
        const number = (Math.abs(amount) / 100).toLocaleString('fr-FR', { minimumFractionDigits: 2, maximumFractionDigits: 2 }).replace(/\s/g, ' ');
        return `${amount < 0 ? '−' : ''}${number} ${this.symbol(currency)}`;
    }
}
