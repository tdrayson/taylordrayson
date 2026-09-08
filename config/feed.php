<?php

use App\Models\TimelineEntry;

$items = [TimelineEntry::class, 'getFeedItems'];
$title = 'Taylor Drayson';
$description = 'Everything, logged — activities, places, films, flights and more.';
$language = 'en-GB';

return [
    'author_name' => 'Taylor Drayson',
    'author_email' => 'hello@taylordrayson.com',

    // Sits beside the name because it answers the same question: who wrote this.
    // Read by the conversation, where one of my own entries linking to another
    // appears alongside other people's responses and needs a face like theirs.
    'author_photo' => '/headshot-taylor.jpg',

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
