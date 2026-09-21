<?php

use App\Actions\Hub\MarkSeen;
use App\Models\User;

it('returns the previous stamp and then moves it on', function () {
    $this->freezeTime();

    $user = User::factory()->create(['hub_seen_at' => now()->subDay()]);

    $before = app(MarkSeen::class)($user);

    expect($before?->toDateTimeString())->toBe(now()->subDay()->toDateTimeString())
        ->and($user->fresh()->hub_seen_at->isToday())->toBeTrue();
});

it('returns null on a first ever visit', function () {
    $user = User::factory()->create(['hub_seen_at' => null]);

    expect(app(MarkSeen::class)($user))->toBeNull()
        ->and($user->fresh()->hub_seen_at)->not->toBeNull();
});
