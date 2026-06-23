import {
    Home01Icon,
    Clock01Icon,
    Calendar03Icon,
    ChartColumnIcon,
    MapsLocation01Icon,
    UserIcon,
    RocketIcon,
    Search01Icon,
} from '@hugeicons-pro/core-stroke-rounded';
import { entryTypes } from './entryTypes.js';

/**
 * Primary page destinations for the command palette and navigation.
 */
export const pageCommands = [
    { label: 'Timeline', href: '/', icon: Home01Icon, keywords: 'home feed entries' },
    { label: 'Now', href: '/now', icon: Clock01Icon, keywords: 'current status live' },
    { label: 'Calendar', href: '/calendar', icon: Calendar03Icon, keywords: 'month year days' },
    { label: 'Stats', href: '/stats', icon: ChartColumnIcon, keywords: 'statistics charts numbers' },
    { label: 'Map', href: '/map', icon: MapsLocation01Icon, keywords: 'places location flights' },
    { label: 'About', href: '/about', icon: UserIcon, keywords: 'bio profile me' },
    { label: "What I'm working on", href: '/working-on', icon: RocketIcon, keywords: 'projects building next' },
    { label: 'Advanced search', href: '/search', icon: Search01Icon, keywords: 'query builder filter advanced' },
];

/**
 * One destination per timeline data type (activities, sleep, food, flights, …).
 */
export const archiveCommands = Object.values(entryTypes)
    .filter((type) => type.href)
    .map((type) => ({ label: type.label, href: type.href, icon: type.icon }));
