<?php

use App\Models\Calorie;

use function Pest\Laravel\get;

/**
 * A food entry is a whole day, but the row it hangs off is one item of many, so
 * every filter here is checked against days the linked row alone would miss.
 */
function foodSearch(array $conditions): string
{
    return '/search?'.http_build_query([
        'filter' => json_encode([['type' => 'calorie', 'conditions' => $conditions]]),
    ]);
}

/**
 * Log a day of food in order, so the lowest id (the row the timeline entry
 * hangs off) is the first item listed.
 *
 * @param  list<array{0: string, 1: int, 2: int}>  $items  [name, calories, protein]
 */
function logFoodDay(string $date, array $items): void
{
    foreach ($items as $index => [$name, $calories, $protein]) {
        Calorie::factory()->create([
            'occurred_at' => sprintf('%s %02d:00:00', $date, 8 + $index),
            'name' => $name,
            'meal' => 'lunch',
            'calories' => $calories,
            'protein' => $protein,
        ]);
    }
}

beforeEach(function () {
    // 3,200 kcal, no single item near it, and its chicken is only 200 of that.
    logFoodDay('2026-05-01', [
        ['Porridge', 400, 15],
        ['Chicken salad', 200, 30],
        ['Pizza', 1200, 20],
        ['Ice cream', 1400, 10],
    ]);

    // Chicken, but a small day.
    logFoodDay('2026-05-02', [
        ['Toast', 300, 8],
        ['Chicken wrap', 500, 35],
    ]);

    // A big day with no chicken in it.
    logFoodDay('2026-05-03', [
        ['Porridge', 400, 15],
        ['Burger', 1500, 40],
        ['Chips', 1300, 12],
    ]);
});

it('matches a day on its total, not on any single item', function () {
    get(foodSearch([['field' => 'calories', 'operator' => 'gte', 'value' => 3000]]))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->where('total', 2)
            ->where('groups.0.date', '2026-05-03')
            ->where('groups.1.date', '2026-05-01')
            ->etc()
        );
});

it('finds nothing when no single item clears the bar the day does', function () {
    get(foodSearch([['field' => 'item_calories', 'operator' => 'gte', 'value' => 3000]]))
        ->assertOk()
        ->assertInertia(fn ($page) => $page->where('total', 0)->etc());
});

it('searches every item in the day, not just the one the entry hangs off', function () {
    // Neither day opens with the chicken, so both are invisible to a filter
    // that only reads the linked row.
    get(foodSearch([['field' => 'item_name', 'operator' => 'contains', 'value' => 'chicken']]))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->where('total', 2)
            ->where('groups.0.date', '2026-05-02')
            ->where('groups.1.date', '2026-05-01')
            ->etc()
        );
});

it('matches an item on its own value', function () {
    get(foodSearch([['field' => 'item_calories', 'operator' => 'gte', 'value' => 1450]]))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->where('total', 1)
            ->where('groups.0.date', '2026-05-03')
            ->etc()
        );
});

it('requires one item to satisfy every item condition, not one each', function () {
    // 1 May has chicken and has items over 400 kcal, but no chicken over 400.
    get(foodSearch([
        ['field' => 'item_name', 'operator' => 'contains', 'value' => 'chicken'],
        ['field' => 'item_calories', 'operator' => 'gte', 'value' => 400],
    ]))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->where('total', 1)
            ->where('groups.0.date', '2026-05-02')
            ->etc()
        );
});

it('totals the whole day alongside an item filter, not just the matching items', function () {
    // The chicken on 1 May is 200 kcal. The day clears 3,000 only because
    // everything else counts, which is the whole point of mixing the scopes.
    get(foodSearch([
        ['field' => 'item_name', 'operator' => 'contains', 'value' => 'chicken'],
        ['field' => 'calories', 'operator' => 'gte', 'value' => 3000],
    ]))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->where('total', 1)
            ->where('groups.0.date', '2026-05-01')
            ->etc()
        );
});

it('finds a day by any item name through the Anything group', function () {
    $url = '/search?'.http_build_query([
        'filter' => json_encode([[
            'type' => 'any',
            'conditions' => [['field' => 'text', 'operator' => 'contains', 'value' => 'chicken']],
        ]]),
    ]);

    get($url)->assertOk()->assertInertia(fn ($page) => $page->where('total', 2)->etc());
});
