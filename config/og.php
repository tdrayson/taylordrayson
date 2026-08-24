<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Card cache version
    |--------------------------------------------------------------------------
    |
    | Baked into every cached card's key and into the `v` on every card URL, so
    | bumping OG_VERSION both re-renders the cards and moves the address anyone
    | holding a share preview refetches by. Editing the card template does this
    | on its own; the version is for changes it cannot see, like the cutout PNG.
    | Old files are orphaned rather than overwritten, so run `php artisan
    | og:clear` afterwards to purge them.
    |
    */

    'version' => env('OG_VERSION', '1'),

];
