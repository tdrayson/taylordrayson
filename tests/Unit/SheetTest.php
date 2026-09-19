<?php

use App\Presenters\Exports\Sheets\Sheet;

it('draws rules, centred text and aligned rows to a fixed width', function () {
    expect(Sheet::rule(10))->toBe('----------')
        ->and(mb_strlen(Sheet::centre('hi', 10)))->toBe(10)
        ->and(trim(Sheet::centre('hi', 10)))->toBe('hi')
        ->and(Sheet::row('Cabin', 'Economy', 20))->toBe('Cabin        Economy');
});

it('draws a dotted leader between a label and a value', function () {
    expect(Sheet::leader('Litres', '42.10L', 20))->toBe('Litres........42.10L');
});

it('draws a box around a list of lines', function () {
    $box = Sheet::box(['Line one', 'Line two'], 20);
    $border = '+'.str_repeat('-', 18).'+';
    $line = fn (string $text): string => '| '.str_pad($text, 16).' |';

    expect($box)->toBe(implode("\n", [$border, $line('Line one'), $line('Line two'), $border]));
});

it('widens a box to fit a line wider than its floor, keeping every row the same width', function () {
    $long = str_repeat('x', 30);
    $lines = explode("\n", Sheet::box(['short', $long], 20));

    expect(array_map('mb_strlen', $lines))->toBe([34, 34, 34, 34])
        ->and($lines[0])->toBe($lines[3])
        ->and($lines[2])->toContain($long);
});

it('draws a proportional bar', function () {
    expect(Sheet::bar(0.5, 10))->toBe('#####.....')
        ->and(Sheet::bar(0, 10))->toBe('..........')
        ->and(Sheet::bar(1, 10))->toBe('##########');
});

it('draws a rating as filled and empty stars', function () {
    expect(Sheet::stars(0.8))->toBe('****-')
        ->and(Sheet::stars(0))->toBe('-----')
        ->and(Sheet::stars(1))->toBe('*****');
});

it('draws an uppercased heading with a rule beneath it', function () {
    expect(Sheet::heading('Flight', 10))->toBe('FLIGHT'."\n".str_repeat('=', 10));
});

it('wraps a body to the sheet width without splitting words', function () {
    $wrapped = Sheet::wrap('one two three four five', 10);

    expect($wrapped)->toBe(['one two', 'three four', 'five']);

    foreach ($wrapped as $line) {
        expect(mb_strlen($line))->toBeLessThanOrEqual(10);
    }
});

it('measures a single-line row by character, not by byte', function () {
    // "Kraków" is seven bytes but six characters: a byte-based gap
    // calculation would misalign the row instead of landing on width exactly.
    $row = Sheet::row('Kraków', 'KRK', 20);

    expect(mb_strlen($row))->toBe(20)
        ->and($row)->toEndWith('KRK');
});

it('stacks the value on an indented line beneath the label rather than dropping either', function () {
    // The real KRK airport name: too long to share a row with its "From"
    // label at the field width every flight row renders at.
    $value = 'Kraków John Paul II International Airport (KRK)';
    $lines = explode("\n", Sheet::row('From', $value, 44));

    expect($lines[0])->toBe('From')
        ->and($lines)->toHaveCount(3);

    foreach ($lines as $line) {
        expect(mb_strlen($line))->toBeLessThanOrEqual(44);
    }

    // Every continuation line is indented two spaces; stripping that and
    // rejoining with a single space must reconstruct the value untruncated.
    $recovered = implode(' ', array_map(fn (string $line): string => ltrim($line), array_slice($lines, 1)));

    expect($recovered)->toBe($value);
});
