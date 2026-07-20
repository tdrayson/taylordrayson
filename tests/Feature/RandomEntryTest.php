<?php

use App\Models\Activity;
use App\Models\Note;

use function Pest\Laravel\get;

it('redirects /lucky to a random entry page', function () {
    $note = Note::factory()->create();

    get('/lucky')
        ->assertRedirect($note->fresh()->url());
});

it('redirects /random to a random entry page', function () {
    $activity = Activity::factory()->create();

    get('/random')
        ->assertRedirect($activity->fresh()->url());
});

it('redirects to one of the available entries', function () {
    $urls = collect([
        Note::factory()->create(),
        Activity::factory()->create(),
        Note::factory()->create(),
    ])->map(fn ($model) => $model->fresh()->url())->all();

    $response = get('/lucky');

    $response->assertRedirect();
    expect(parse_url($response->headers->get('Location'), PHP_URL_PATH))->toBeIn($urls);
});

it('returns 404 when the timeline is empty', function () {
    get('/lucky')->assertNotFound();
    get('/random')->assertNotFound();
});
