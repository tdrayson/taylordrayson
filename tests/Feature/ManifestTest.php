<?php

use function Pest\Laravel\get;

it('serves the manifest with the values the static file carried', function () {
    $response = get('/manifest.webmanifest')
        ->assertOk()
        ->assertHeader('Content-Type', 'application/manifest+json');

    $manifest = json_decode($response->getContent(), true);

    expect($manifest)->toBeArray()
        ->and(array_keys($manifest))->toBe([
            'name', 'short_name', 'start_url', 'scope', 'display',
            'background_color', 'theme_color', 'icons',
        ])
        ->and($manifest['name'])->toBe('Taylor Drayson')
        ->and($manifest['short_name'])->toBe('Drayson')
        ->and($manifest['start_url'])->toBe('/')
        ->and($manifest['scope'])->toBe('/')
        ->and($manifest['display'])->toBe('standalone')
        ->and($manifest['background_color'])->toBe('#ffffff')
        ->and($manifest['theme_color'])->toBe('#ffffff');

    expect($manifest['icons'])->toHaveCount(3)
        ->and($manifest['icons'][0]['sizes'])->toBe('192x192')
        ->and($manifest['icons'][1]['sizes'])->toBe('512x512')
        ->and($manifest['icons'][2]['purpose'])->toBe('maskable');

    foreach ($manifest['icons'] as $icon) {
        expect($icon['type'])->toBe('image/png')
            ->and($icon['src'])->toMatch('#^/icons/[a-z0-9-]+\.png\?v=[0-9a-f]{8}$#');
    }
});

it('does not tell clients to hold on to the manifest', function () {
    $cacheControl = get('/manifest.webmanifest')->headers->get('Cache-Control');

    expect($cacheControl)->not->toContain('max-age=3')
        ->and($cacheControl)->not->toContain('immutable');
});
