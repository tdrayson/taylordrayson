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
        'I slept :duration last night',
        'I got :duration of sleep in',
        'I was out cold for :duration',
        ':duration of glorious sleep',
        'I managed :duration of shut-eye',
        'Dead to the world for :duration',
    ],

    'food' => [
        'I ate :kcal kcal across the day',
        'I demolished :kcal kcal today',
        ':kcal kcal, and no regrets',
        'A casual :kcal kcal over the day',
        'I put away :kcal kcal today',
        ':kcal kcal, purely for science',
    ],

    'fuel' => [
        'I put £:cost of fuel in the car',
        'I fed the car another £:cost',
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
        'podcast' => ['This Week With', 'Every episode so far'],
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
        'What I got up to in :date',
        'How :date actually went',
        ':date, from start to finish',
        'All of :date, for the record',
    ],
    'day' => [
        'What I got up to on :date',
        'How :date actually went',
        ':date, from start to finish',
        'Everything :date threw at me',
    ],

];
