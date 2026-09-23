<?php

use App\Actions\Hub\MarkSeen;
use App\Models\User;

it('reads the previous stamp without moving it', function () {
    $this->freezeTime();

    $user = User::factory()->create(['hub_seen_at' => now()->subDay()]);

    $previous = app(MarkSeen::class)->previous($user);

    expect($previous?->toDateTimeString())->toBe(now()->subDay()->toDateTimeString())
        ->and($user->fresh()->hub_seen_at->toDateTimeString())->toBe(now()->subDay()->toDateTimeString());
});

it('returns null on a first ever visit', function () {
    $user = User::factory()->create(['hub_seen_at' => null]);

    expect(app(MarkSeen::class)->previous($user))->toBeNull();
});

it('moves the stamp to now', function () {
    $this->freezeTime();

    $user = User::factory()->create(['hub_seen_at' => now()->subDay()]);

    app(MarkSeen::class)($user);

    expect($user->fresh()->hub_seen_at->isToday())->toBeTrue();
});
