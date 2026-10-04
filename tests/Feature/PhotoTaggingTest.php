<?php

use App\Enums\ReviewKind;
use App\Models\Activity;
use App\Models\Subject;
use App\Models\User;

use function Pest\Laravel\actingAs;

it('places a subject tag at a point', function () {
    $attachment = Activity::factory()->create()->addMediaFromString(fakeJpeg())->usingFileName('p.jpg')->toMediaCollection('photos');
    $subject = Subject::factory()->person()->create();

    actingAs(User::factory()->create())->post("/attachments/{$attachment->id}/subjects", [
        'subject_id' => $subject->id, 'role' => 'subject', 'x' => 42.5, 'y' => 61.25,
    ])->assertRedirect();

    expect((float) $attachment->fresh()->subjects->first()->pivot->x)->toBe(42.5);
});

it('rejects a subject tag without a position', function () {
    $attachment = Activity::factory()->create()->addMediaFromString(fakeJpeg())->usingFileName('p.jpg')->toMediaCollection('photos');
    $subject = Subject::factory()->person()->create();

    actingAs(User::factory()->create())->post("/attachments/{$attachment->id}/subjects", [
        'subject_id' => $subject->id, 'role' => 'subject',
    ])->assertSessionHasErrors('x');
});

it('rejects a camera credit carrying a position', function () {
    $attachment = Activity::factory()->create()->addMediaFromString(fakeJpeg())->usingFileName('p.jpg')->toMediaCollection('photos');
    $subject = Subject::factory()->thing()->create();

    actingAs(User::factory()->create())->post("/attachments/{$attachment->id}/subjects", [
        'subject_id' => $subject->id, 'role' => 'camera', 'x' => 10, 'y' => 10,
    ])->assertSessionHasErrors('x');
});

it('marks a photograph reviewed and lets it be put back', function () {
    $attachment = Activity::factory()->create()->addMediaFromString(fakeJpeg())->usingFileName('p.jpg')->toMediaCollection('photos');

    actingAs(User::factory()->create())->post("/attachments/{$attachment->id}/review", ['kind' => 'subjects']);
    expect($attachment->fresh()->getCustomProperty(ReviewKind::Subjects->property()))->not->toBeNull();

    actingAs(User::factory()->create())->delete("/attachments/{$attachment->id}/review", ['kind' => 'subjects']);
    expect($attachment->fresh()->getCustomProperty(ReviewKind::Subjects->property()))->toBeNull();
});
