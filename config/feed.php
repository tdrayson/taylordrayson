<?php

use App\Models\TimelineEntry;

$items = [TimelineEntry::class, 'getFeedItems'];
$title = 'Taylor Drayson';
$description = 'Everything, logged — activities, places, films, flights and more.';
$language = 'en-GB';

return [
    'author_name' => 'Taylor Drayson',
    // Loaded directly rather than via config('site.email'): config files load
    // alphabetically, so 'feed' runs before 'site' exists in the repository.
    'author_email' => (require config_path('site.php'))['email'],

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
