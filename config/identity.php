<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Site Identity
    |--------------------------------------------------------------------------
    |
    | The owner's public-facing identity: name, avatar, bio and the rel="me"
    | profile list rendered on the profile card, in the page head, and (later)
    | in exported h-card markup. Plain config so every reader, PHP or Vue,
    | shares one source instead of repeating these values.
    |
    | Deliberately separate from `app.cp`, which names the account that signs
    | in and edits: that is an authentication concern, this is a presentation
    | one, and they should be free to diverge.
    |
    */

    'name' => 'Taylor Drayson',

    // Two encodings of one crop, so the avatar looks the same everywhere.
    // Transparent, for surfaces that supply their own background: Avatar.vue
    // sits it on bg-accent-100, which differs between light and dark.
    'avatar' => '/avatar-taylor.png',

    // The same crop flattened onto light accent-100, for anywhere that cannot
    // apply a CSS background. An email client renders a transparent PNG on its
    // own body colour, which breaks the avatar in dark mode.
    'photo' => '/avatar-taylor.jpg',

    'bio' => 'I build stuff on the internet, track everything, and drink too much coffee.',

    // The personal link page's address, so it lives in .env and is never committed.
    'email' => env('PROFILE_PERSONAL_EMAIL'),

    // Where I am based, deliberately distinct from ambient.location, which
    // follows me around.
    'home' => 'Whyteleafe, Surrey',

    // Each also gets a short link on the main site, e.g. /linkedin.
    'profiles' => [
        ['label' => 'GitHub', 'href' => 'https://github.com/tdrayson'],
        ['label' => 'LinkedIn', 'href' => 'https://www.linkedin.com/in/taylor-drayson'],
        ['label' => 'Strava', 'href' => 'https://www.strava.com/athletes/23424891'],
    ],

];
