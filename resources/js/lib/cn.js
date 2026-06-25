import { clsx } from 'clsx';
import { extendTailwindMerge } from 'tailwind-merge';

/**
 * tailwind-merge doesn't know our custom font-size tokens (text-meta, text-label,
 * ...), so without this it mistakes them for text-*colour* utilities and strips a
 * real colour like `text-white`. Register them as the font-size group.
 */
const twMerge = extendTailwindMerge({
    extend: {
        classGroups: {
            'font-size': [
                {
                    text: [
                        'eyebrow', 'label', 'caption', 'meta', 'nav', 'body',
                        'section', 'item-title', 'name', 'stat', 'stat-lg', 'display', 'display-xl',
                    ],
                },
            ],
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
