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
export const CONTROL = 'w-full min-h-11 rounded-md border bg-neutral-0 px-3 py-2 text-meta font-normal transition-colors';

/** The idle and focus border, which only an invalid field overrides. */
export const CONTROL_BORDER = 'border-neutral-100 focus:border-accent-500 focus:outline-none';
