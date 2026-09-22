import { toEmoji } from './emoji.js';
import { toNumeronym } from './numeronym.js';
import { toPigLatin } from './pigLatin.js';
import { toPirate } from './pirate.js';
import { toReversed } from './reversed.js';

/** Each text mode's word transform, keyed by the setting's value. */
export const WORD_TRANSFORMS = {
    numeronym: toNumeronym,
    pirate: toPirate,
    reversed: toReversed,
    emoji: toEmoji,
    'pig-latin': toPigLatin,
};
