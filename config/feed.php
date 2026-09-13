<?php

use App\Models\TimelineEntry;

$items = [TimelineEntry::class, 'getFeedItems'];
$title = 'Taylor Drayson';
$description = 'Everything, logged — activities, places, films, flights and more.';
$language = 'en-GB';

return [
    'author_name' => 'Taylor Drayson',
    // Not config('site.email'): config files load alphabetically, so 'feed'
    // runs before 'site' exists in the repository. Default matches site.php.
    'author_email' => env('SITE_EMAIL', 'taylor@drayson.co.uk'),

    'feeds' => [
        'atom' => [
            'items' => $items,
            'url' => '/feed',
            'title' => $title,
            'description' => $description,
            'language' => $language,
            'image' => '',
            'format' => 'atom',
            'view' => 'feed::atom',
            'type' => '',
            'contentType' => '',
        ],

        'rss' => [
            'items' => $items,
            'url' => '/feed/rss',
            'title' => $title,
            'description' => $description,
            'language' => $language,
            'image' => '',
            'format' => 'rss',
            'view' => 'feed::rss',
            'type' => '',
            'contentType' => '',
        ],

        'json' => [
            'items' => $items,
            'url' => '/feed/json',
            'title' => $title,
            'description' => $description,
            'language' => $language,
            'image' => '',
            'format' => 'json',
            'view' => 'feed::json',
            'type' => '',
            'contentType' => '',
        ],
    ],
];
