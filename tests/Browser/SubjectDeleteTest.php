<?php

use App\Models\Subject;
use App\Models\User;

beforeEach(fn () => $this->actingAs(User::factory()->create()));

it('deletes the subject when Delete is clicked and confirmed', function () {
    $subject = Subject::factory()->person()->create(['name' => 'Clare', 'slug' => 'clare']);

    $page = visit($subject->url());
    // Stubs the native confirm() dialog so the click resolves synchronously,
    // then exercises the real Delete button rather than calling the route directly.
    $page->script('window.confirm = () => true;');
    $page->click('Delete')->wait(1);

    $page->assertPathIs('/life');

    expect(Subject::query()->find($subject->id))->toBeNull();
});
