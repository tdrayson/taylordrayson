<?php

use App\Actions\Og\BuildEntryOgData;
use App\Enums\EntryStatus;
use App\Mcp\Tools\Timeline;
use App\Models\Note;
use App\Models\Scopes\ListedScope;
use App\Models\TimelineEntry;
use App\Models\User;
use App\Support\OgRenderer;
use App\Support\PortableText;
use Illuminate\Support\Facades\Storage;
use Inertia\Testing\AssertableInertia as Assert;

/*
 * One note per status, walked across every surface for a guest and for the
 * owner. Listings show published only, to both; the URL and search differ.
 */

beforeEach(function (): void {
    Storage::fake('local');

    foreach (EntryStatus::cases() as $status) {
        $note = Note::factory()->create([
            'content' => PortableText::fromPlainText("Matrix {$status->value} body"),
            'slug' => "matrix-{$status->value}",
            'occurred_at' => '2026-06-15 09:00:00',
            'password' => $status === EntryStatus::Private ? 'hunter2' : null,
        ]);

        $note->syncTagNames(['Matrix']);
        // Published first, then moved, so the draft keeps its date and dated URL.
        $note->update(['status' => $status]);
    }
});

/** The matrix slugs among a feed payload's cards, sorted. */
function matrixSlugsIn(?array $groups): array
{
    return collect($groups ?? [])
        ->flatMap(fn (array $group): array => array_column($group['items'], 'url'))
        ->map(fn (string $url): string => basename($url))
        ->filter(fn (string $slug): bool => str_starts_with($slug, 'matrix-'))
        ->unique()->sort()->values()->all();
}

/** The matrix slugs mentioned anywhere in a text body (sitemap XML, a feed), sorted. */
function matrixSlugsInText(string $body): array
{
    preg_match_all('/matrix-(?:draft|published|unlisted|private)/', $body, $matches);

    return collect($matches[0])->unique()->sort()->values()->all();
}

it('shows each status only where it belongs', function (bool $owner) {
    if ($owner) {
        $this->actingAs(User::factory()->create());
    }

    $listed = ['matrix-published'];
    $searchable = $owner ? ['matrix-private', 'matrix-published', 'matrix-unlisted'] : ['matrix-published'];

    // Entry URL.
    $this->get('/2026/06/15/matrix-draft')->assertStatus($owner ? 200 : 404);
    $this->get('/2026/06/15/matrix-published')->assertOk()
        ->assertInertia(fn (Assert $page) => $page->where('locked', false)->where('og.noindex', false));
    $this->get('/2026/06/15/matrix-unlisted')->assertOk()
        ->assertInertia(fn (Assert $page) => $page->where('locked', false)->where('og.noindex', true));
    $this->get('/2026/06/15/matrix-private')->assertOk()
        ->assertInertia(fn (Assert $page) => $page->where('locked', ! $owner)->where('og.noindex', true)->has('og.title'));

    // Listings: the same for both viewers.
    expect(matrixSlugsIn($this->get('/')->inertiaProps('groups')))->toBe($listed)
        ->and(matrixSlugsIn($this->get('/notes')->inertiaProps('groups')))->toBe($listed)
        ->and(matrixSlugsIn($this->get('/tags/matrix')->inertiaProps('groups')))->toBe($listed)
        ->and(matrixSlugsInText($this->get('/sitemap/2026.xml')->getContent()))->toBe($listed)
        ->and(matrixSlugsInText($this->get('/feed')->getContent()))->toBe($listed);

    $timeline = collect(callTool(Timeline::class, ['from' => '2026-06-15'])['data']['entries'])
        ->map(fn (array $entry): string => basename($entry['url']))->sort()->values()->all();

    expect($timeline)->toBe($listed);

    // Search: guests find published, the owner everything with a spine row.
    $search = '/search?'.http_build_query(['filter' => json_encode([
        ['type' => 'note', 'conditions' => [['field' => 'content', 'operator' => 'contains', 'value' => 'Matrix']]],
    ])]);

    expect(matrixSlugsIn($this->get($search)->inertiaProps('groups')))->toBe($searchable);

    // OG image: every status with a spine row renders, whoever asks; a draft has none.
    foreach (['published', 'unlisted', 'private'] as $status) {
        $entry = TimelineEntry::withoutGlobalScope(ListedScope::class)->where('url_slug', "matrix-{$status}")->sole();
        $path = 'og/'.OgRenderer::generation().'/entry/'.md5($entry->id.'|'.BuildEntryOgData::entryTimestamp($entry)).'.png';
        Storage::disk('local')->put($path, 'fake-png-bytes');

        $this->get("/og/entry/{$entry->id}.png")->assertOk();
    }

    expect(TimelineEntry::withoutGlobalScope(ListedScope::class)->where('url_slug', 'matrix-draft')->exists())->toBeFalse();
})->with(['guest' => false, 'owner' => true]);
