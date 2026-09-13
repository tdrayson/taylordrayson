<?php

use App\Console\Commands\Fetch\FetchLinkFavicons;
use App\Jobs\ResolveLinkFavicons;
use App\Models\Note;
use Illuminate\Support\Facades\Bus;

/** A Portable Text document linking a span to a dynamicHref, unresolved until render. */
function documentWithDynamicHref(): array
{
    return [[
        '_type' => 'block',
        '_key' => 'b1',
        'style' => 'normal',
        'markDefs' => [['_key' => 'l1', '_type' => 'dynamicHref', 'tag' => 'site.social', 'options' => ['network' => 'github']]],
        'children' => [['_type' => 'span', '_key' => 's1', 'text' => 'my github', 'marks' => ['l1']]],
    ]];
}

it('queues a favicon fetch for a host only reachable through a dynamic link', function () {
    config(['site.social' => ['github' => 'https://github.com/tdrayson']]);
    Bus::fake();

    Note::factory()->create(['content' => documentWithDynamicHref()]);

    Bus::assertDispatched(ResolveLinkFavicons::class, fn (ResolveLinkFavicons $job): bool => $job->hosts === ['github.com']);
});

it('finds a dynamic link host when sweeping every entry for favicons:favicons', function () {
    config(['site.social' => ['github' => 'https://github.com/tdrayson']]);
    Bus::fake();

    Note::factory()->create(['content' => documentWithDynamicHref()]);

    $hosts = (new ReflectionMethod(FetchLinkFavicons::class, 'linkedHosts'))
        ->invoke(app(FetchLinkFavicons::class));

    expect($hosts)->toBe(['github.com']);
});
