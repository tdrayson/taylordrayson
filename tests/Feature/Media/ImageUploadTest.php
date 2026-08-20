<?php

use App\Models\Article;
use App\Models\Note;
use App\Models\User;
use App\Support\PendingUploads;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

beforeEach(fn () => $this->actingAs(User::factory()->create()));

/** Park a file the way the editor does, returning the id it hands back. */
function park(string $name = 'shot.jpg'): string
{
    return test()->postJson('/media/pending', ['file' => UploadedFile::fake()->image($name, 800, 600)])
        ->assertOk()
        ->json('data.id');
}

it('parks an upload and serves it back for preview', function () {
    $response = $this->postJson('/media/pending', [
        'file' => UploadedFile::fake()->image('cover.jpg', 1200, 800),
    ])->assertOk();

    expect($response->json('data.id'))->toStartWith('pending:')
        ->and($response->json('data.name'))->toBe('cover.jpg');

    $this->get($response->json('data.url'))->assertOk();
});

it('refuses a file that is not an image', function () {
    $this->postJson('/media/pending', ['file' => UploadedFile::fake()->create('notes.pdf', 20, 'application/pdf')])
        ->assertJsonValidationErrors('file');
});

it('keeps the whole authoring upload behind the login', function () {
    auth()->logout();

    $this->post('/media/pending', ['file' => UploadedFile::fake()->image('x.jpg')])
        ->assertRedirect('/login');
});

it('attaches a parked upload to the entry when it saves', function () {
    Storage::fake('public');

    $article = Article::factory()->create();

    $this->patch("/entries/article/{$article->id}", ['cover' => [park('cover.jpg')]])->assertRedirect();

    expect($article->refresh()->getMedia('cover'))->toHaveCount(1)
        ->and($article->getFirstMedia('cover')->name)->toBe('cover');
});

it('keeps a gallery in the order the editor arranged', function () {
    Storage::fake('public');

    $note = Note::factory()->create();
    $first = park('one.jpg');
    $second = park('two.jpg');

    $this->patch("/entries/note/{$note->id}", ['photos' => [$first, $second]])->assertRedirect();

    $uuids = $note->refresh()->getMedia('photos')->pluck('uuid')->all();

    // Send them back reversed: the list is the running order, not a set.
    $this->patch("/entries/note/{$note->id}", ['photos' => array_reverse($uuids)])->assertRedirect();

    expect($note->refresh()->getMedia('photos')->pluck('uuid')->all())->toBe(array_reverse($uuids));
});

it('detaches media the editor removed, and leaves untouched fields alone', function () {
    Storage::fake('public');

    $note = Note::factory()->create();

    $this->patch("/entries/note/{$note->id}", ['photos' => [park('one.jpg'), park('two.jpg')]]);
    expect($note->refresh()->getMedia('photos'))->toHaveCount(2);

    $keep = $note->getMedia('photos')->first()->uuid;
    $this->patch("/entries/note/{$note->id}", ['photos' => [$keep]]);
    expect($note->refresh()->getMedia('photos'))->toHaveCount(1);

    // An edit that never mentions photos must not wipe them: absent is "not
    // edited", which is a different thing from an empty list.
    $this->patch("/entries/note/{$note->id}", ['content' => 'Changed the words only.']);
    expect($note->refresh()->getMedia('photos'))->toHaveCount(1);
});

it('caps a large upload on its long edge and stores it as webp', function () {
    // The server is the backstop: an API post or an image pulled from Strava
    // never passes through the browser's own shrink.
    $response = $this->postJson('/media/pending', [
        'file' => UploadedFile::fake()->image('huge.jpg', 4000, 3000),
    ])->assertOk();

    $path = PendingUploads::path(substr($response->json('data.id'), strlen('pending:')));
    [$width, $height] = getimagesize($path);

    expect($width)->toBe(1920)
        ->and($height)->toBe(1440)
        ->and(mime_content_type($path))->toBe('image/webp');
});

it('leaves a gif alone rather than flattening it', function () {
    $response = $this->postJson('/media/pending', [
        'file' => UploadedFile::fake()->image('loop.gif', 400, 400),
    ])->assertOk();

    $path = PendingUploads::path(substr($response->json('data.id'), strlen('pending:')));

    expect($path)->toEndWith('.gif');
});
