<?php

use App\Cp\FieldGuesser;
use App\Models\Flight;

it('guesses field types from a model', function () {
    $fields = app(FieldGuesser::class)->guess(Flight::class);

    expect($fields['occurred_at']['type'])->toBe('datetime');
    expect($fields['duration']['type'])->toBe('number');
    expect($fields['distance_miles']['type'])->toBe('number');
    expect($fields['flight_number']['type'])->toBe('text');
    expect($fields['reason']['type'])->toBe('textarea');
    expect($fields['meta']['type'])->toBe('json');
});

it('derives required rules from NOT NULL columns and nullable rules from nullable columns', function () {
    $fields = app(FieldGuesser::class)->guess(Flight::class);

    // occurred_at is always required
    expect($fields['occurred_at']['rules'])->toBe(['required', 'date']);

    // flight_number is NOT NULL with no default: required
    expect($fields['flight_number']['rules'])->toBe(['required', 'string']);

    // cabin_class is nullable in the schema: nullable
    expect($fields['cabin_class']['rules'])->toBe(['nullable', 'string']);

    // distance_miles is nullable in the schema: nullable
    expect($fields['distance_miles']['rules'])->toBe(['nullable', 'numeric']);
});

it('labels columns as headline text', function () {
    $fields = app(FieldGuesser::class)->guess(Flight::class);

    expect($fields['flight_number']['label'])->toBe('Flight Number');
});
