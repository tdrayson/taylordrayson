<?php

/*
|--------------------------------------------------------------------------
| Open Graph card phrases
|--------------------------------------------------------------------------
|
| A bank of interchangeable headlines, one pool per key. The OgPhrases helper
| picks one deterministically (seeded by the entry id, period, or type) so a
| given card always reads the same, while different cards vary. :placeholders
| are filled in per card.
|
| Keep it personal and a little tongue-in-cheek, especially the stat entries.
|
*/

return [

    // Stat entries, first person. :duration, :kcal, :cost filled per entry.
    'sleep' => [
        'I slept :duration',
        'I got :duration of sleep',
        'Out cold for :duration',
        ':duration of glorious sleep',
        'I managed :duration of shut-eye',
        'Dead to the world for :duration',
    ],

    'food' => [
        'I ate :kcal kcal',
        'I demolished :kcal kcal',
        ':kcal kcal, no regrets',
        'A casual :kcal kcal today',
        'I put away :kcal kcal',
        ':kcal kcal, purely for science',
    ],

    'fuel' => [
        'I put £:cost of fuel in',
        '£:cost of dinosaur juice',
        'I fed the car £:cost',
        'Another £:cost up in smoke',
        '£:cost lighter at the pump',
    ],

    'podcast' => [
        'Season :season, Episode :episode',
    ],

    // Per-type archive index headlines.
    'archive' => [
        'activity' => ["Every move I've made", 'All my questionable cardio', 'Proof that I exercise'],
        'sleep' => ["Every night's sleep", "How I've been sleeping", 'All my nights, logged'],
        'calorie' => ["Everything I've eaten", 'Every calorie, counted', 'A running tally of my snacking'],
        'media' => ["Everything I've watched and read", 'My watch and read history', 'Where my evenings went'],
        'event' => ["Events I've turned up to", "Everywhere I've shown my face", 'Times I left the house'],
        'appearance' => ['Talks and appearances', 'Times they let me on stage', "Where I've been let loose"],
        'podcast' => ['Every episode so far', 'The whole back catalogue', 'Every week, archived'],
        'flight' => ["Everywhere I've flown", 'My carbon footprint, mapped', "Every flight I've taken"],
        'checkin' => ["Everywhere I've been", "Places I've shown up", 'My questionable travel choices'],
        'fuel' => ['Every fill-up', "Money I've burned on fuel", 'Every trip to the pump'],
        'project' => ["Things I've built", "Stuff I've made", 'My pile of side projects'],
        'article' => ["Things I've written", 'My collected ramblings', "Words I've put online"],
        'note' => ['Passing thoughts', 'Half-formed thoughts', 'Things I jotted down'],
    ],

    // Date-page titles. The date leads (and stays the headline) with a wry tail.
    // :date is the period, e.g. "2026", "June 2026", "26 June 2026".
    'year' => [
        ':date, in full',
        ":date, the director's cut",
        'All of :date, allegedly',
        ':date, for the record',
        ':date, apparently',
    ],
    'month' => [
        ':date, in full',
        'All of :date',
        ':date, the highlights',
        ':date, for the record',
        ':date, apparently',
    ],
    'day' => [
        ':date, in full',
        ':date, the highlights',
        ':date, such as it was',
        'Just :date',
        ':date, apparently',
    ],

];
