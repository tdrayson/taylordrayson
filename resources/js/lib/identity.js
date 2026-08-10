import { GithubIcon } from '@hugeicons-pro/core-stroke-rounded';

/**
 * The profiles that say "this is the same person elsewhere". One source, so
 * the h-card and the visible icon row cannot claim different identities.
 */
export const profiles = [
    { icon: GithubIcon, href: 'https://github.com/tdrayson', label: 'GitHub' },
];

/**
 * Profiles that actually point somewhere. A '#' placeholder is a profile not
 * claimed yet, and asserting rel="me" to it would be a claim about nothing.
 */
export const identityProfiles = profiles.filter((profile) => profile.href !== '#');
