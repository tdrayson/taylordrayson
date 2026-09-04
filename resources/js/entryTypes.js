/**
 * Visual metadata for each timeline data type, keyed by the card's `type` string.
 * `icon` is an icon-registry name, `href` mirrors the archive slugs in
 * App\Timeline\TypeRegistry, and `accent` is the --color-* token key, which
 * matches the type key except where it diverges server-side (calorie -> food).
 */
export const entryTypes = {
    activity: { icon: 'WorkoutRunIcon', label: 'Activity', href: '/activities', accent: 'activity' },
    sleep: { icon: 'Moon02Icon', label: 'Sleep', href: '/sleep', accent: 'sleep' },
    calorie: { icon: 'UtensilsIcon', label: 'Food', href: '/food', accent: 'food' },
    media: { icon: 'Film01Icon', label: 'Media', href: '/media', accent: 'media' },
    event: { icon: 'Ticket01Icon', label: 'Event', href: '/events', accent: 'event' },
    appearance: { icon: 'Mic01Icon', label: 'Appearance', href: '/appearances', accent: 'appearance' },
    podcast: { icon: 'PodcastIcon', label: 'This Week With', href: '/this-week-with', accent: 'podcast' },
    flight: { icon: 'AirplaneTakeOff01Icon', label: 'Flight', href: '/flights', accent: 'flight' },
    checkin: { icon: 'Location01Icon', label: 'Place', href: '/places', accent: 'checkin' },
    fuel: { icon: 'PetrolPumpIcon', label: 'Fuel', href: '/fuel', accent: 'fuel' },
    project: { icon: 'RocketIcon', label: 'Project', href: '/projects', accent: 'project' },
    article: { icon: 'File01Icon', label: 'Article', href: '/articles', accent: 'article' },
    note: { icon: 'Note01Icon', label: 'Note', href: '/notes', accent: 'note' },
    // Not timeline types: the other things an internal link can point at, so a
    // link preview has a glyph for each. See App\\Links\\Resolvers.
    story: { icon: 'BookOpen01Icon', label: 'Story', href: '/stories', accent: 'article' },
    page: { icon: 'File02Icon', label: 'Page', accent: 'article' },
    period: { icon: 'Calendar03Icon', label: 'Archive', href: '/', accent: 'article' },
    tag: { icon: 'Tag01Icon', label: 'Tag', href: '/tags', accent: 'article' },
    live: { icon: 'Clock01Icon', label: 'Now', href: '/now', accent: 'activity' },
};

export function entryType(type) {
    return entryTypes[type] ?? { icon: 'Note01Icon', label: type, accent: 'note' };
}
