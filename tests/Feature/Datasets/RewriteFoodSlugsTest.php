<?php

use Illuminate\Support\Facades\DB;

it('rewrites food url slugs from calories to food, resolving a same-day collision', function () {
    $migration = require database_path('migrations/2026_09_13_000006_rewrite_food_url_slugs_from_calories_to_food.php');

    DB::table('timeline_entries')->insert([
        ['dataset' => 'food', 'entry_id' => 9001, 'url_slug' => 'calories', 'occurred_at' => '2024-01-01 23:59:59', 'created_at' => now(), 'updated_at' => now()],
        ['dataset' => 'food', 'entry_id' => 9002, 'url_slug' => 'calories-2', 'occurred_at' => '2024-01-02 23:59:59', 'created_at' => now(), 'updated_at' => now()],
        ['dataset' => 'place', 'entry_id' => 9003, 'url_slug' => 'food', 'occurred_at' => '2024-01-02 09:00:00', 'created_at' => now(), 'updated_at' => now()],
    ]);

    $migration->up();

    expect(DB::table('timeline_entries')->where('entry_id', 9001)->where('dataset', 'food')->value('url_slug'))->toBe('food')
        ->and(DB::table('timeline_entries')->where('entry_id', 9002)->where('dataset', 'food')->value('url_slug'))->toBe('food-2')
        ->and(DB::table('timeline_entries')->where('entry_id', 9003)->where('dataset', 'place')->value('url_slug'))->toBe('food');
});

it('reverses food url slugs back to calories on down', function () {
    $migration = require database_path('migrations/2026_09_13_000006_rewrite_food_url_slugs_from_calories_to_food.php');

    DB::table('timeline_entries')->insert([
        ['dataset' => 'food', 'entry_id' => 9101, 'url_slug' => 'food', 'occurred_at' => '2024-02-01 23:59:59', 'created_at' => now(), 'updated_at' => now()],
        ['dataset' => 'food', 'entry_id' => 9102, 'url_slug' => 'food-2', 'occurred_at' => '2024-02-02 23:59:59', 'created_at' => now(), 'updated_at' => now()],
    ]);

    $migration->down();

    expect(DB::table('timeline_entries')->where('entry_id', 9101)->where('dataset', 'food')->value('url_slug'))->toBe('calories')
        ->and(DB::table('timeline_entries')->where('entry_id', 9102)->where('dataset', 'food')->value('url_slug'))->toBe('calories-2');
});
