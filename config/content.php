<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Content path
    |--------------------------------------------------------------------------
    |
    | Root directory for flat-file entries (date-tree markdown/json). This is
    | the durable source of truth; the database is a rebuildable index.
    |
    */

    'path' => env('CONTENT_PATH', base_path('content')),

];
