import {
    WorkoutRunIcon, Moon02Icon, Restaurant01Icon, Film01Icon, Ticket01Icon,
    Mic01Icon, PodcastIcon, AirplaneTakeOff01Icon, Location01Icon, PetrolPumpIcon,
    RocketIcon, File01Icon, Note01Icon, Building06Icon, MapPinIcon,
} from '@hugeicons-pro/core-stroke-rounded';

const ICONS = {
    activities: WorkoutRunIcon,
    sleep: Moon02Icon,
    food: Restaurant01Icon,
    media: Film01Icon,
    events: Ticket01Icon,
    appearances: Mic01Icon,
    'this-week-with': PodcastIcon,
    flights: AirplaneTakeOff01Icon,
    places: Location01Icon,
    fuel: PetrolPumpIcon,
    projects: RocketIcon,
    articles: File01Icon,
    notes: Note01Icon,
    airlines: AirplaneTakeOff01Icon,
    airports: MapPinIcon,
    'fuel-stations': Building06Icon,
};

export function cpIcon(slug) {
    return ICONS[slug] ?? Note01Icon;
}
