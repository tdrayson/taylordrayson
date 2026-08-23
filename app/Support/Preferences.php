<?php

namespace App\Support;

use Illuminate\Http\Request;

/**
 * Display preferences the visitor sets in their browser: colour scheme and the
 * units the site renders in.
 *
 * These live in cookies rather than local storage so the server can read them
 * and render the right thing first time. Local storage is invisible to the
 * server, which under SSR means every page would be built with the defaults and
 * then corrected once Vue hydrates, flashing the wrong units at the reader and
 * handing crawlers the wrong values.
 *
 * Written by the client, never by us, so they are exempt from cookie encryption
 * (see bootstrap/app.php) and every value read here is untrusted.
 */
final class Preferences
{
    /** Cookie holding the visitor's chosen scheme. */
    public const THEME = 'theme';

    /**
     * Cookie holding the scheme actually in effect, which the client writes
     * whenever it changes. `system` resolves against the OS, which the server
     * cannot see, so without this a system-set visitor gets the wrong class
     * until hydration.
     */
    public const SCHEME = 'scheme';

    /** Prefix for the enumerated settings, matching the client's registry. */
    public const SETTING_PREFIX = 'pref_';

    /**
     * The settings the client defines, by key. Listed here so each one can be
     * exempted from cookie encryption and so an unknown cookie name is ignored
     * rather than passed through. Adding a `defineSetting` call means adding
     * its key here.
     *
     * @var list<string>
     */
    public const SETTINGS = ['distanceUnit', 'weightUnit'];

    /**
     * Every cookie the browser writes, which must not be encrypted.
     *
     * @return list<string>
     */
    public static function cookieNames(): array
    {
        return [
            self::THEME,
            self::SCHEME,
            ...array_map(fn (string $key): string => self::SETTING_PREFIX.$key, self::SETTINGS),
        ];
    }

    /** @var list<string> */
    private const THEMES = ['light', 'dark', 'system'];

    /**
     * Every preference for this request, as the client's stores expect them.
     *
     * @return array{theme: string, scheme: string, settings: array<string, string>}
     */
    public static function for(Request $request): array
    {
        $theme = (string) $request->cookie(self::THEME, 'system');
        $scheme = (string) $request->cookie(self::SCHEME, 'light');

        return [
            'theme' => in_array($theme, self::THEMES, true) ? $theme : 'system',
            'scheme' => $scheme === 'dark' ? 'dark' : 'light',
            'settings' => self::settings($request),
        ];
    }

    /**
     * The `pref_*` cookies, keyed without the prefix. Values are whitelisted
     * client-side against each setting's own allowed list; anything unknown
     * simply never matches and falls back there.
     *
     * @return array<string, string>
     */
    private static function settings(Request $request): array
    {
        $settings = [];

        foreach (self::SETTINGS as $key) {
            $value = $request->cookie(self::SETTING_PREFIX.$key);

            if (is_string($value) && $value !== '') {
                $settings[$key] = $value;
            }
        }

        return $settings;
    }

    /**
     * Whether the page should render dark. `system` cannot be resolved
     * server-side, so it defers to the scheme the client last reported.
     */
    public static function rendersDark(Request $request): bool
    {
        $preferences = self::for($request);

        return $preferences['theme'] === 'dark'
            || ($preferences['theme'] === 'system' && $preferences['scheme'] === 'dark');
    }
}
