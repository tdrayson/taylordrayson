<?php

use function Pest\Laravel\get;

it('sends a short link to the matching profile in any case', function (string $path, string $href) {
    get($path)->assertRedirect($href);
})->with([
    'lowercase' => ['/linkedin', 'https://www.linkedin.com/in/taylor-drayson'],
    'as written' => ['/LinkedIn', 'https://www.linkedin.com/in/taylor-drayson'],
    'shouting' => ['/STRAVA', 'https://www.strava.com/athletes/23424891'],
]);
