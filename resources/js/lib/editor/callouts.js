/**
 * The callout kinds, shared by the renderer and the editor's node view so a
 * panel looks the same while it is being written as it does once published.
 */

// Callout tint per variant (GitHub-alert set). Hues borrow the closest timeline
// data-type tokens, the palette having no success/warning/danger scale of its
// own. Chip text stays neutral-900/accent-700: the hue tokens are single values
// with no dark shade to guarantee contrast.
export const CALLOUT_VARIANTS = {
    note: { label: 'Note', panel: 'bg-neutral-25', chip: 'bg-neutral-900 text-neutral-0' },
    tip: { label: 'Tip', panel: 'bg-activity/10', chip: 'bg-activity text-neutral-0' },
    important: { label: 'Important', panel: 'bg-accent-50', chip: 'bg-accent-500 text-neutral-0' },
    warning: { label: 'Warning', panel: 'bg-fuel/10', chip: 'bg-fuel text-neutral-900' },
    caution: { label: 'Caution', panel: 'bg-media/10', chip: 'bg-media text-neutral-0' },
};
