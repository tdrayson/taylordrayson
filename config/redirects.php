<?php

/*
|--------------------------------------------------------------------------
| Legacy URL redirects
|--------------------------------------------------------------------------
|
| Old site path => current path, registered as exact-match 301s in
| routes/web.php. Exact match matters: `/vehicles` is a dead index but
| `/vehicles/{value}` is a live taxonomy route, so a prefix redirect would
| shadow it. List every old URL explicitly.
|
*/

return [
    'checkins' => 'places',
    'vehicles' => 'fuel',
    'checkin-map' => 'places',
];
