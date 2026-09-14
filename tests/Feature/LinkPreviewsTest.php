<?php

use App\Models\Airport;
use App\Models\Article;
use App\Models\Flight;
use Saloon\Http\Faking\MockResponse;
use Saloon\Laravel\Facades\Saloon;

use function Pest\Laravel\get;

// Content carrying a link queues a favicon fetch; keep it off the network.
beforeEach(fn () => Saloon::fake(['*' => MockResponse::make('', 404)]));

it('exposes previews only for internal, previewable content links', function () {
    // Target article the link points to. Content is left empty so the card's
    // subtitle falls back to the explicit excerpt (PortableText::plainText of
    // a non-empty body would otherwise win).
    $target = Article::factory()->create([
        'title' => 'Target Post',
        'excerpt' => 'A short summary.',
        'occurred_at' => '2026-05-01 10:00:00',
        'slug' => 'target-post',
        'status' => 'published',
        'content' => [],
    ]);
    $targetHref = '/2026/05/01/target-post';

    // Source article whose content links to the target, plus an external link
    // and an internal link nothing can preview. /drafts is behind auth, so a
    // card describing it would be one most readers could never open.
    $source = Article::factory()->create([
        'occurred_at' => '2026-05-02 10:00:00',
        'slug' => 'source-post',
        'status' => 'published',
        'content' => [[
            '_type' => 'block',
            'markDefs' => [
                ['_key' => 'a', '_type' => 'link', 'href' => $targetHref],
                ['_key' => 'b', '_type' => 'link', 'href' => 'https://example.com'],
                ['_key' => 'c', '_type' => 'link', 'href' => '/drafts'],
            ],
            'children' => [
                ['_type' => 'span', 'marks' => ['a'], 'text' => 'target'],
                ['_type' => 'span', 'marks' => ['b'], 'text' => 'external'],
                ['_type' => 'span', 'marks' => ['c'], 'text' => 'drafts'],
            ],
        ]],
    ]);

    get('/2026/05/02/source-post')
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->has('linkPreviews', 1)
            ->where("linkPreviews.$targetHref.title", 'Target Post')
            ->where("linkPreviews.$targetHref.excerpt", 'A short summary.')
            ->where("linkPreviews.$targetHref.type", 'article')
            ->where("linkPreviews.$targetHref.url", $targetHref)
        );
});

it('names the airports in a flight link preview, not just their codes', function () {
    Airport::factory()->create(['iata_code' => 'KRK', 'name' => 'Kraków John Paul II International Airport']);
    Airport::factory()->create(['iata_code' => 'LGW', 'name' => 'London Gatwick Airport']);

    $flight = Flight::factory()->create([
        'occurred_at' => '2026-05-01 10:00:00',
        'origin_iata' => 'KRK',
        'destination_iata' => 'LGW',
    ]);
    $flightHref = '/2026/05/01/'.$flight->timelineEntry->url_slug;

    $source = Article::factory()->create([
        'occurred_at' => '2026-05-02 10:00:00',
        'slug' => 'source-post',
        'status' => 'published',
        'content' => [[
            '_type' => 'block',
            'markDefs' => [
                ['_key' => 'a', '_type' => 'link', 'href' => $flightHref],
            ],
            'children' => [
                ['_type' => 'span', 'marks' => ['a'], 'text' => 'flight'],
            ],
        ]],
    ]);

    get('/2026/05/02/source-post')
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->where("linkPreviews.$flightHref.excerpt", fn (string $excerpt) => str_contains($excerpt, 'Kraków John Paul II International Airport')
                && str_contains($excerpt, 'London Gatwick Airport'))
        );
});
