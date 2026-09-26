<?php

use function Pest\Laravel\get;

it('sends a short link to the matching profile', function (string $path, string $href) {
    get($path)->assertRedirect($href);
})->with([
    'linkedin' => ['/linkedin', 'https://www.linkedin.com/in/taylor-drayson'],
    'strava' => ['/strava', 'https://www.strava.com/athletes/23424891'],
]);
