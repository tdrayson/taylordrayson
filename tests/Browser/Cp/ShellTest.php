<?php

use App\Models\User;

it('renders the public navigation when logged out', function () {
    $page = visit('/');

    $page->assertNoJavaScriptErrors()
        ->assertSee('Timeline')
        ->assertSee('Now')
        ->assertDontSee('Sign out');
});

it('renders the collections navigation and account menu in the control panel', function () {
    $this->actingAs(User::factory()->create(['name' => 'Taylor Drayson']));

    $page = visit('/cp');

    $page->assertNoJavaScriptErrors()
        ->assertSee('Flights')
        ->assertSee('Reference')
        ->assertSee('Taylor Drayson');
});
