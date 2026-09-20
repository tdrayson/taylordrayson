<?php

namespace App\Http\Controllers;

use App\Support\PublicAsset;
use Illuminate\Http\JsonResponse;

/**
 * The PWA manifest, with content-hashed icon URLs so an installed app picks up
 * a new icon when the bytes change. A static JSON file cannot compute a hash,
 * which is why this is a route.
 */
class ManifestController extends Controller
{
    public function __invoke(): JsonResponse
    {
        // Deliberately left to revalidate: cache the manifest and the versioned
        // icon URLs inside it never reach anybody, which is the bug this fixes.
        return response()
            ->json([
                'name' => 'Taylor Drayson',
                'short_name' => 'Drayson',
                'start_url' => '/',
                'scope' => '/',
                'display' => 'standalone',
                'background_color' => '#ffffff',
                'theme_color' => '#ffffff',
                'icons' => [
                    ['src' => PublicAsset::url('/icons/icon-192.png'), 'sizes' => '192x192', 'type' => 'image/png'],
                    ['src' => PublicAsset::url('/icons/icon-512.png'), 'sizes' => '512x512', 'type' => 'image/png'],
                    ['src' => PublicAsset::url('/icons/icon-512-maskable.png'), 'sizes' => '512x512', 'type' => 'image/png', 'purpose' => 'maskable'],
                ],
            ])
            ->header('Content-Type', 'application/manifest+json');
    }
}
