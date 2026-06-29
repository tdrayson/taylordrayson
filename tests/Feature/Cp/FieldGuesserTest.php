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

it('marks occurred_at required and other fields nullable', function () {
    $fields = app(FieldGuesser::class)->guess(Flight::class);

    expect($fields['occurred_at']['rules'])->toBe(['required', 'date']);
    expect($fields['flight_number']['rules'])->toBe(['nullable', 'string']);
    expect($fields['duration']['rules'])->toBe(['nullable', 'numeric']);
});

it('labels columns as headline text', function () {
    $fields = app(FieldGuesser::class)->guess(Flight::class);

    expect($fields['flight_number']['label'])->toBe('Flight Number');
});
