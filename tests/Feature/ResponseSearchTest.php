<?php

use App\Enums\CommentStatus;
use App\Enums\ReactionType;
use App\Enums\Source;
use App\Enums\WebmentionKind;
use App\Models\Note;
use App\Models\SyndicatedResponse;
use App\Support\PortableText;

use function Pest\Laravel\get;

/**
 * The notes a filter finds, by their plain-text content.
 *
 * @param  array<int, array<string, mixed>>  $conditions
 * @return list<string>
 */
function notesMatching(array $conditions, string $type = 'any'): array
{
    $url = '/search?'.http_build_query(['filter' => json_encode([['type' => $type, 'conditions' => $conditions]])]);

    return collect(get($url)->assertOk()->inertiaProps('groups'))
        ->flatMap(fn (array $day): array => $day['items'])
        ->map(fn (array $item): string => is_array($item['body']) ? PortableText::plainText($item['body']) : (string) ($item['body'] ?? $item['title']))
        ->sort()
        ->values()
        ->all();
}

function noteSaying(string $text): Note
{
    return Note::factory()->create(['content' => PortableText::fromPlainText($text)]);
}

function commentOn(Note $note, string $body, CommentStatus $status = CommentStatus::Approved): void
{
    $note->comments()->create(['author_name' => 'Sam', 'body' => PortableText::fromPlainText($body), 'status' => $status]);
}

function syndicatedOn(Note $note, WebmentionKind $kind, string $author, Source $source = Source::Strava): void
{
    SyndicatedResponse::factory()->for($note, 'target')->create([
        'source' => $source->value,
        'source_id' => fake()->uuid(),
        'kind' => $kind,
        'author_name' => $author,
        'status' => CommentStatus::Approved,
    ]);
}

function reactTo(Note $note, int $count, ReactionType $type = ReactionType::Like): void
{
    foreach (range(1, $count) as $index) {
        $note->reactions()->create(['type' => $type, 'identity_key' => hash('sha256', "{$note->id}-{$type->value}-{$index}")]);
    }
}

it('finds entries by the text of an approved comment, not its Portable Text keys or a pending one', function () {
    commentOn(noteSaying('Kettle'), 'Great run today');
    commentOn(noteSaying('Toaster'), 'Great run too', CommentStatus::Pending);

    expect(notesMatching([['field' => 'response_text', 'operator' => 'contains', 'value' => 'great run']]))->toBe(['Kettle'])
        ->and(notesMatching([['field' => 'response_text', 'operator' => 'contains', 'value' => 'span']]))->toBe([]);
});

it('holds every per-response condition to the same response', function () {
    $mixed = noteSaying('Mixed');
    syndicatedOn($mixed, WebmentionKind::Reply, 'Jane');
    syndicatedOn($mixed, WebmentionKind::Like, 'Bob');
    syndicatedOn(noteSaying('Bob replied'), WebmentionKind::Reply, 'Bob');
    syndicatedOn(noteSaying('Swarm'), WebmentionKind::Reply, 'Bob', Source::Swarm);

    expect(notesMatching([
        ['field' => 'response_source', 'operator' => 'is', 'value' => ['strava']],
        ['field' => 'response_kind', 'operator' => 'is', 'value' => ['reply']],
        ['field' => 'response_author', 'operator' => 'contains', 'value' => 'bob'],
    ], 'note'))->toBe(['Bob replied']);
});

it('tells a local comment apart from a webmention and finds a webmention by its site', function () {
    commentOn(noteSaying('Commented'), 'Nice');
    noteSaying('Mentioned')->webmentions()->create([
        'source_url' => 'https://aaronparecki.com/2026/09/01/1/',
        'target_url' => 'https://taylordrayson.com/notes/1',
        'kind' => WebmentionKind::Reply->value,
        'author_name' => 'Aaron',
        'author_url' => 'https://aaronparecki.com/',
        'status' => CommentStatus::Approved,
    ]);

    expect(notesMatching([['field' => 'response_source', 'operator' => 'is', 'value' => ['comment']]]))->toBe(['Commented'])
        ->and(notesMatching([['field' => 'response_source', 'operator' => 'is_not', 'value' => ['comment']]]))->toBe(['Mentioned'])
        ->and(notesMatching([['field' => 'response_site', 'operator' => 'contains', 'value' => 'aaronparecki.com']]))->toBe(['Mentioned']);
});

it('counts reactions, optionally of one emoji', function () {
    reactTo(noteSaying('Five'), 5);
    reactTo($mixed = noteSaying('Four'), 2);
    reactTo($mixed, 2, ReactionType::Love);
    noteSaying('None');

    expect(notesMatching([['field' => 'reactions', 'operator' => 'gte', 'value' => 5]]))->toBe(['Five'])
        ->and(notesMatching([['field' => 'reactions', 'operator' => 'eq', 'value' => 0]], 'note'))->toBe(['None'])
        ->and(notesMatching([
            ['field' => 'reaction', 'operator' => 'is', 'value' => ['love']],
            ['field' => 'reactions', 'operator' => 'gte', 'value' => 2],
        ]))->toBe(['Four']);
});

it('counts only the responses the group describes', function () {
    $busy = noteSaying('Busy');
    syndicatedOn($busy, WebmentionKind::Like, 'A');
    syndicatedOn($busy, WebmentionKind::Like, 'B');
    commentOn($busy, 'Hi');
    $quiet = noteSaying('Quiet');
    syndicatedOn($quiet, WebmentionKind::Like, 'A');
    commentOn($quiet, 'Hi');
    commentOn($quiet, 'Hello');

    expect(notesMatching([['field' => 'responses', 'operator' => 'gte', 'value' => 3]]))->toBe(['Busy', 'Quiet'])
        ->and(notesMatching([
            ['field' => 'response_source', 'operator' => 'is', 'value' => ['strava']],
            ['field' => 'responses', 'operator' => 'gte', 'value' => 2],
        ]))->toBe(['Busy']);
});
