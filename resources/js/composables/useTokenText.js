import { humanDuration, money, number, pencePerLitre } from '../lib/format';
import { useFormat } from './useFormat';

/** Each measured token in real units, matching the server's SubtitleText. */
const REAL = {
    dur: (token) => (token.u === 'minutes' ? `${number(Math.floor(token.s / 60))} minutes` : humanDuration(token.s)),
    kcal: (token) => `${number(token.kcal)} ${token.u ?? 'kcal'}`,
    gbp: (token) => money(token.gbp),
    ppl: (token) => `${pencePerLitre(token.ppl)}/L`,
    vol: (token) => `${number(token.l, 2)}L`,
};

/**
 * Join tokens into one line: the first takes no separator, the rest their own
 * (', ' by default), and empty ones drop out.
 * @param {Array<object>} tokens
 * @param {(token: object) => string|null} format
 * @returns {string}
 */
function join(tokens, format) {
    return tokens
        .map((token) => ({ text: format(token), sep: token.sep ?? ', ' }))
        .filter((part) => part.text)
        .map((part, index) => (index === 0 ? '' : part.sep) + part.text)
        .join('');
}

/**
 * Card titles and subtitles sent as tokens, composed in the visitor's units.
 * @returns {{tokenText: (tokens: Array<object>) => string, tokenTitle: (tokens: Array<object>|null) => string|null}}
 */
export function useTokenText() {
    const { distance, weight, exactDistance, exactWeight, measure, sillyUnits } = useFormat();

    const display = {
        dist: (token) => distance(token.m, token.p),
        wt: (token) => weight(token.kg, token.p),
        dur: (token) => measure('duration', token.s, REAL.dur(token)),
        kcal: (token) => measure('energy', token.kcal, REAL.kcal(token)),
        gbp: (token) => measure('money', token.gbp, REAL.gbp(token)),
        vol: (token) => measure('volume', token.l, REAL.vol(token)),
        ppl: (token) => (sillyUnits.value === 'on' ? `${measure('money', token.ppl, '')} a litre` : REAL.ppl(token)),
    };

    const exact = {
        ...REAL,
        dist: (token) => exactDistance(token.m, token.p),
        wt: (token) => exactWeight(token.kg, token.p),
    };

    /** The tokens as the visitor sees them. */
    function tokenText(tokens) {
        return join(tokens, (token) => (display[token.t] ? display[token.t](token) : token.v));
    }

    /** The tokens in real units for a title, only while silly units hide them. */
    function tokenTitle(tokens) {
        if (sillyUnits.value !== 'on' || !tokens?.some((token) => token.t !== 'text')) {
            return null;
        }

        return join(tokens, (token) => (exact[token.t] ? exact[token.t](token) : token.v));
    }

    return { tokenText, tokenTitle };
}
