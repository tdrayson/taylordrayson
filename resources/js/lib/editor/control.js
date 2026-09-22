/**
 * The shape every form control shares, so a text input, a select and the date
 * button are the same height beside each other.
 *
 * font-normal is not redundant: a control nested inside its own <label> would
 * otherwise inherit the label's 600, and its placeholder would sit two weights
 * heavier than the same text in an editor beside it.
 *
 * 44px, the touch-target floor: `py-3` around a 20.3px line puts a control at
 * 46.3px, which reads as a wobble in a stack of otherwise identical rows.
 */
export const CONTROL = 'w-full min-h-11 rounded-md border bg-neutral-0 px-3 py-2 text-sm font-normal transition-colors focus-visible:-outline-offset-1';

/** The idle and focus border, which only an invalid field overrides. */
export const CONTROL_BORDER = 'border-neutral-100 focus:border-accent-500';

/** The field focus ring for a wrapper whose bare input draws none of its own. */
export const FOCUS_WITHIN = 'focus-within:outline-2 focus-within:-outline-offset-1 focus-within:outline-accent-500';

/** The same, red on a field that failed validation. */
export const FOCUS_WITHIN_INVALID = 'focus-within:outline-2 focus-within:-outline-offset-1 focus-within:outline-red-500';

/** The one grey read-only look every control uses: no accent border, cursor says it can't be edited. */
export const READONLY = 'bg-neutral-50 text-neutral-700 cursor-default';

/** The read-only border: stays neutral rather than taking the accent focus colour. */
export const READONLY_BORDER = 'border-neutral-100 focus:border-neutral-100 focus-within:border-neutral-100';
