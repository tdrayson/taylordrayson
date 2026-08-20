/**
 * The callout kinds, shared by the renderer and the editor's node view so a
 * panel looks the same while it is being written as it does once published.
 */

// Callout tint per variant (GitHub-alert set). Hues borrow the closest timeline
// data-type tokens, the palette having no success/warning/danger scale of its
// own. Chip text stays neutral-900/accent-700: the hue tokens are single values
// with no dark shade to guarantee contrast.
// `code` is the panel's own hue a shade darker, used for inline code inside the
// panel: the default grey chip reads as a patch of something else laid on top.
// A CSS value rather than a class, since it is applied to descendants through a
// custom property.
export const CALLOUT_VARIANTS = {
    note: { label: 'Note', panel: 'bg-neutral-25', chip: 'bg-neutral-900 text-neutral-0', code: 'var(--color-neutral-100)' },
    tip: { label: 'Tip', panel: 'bg-activity/10', chip: 'bg-activity text-neutral-0', code: 'color-mix(in oklab, var(--color-activity) 22%, transparent)' },
    important: { label: 'Important', panel: 'bg-accent-50', chip: 'bg-accent-500 text-neutral-0', code: 'color-mix(in oklab, var(--color-accent-500) 18%, transparent)' },
    warning: { label: 'Warning', panel: 'bg-fuel/10', chip: 'bg-fuel text-neutral-900', code: 'color-mix(in oklab, var(--color-fuel) 24%, transparent)' },
    caution: { label: 'Caution', panel: 'bg-media/10', chip: 'bg-media text-neutral-0', code: 'color-mix(in oklab, var(--color-media) 22%, transparent)' },
};
