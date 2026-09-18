/**
 * The shape every form control shares, so a text input, a select and the date
 * button are the same height beside each other.
 *
 * `min-h-11` holds the 44px touch-target floor, which `py-2` around a 20px
 * line falls short of on its own.
 */
export const CONTROL = 'w-full min-h-11 rounded-md border bg-neutral-0 px-3 py-2 text-sm transition-colors';

/** The idle and focus border, which only an invalid field overrides. */
export const CONTROL_BORDER = 'border-neutral-100 focus:border-accent-500 focus:outline-none';

/** The one grey read-only look every control uses: no accent border, cursor says it can't be edited. */
export const READONLY = 'bg-neutral-50 text-neutral-700 cursor-default';

/** The read-only border: stays neutral rather than taking the accent focus colour. */
export const READONLY_BORDER = 'border-neutral-100 focus:border-neutral-100 focus-within:border-neutral-100';
