<?php

return [
    // The subject that is me. Excluded from the entry picker and the search
    // options, since the whole site is already mine, but taggable in a
    // photograph like anyone else.
    'self_slug' => env('LIFE_SELF_SLUG', 'taylor-drayson'),
];
