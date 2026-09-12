<?php

use App\Actions\Syndicated\ReconcileResponses;
use App\Actions\Webmentions\StoreAuthorPhoto;
use App\Data\SyndicatedResponseData;
use App\Enums\Source;
use App\Enums\WebmentionKind;
use App\Models\Note;
use App\Models\SyndicatedResponse;
use App\Support\PortableText;

function kudo(string $name): SyndicatedResponseData
{
    return new SyndicatedResponseData(
        kind: WebmentionKind::Like,
        authorName: $name,
        occurredAt: now(),
    );
}

function remoteComment(string $id, string $text, string $name = 'Clare A.'): SyndicatedResponseData
{
    return new SyndicatedResponseData(
        kind: WebmentionKind::Reply,
        authorName: $name,
        occurredAt: now(),
        sourceId: $id,
        body: PortableText::fromPlainText($text),
    );
}

it('stores the gestures the source just reported', function () {
    $note = Note::factory()->create();

    app(ReconcileResponses::class)($note, Source::Strava, [kudo('Justin M.'), kudo('Clare A.')]);

    expect($note->syndicatedResponses()->count())->toBe(2);
});

// Strava's kudos list is a list of people, so two of the same name are two
// humans and collapsing them would lose one.
it('keeps two people who share a name', function () {
    $note = Note::factory()->create();

    app(ReconcileResponses::class)($note, Source::Strava, [kudo('Clare A.'), kudo('Clare A.')]);

    expect($note->syndicatedResponses()->count())->toBe(2);
});

// A gesture carries no id, so the fetched list is the whole truth for that kind
// and one taken back upstream has to disappear here.
it('drops a gesture that is no longer in the list', function () {
    $note = Note::factory()->create();
    $reconcile = app(ReconcileResponses::class);

    $reconcile($note, Source::Strava, [kudo('Justin M.'), kudo('Clare A.')]);
    $reconcile($note, Source::Strava, [kudo('Clare A.')]);

    expect($note->syndicatedResponses()->pluck('author_name')->all())->toBe(['Clare A.']);
});

it('leaves another source alone when one reports its gestures', function () {
    $note = Note::factory()->create();
    SyndicatedResponse::factory()->for($note, 'target')->create(['source' => Source::Swarm->value]);

    app(ReconcileResponses::class)($note, Source::Strava, [kudo('Justin M.')]);

    expect($note->syndicatedResponses()->where('source', Source::Swarm->value)->count())->toBe(1);
});

// Prose has an id, so an edit updates the row rather than making a second one.
it('updates a comment in place when its text changes', function () {
    $note = Note::factory()->create();
    $reconcile = app(ReconcileResponses::class);

    $reconcile($note, Source::Strava, [remoteComment('2257139458', 'Do they not do padel in here')]);
    $reconcile($note, Source::Strava, [remoteComment('2257139458', 'Do they not do padel in there')]);

    $stored = $note->syndicatedResponses()->sole();

    expect(PortableText::plainText($stored->body))->toBe('Do they not do padel in there');
});

it('removes a comment that has gone from the source', function () {
    $note = Note::factory()->create();
    $reconcile = app(ReconcileResponses::class);

    $reconcile($note, Source::Strava, [remoteComment('1', 'First'), remoteComment('2', 'Second')]);
    $reconcile($note, Source::Strava, [remoteComment('2', 'Second')]);

    expect($note->syndicatedResponses()->pluck('source_id')->all())->toBe(['2']);
});

// An empty payload means every comment stored for this source has gone.
it('removes every comment when the source reports none at all', function () {
    $note = Note::factory()->create();
    $reconcile = app(ReconcileResponses::class);

    $reconcile($note, Source::Strava, [remoteComment('1', 'First'), remoteComment('2', 'Second')]);
    $reconcile($note, Source::Strava, []);

    expect($note->syndicatedResponses()->count())->toBe(0);
});

// The delete-then-rewrite spans a third-party photo fetch per row, so a
// failure partway through must not leave the entry with its responses gone.
it('leaves stored responses untouched when a write fails partway through', function () {
    $note = Note::factory()->create();
    SyndicatedResponse::factory()->for($note, 'target')->create([
        'source' => Source::Strava->value, 'kind' => WebmentionKind::Like, 'author_name' => 'Existing',
    ]);

    $calls = 0;
    $photo = Mockery::mock(StoreAuthorPhoto::class);
    $photo->shouldReceive('__invoke')->andReturnUsing(function () use (&$calls) {
        $calls++;

        if ($calls === 2) {
            throw new RuntimeException('boom');
        }

        return null;
    });
    app()->instance(StoreAuthorPhoto::class, $photo);

    expect(fn () => app(ReconcileResponses::class)($note, Source::Strava, [kudo('One'), kudo('Two')]))
        ->toThrow(RuntimeException::class);

    expect($note->syndicatedResponses()->sole()->author_name)->toBe('Existing');
});
