import { computed } from 'vue';
import { usePage } from '@inertiajs/vue3';
import { GithubIcon } from '@hugeicons-pro/core-stroke-rounded';

// Icon components keyed by profile label. Icons are a client-only concern;
// config/identity.php (the shared source) carries only href/label.
const ICONS = {
    GitHub: GithubIcon,
};

/**
 * The profiles that say "this is the same person elsewhere", read from the
 * server-shared identity config. One source, so the h-card and the visible
 * icon row cannot claim different identities.
 */
export const profiles = computed(() =>
    usePage().props.identity.profiles.map((profile) => ({ ...profile, icon: ICONS[profile.label] })),
);

/**
 * Profiles that actually point somewhere. A '#' placeholder is a profile not
 * claimed yet, and asserting rel="me" to it would be a claim about nothing.
 */
export const identityProfiles = computed(() => profiles.value.filter((profile) => profile.href !== '#'));
