// Class sets and lookups shared by the link-in-bio cards.

// A raised card: white on the light canvas, one step up from it in dark.
export const SURFACE = 'border border-neutral-50 bg-neutral-0 dark:bg-neutral-25';

// Fills for activity levels 0-3, shared by the header tiles and every legend.
export const LEVELS = ['bg-neutral-50', 'bg-accent-200', 'bg-accent-400', 'bg-accent-600'];

// Icon and colour per identity profile label (config/identity.php).
export const SOCIALS = {
    GitHub: { icon: 'GithubIcon', class: 'text-neutral-900' },
    LinkedIn: { icon: 'Linkedin01Icon', class: 'text-linkedin' },
    Strava: { icon: 'StravaIcon', class: 'text-strava' },
};

// Tags an Inertia view transition so transitions.css slides it the right way.
export const slide = (direction) => (transition) => transition.types?.add(`link-${direction}`);
