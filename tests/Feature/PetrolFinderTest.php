<?php

use App\Services\PetrolFinder;
use Illuminate\Support\Facades\Http;

it('resolves a brand name to its web domain', function () {
    Http::fake(['*petrolfinder.uk/api/brands*' => Http::response([
        'brands' => [
            ['brand' => 'BP', 'logo' => 'https://cdn.brandfetch.io/bp.com?c=abc'],
        ],
    ], 200)]);

    expect((new PetrolFinder)->brandDomain('bp'))->toBe('bp.com');
});

it('returns null for an unlisted brand', function () {
    Http::fake(['*petrolfinder.uk/api/brands*' => Http::response(['brands' => []], 200)]);

    expect((new PetrolFinder)->brandDomain('Unknown Garage'))->toBeNull();
});
