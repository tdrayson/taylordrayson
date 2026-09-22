import { matchCase } from './words.js';

const VOWELS = /^[aeiou]/i;

/**
 * Pig Latin: the opening consonants move to the end and take "ay", so pig becomes igpay.
 * @param {string} word
 * @returns {string}
 */
export function toPigLatin(word) {
    const lower = word.toLowerCase();

    if (VOWELS.test(lower)) {
        return `${word}way`;
    }

    const onset = lower.match(/^(?:[^aeiouy]*qu|[^aeiou][^aeiouy]*)/)?.[0] ?? '';

    return matchCase(word, `${lower.slice(onset.length)}${onset}ay`);
}
