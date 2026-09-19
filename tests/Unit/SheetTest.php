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

it('draws a proportional bar', function () {
    expect(Sheet::bar(0.5, 10))->toBe('#####.....')
        ->and(Sheet::bar(0, 10))->toBe('..........')
        ->and(Sheet::bar(1, 10))->toBe('##########');
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

it('truncates rather than overflowing the width, measuring by character not by byte', function () {
    // "Kraków" is seven bytes but six characters: a byte-based clip would cut
    // mid-character and misalign the row instead of landing on width exactly.
    $row = Sheet::row('Kraków John Paul II International Airport', 'KRK', 20);

    expect(mb_strlen($row))->toBe(20)
        ->and($row)->toEndWith('KRK');
});

it('drops the label entirely rather than pushing the row past its width when the value alone fills it', function () {
    // A value long enough to consume the whole row leaves no room for the
    // guaranteed one-character gap; the row must still land on width exactly.
    $row = Sheet::row('From', 'Kraków John Paul II International Airport (KRK)', 44);

    expect(mb_strlen($row))->toBe(44)
        ->and($row)->toBe(mb_substr('Kraków John Paul II International Airport (KRK)', 0, 44));
});
