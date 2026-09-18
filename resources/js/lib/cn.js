import { clsx } from 'clsx';
import { extendTailwindMerge } from 'tailwind-merge';

/**
 * tailwind-merge reads `text-unit` as a text-*colour* utility and strips a real
 * colour alongside it, so register our one remaining bespoke font size. The
 * t-shirt sizes (`text-2xs`, `text-3xs`) it already understands.
 */
const twMerge = extendTailwindMerge({
    extend: {
        classGroups: {
            'font-size': [{ text: ['unit'] }],
        },
    },
});

/**
 * Compose class lists and resolve conflicting Tailwind utilities so a consumer's
 * `class` cleanly overrides a component's defaults (the shadcn `cn` helper).
 *
 * @param {...any} inputs - class values (strings, arrays, conditionals)
 * @returns {string}
 */
export function cn(...inputs) {
    return twMerge(clsx(inputs));
}
