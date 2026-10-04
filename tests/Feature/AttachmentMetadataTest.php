<?php

use App\Models\Activity;
use App\Models\User;

use function Pest\Laravel\actingAs;
use function Pest\Laravel\patch;

it('stores alt and caption in custom properties', function () {
    $activity = Activity::factory()->create();
    $attachment = $activity->addMediaFromString(fakeJpeg())->usingFileName('p.jpg')->toMediaCollection('photos');

    actingAs(User::factory()->create())->patch("/attachments/{$attachment->id}", [
        'alt' => 'Clare on the summit, squinting',
        'caption' => 'Whyteleafe, August',
    ])->assertRedirect();

    expect($attachment->fresh()->getCustomProperty('alt'))
        ->toBe('Clare on the summit, squinting');
});

it('sends alt and caption through to the gallery payload', function () {
    $activity = Activity::factory()->create();
    $attachment = $activity->addMediaFromString(fakeJpeg())->usingFileName('p.jpg')->toMediaCollection('photos');
    $attachment->setCustomProperty('alt', 'A described photo')->save();

    $photo = $activity->galleryPhotos()[0];

    expect($photo['alt'])->toBe('A described photo')
        ->and($photo['id'])->toBe($attachment->id);
});

it('refuses a write from a guest', function () {
    $activity = Activity::factory()->create();
    $attachment = $activity->addMediaFromString(fakeJpeg())->usingFileName('p.jpg')->toMediaCollection('photos');

    patch("/attachments/{$attachment->id}", ['alt' => 'x'])->assertRedirect('/login');
});
