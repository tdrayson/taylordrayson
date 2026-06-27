<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Card cache version
    |--------------------------------------------------------------------------
    |
    | Baked into every cached card's key. Bump OG_VERSION to force every card to
    | regenerate (e.g. after a template change). Old files are orphaned rather
    | than overwritten, so run `php artisan og:clear` afterwards to purge them.
    |
    */

    'version' => env('OG_VERSION', '1'),

];
