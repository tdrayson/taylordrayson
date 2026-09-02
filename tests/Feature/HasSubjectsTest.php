<?php

use App\Models\Activity;
use App\Models\Subject;

it('unions an entry\'s own subjects with the ones on its photographs', function () {
    $activity = Activity::factory()->create();
    $direct = Subject::factory()->person()->create(['name' => 'Clare']);
    $inPhoto = Subject::factory()->pet()->create(['name' => 'Bear']);

    $activity->subjects()->attach($direct);
    $attachment = $activity->addMediaFromString(fakeJpeg())->usingFileName('p.jpg')->toMediaCollection('photos');
    $attachment->subjects()->attach($inPhoto, ['role' => 'subject', 'x' => 50, 'y' => 40]);

    expect($activity->allSubjects()->pluck('name')->all())->toBe(['Clare', 'Bear']);
});

it('drops a subject from the entry when its last photo tag goes', function () {
    $activity = Activity::factory()->create();
    $subject = Subject::factory()->person()->create();
    $attachment = $activity->addMediaFromString(fakeJpeg())->usingFileName('p.jpg')->toMediaCollection('photos');
    $attachment->subjects()->attach($subject, ['role' => 'subject', 'x' => 10, 'y' => 10]);

    expect($activity->allSubjects())->toHaveCount(1);

    $attachment->subjects()->detach($subject);

    expect($activity->fresh()->allSubjects())->toHaveCount(0);
});

it('counts a subject once when tagged both directly and in a photo', function () {
    $activity = Activity::factory()->create();
    $subject = Subject::factory()->person()->create();
    $activity->subjects()->attach($subject);
    $attachment = $activity->addMediaFromString(fakeJpeg())->usingFileName('p.jpg')->toMediaCollection('photos');
    $attachment->subjects()->attach($subject, ['role' => 'subject', 'x' => 10, 'y' => 10]);

    expect($activity->allSubjects())->toHaveCount(1);
});
