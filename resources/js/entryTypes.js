import {
    WorkoutRunIcon,
    Moon02Icon,
    Restaurant01Icon,
    Film01Icon,
    Ticket01Icon,
    Mic01Icon,
    PodcastIcon,
    AirplaneTakeOff01Icon,
    Location01Icon,
    PetrolPumpIcon,
    RocketIcon,
    File01Icon,
    Note01Icon,
} from '@hugeicons-pro/core-stroke-rounded';

/**
 * Visual metadata for each timeline data type, keyed by the `type` string
 * returned from each model's card() method. The `accent` colour is supplied
 * separately by the server (card['accent']) since a few types diverge
 * (e.g. calorie → food).
 */
// `href` mirrors the archive slugs registered in App\Timeline\TypeRegistry (PHP).
export const entryTypes = {
    activity: { icon: WorkoutRunIcon, label: 'Activity', href: '/activities' },
    sleep: { icon: Moon02Icon, label: 'Sleep', href: '/sleep' },
    calorie: { icon: Restaurant01Icon, label: 'Food', href: '/food' },
    media: { icon: Film01Icon, label: 'Media', href: '/media' },
    event: { icon: Ticket01Icon, label: 'Event', href: '/events' },
    appearance: { icon: Mic01Icon, label: 'Appearance', href: '/appearances' },
    podcast: { icon: PodcastIcon, label: 'This Week With', href: '/this-week-with' },
    flight: { icon: AirplaneTakeOff01Icon, label: 'Flight', href: '/flights' },
    checkin: { icon: Location01Icon, label: 'Place', href: '/places' },
    fuel: { icon: PetrolPumpIcon, label: 'Fuel', href: '/fuel' },
    project: { icon: RocketIcon, label: 'Project', href: '/projects' },
    article: { icon: File01Icon, label: 'Article', href: '/articles' },
    note: { icon: Note01Icon, label: 'Note', href: '/notes' },
};

export function entryType(type) {
    return entryTypes[type] ?? { icon: Note01Icon, label: type };
}
