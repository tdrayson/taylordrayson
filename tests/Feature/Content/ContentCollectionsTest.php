<?php

use Statamic\Facades\Collection;

it('has date-ordered articles and notes collections', function (string $handle) {
    $c = Collection::findByHandle($handle);
    expect($c)->not->toBeNull();
    expect($c->dated())->toBeTrue();
})->with(['articles', 'notes']);
