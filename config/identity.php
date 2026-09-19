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

    'avatar' => '/taylor-cutout.png',

    'bio' => 'I build stuff on the internet, track everything, and drink too much coffee.',

    'profiles' => [
        ['label' => 'GitHub', 'href' => 'https://github.com/tdrayson'],
    ],

];
