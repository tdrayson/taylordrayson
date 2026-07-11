/**
 * Visual metadata for each timeline data type, keyed by the `type` string
 * returned from each model's card() method. The `accent` colour is supplied
 * separately by the server (card['accent']) since a few types diverge
 * (e.g. calorie → food).
 */
// `icon` is an icon-registry name string (resolved by Icon.vue), so this map
// carries no direct hugeicons imports. `href` mirrors the archive slugs in
// App\Timeline\TypeRegistry (PHP). `accent` is the --color-* token key, which
// matches the type key except where it diverges server-side (calorie → food).
export const entryTypes = {
    activity: { icon: 'WorkoutRunIcon', label: 'Activity', href: '/activities', accent: 'activity' },
    sleep: { icon: 'Moon02Icon', label: 'Sleep', href: '/sleep', accent: 'sleep' },
    calorie: { icon: 'Restaurant01Icon', label: 'Food', href: '/food', accent: 'food' },
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
};

export function entryType(type) {
    return entryTypes[type] ?? { icon: 'Note01Icon', label: type, accent: 'note' };
}
