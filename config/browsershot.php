<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Binary paths
    |--------------------------------------------------------------------------
    |
    | Browsershot shells out to Node (with Puppeteer) and Chromium. When the web
    | server's PATH does not include them (common with php-fpm / Herd), set the
    | absolute paths here. Leave null to let Browsershot resolve them itself.
    |
    */

    'node_binary' => env('BROWSERSHOT_NODE_BINARY'),

    'npm_binary' => env('BROWSERSHOT_NPM_BINARY'),

    'chrome_path' => env('BROWSERSHOT_CHROME_PATH'),
];
