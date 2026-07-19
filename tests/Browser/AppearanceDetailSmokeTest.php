<?php

use App\Models\Appearance;

it('renders an appearance with both a show page and a Watch on YouTube link', function () {
    $appearance = Appearance::factory()->create([
        'title' => 'Smoke Appearance',
        'occurred_at' => '2024-05-01 12:00:00',
        'url' => 'https://example.com/show',
        'video_url' => 'https://www.youtube.com/watch?v=dQw4w9WgXcQ',
    ]);

    $page = visit($appearance->url());

    $page->assertNoJavaScriptErrors()
        ->assertSee('Smoke Appearance')
        ->assertSee('Show page')
        ->assertSee('Watch on YouTube');
});

it('still offers Watch on YouTube when the appearance has no show page', function () {
    $appearance = Appearance::factory()->create([
        'title' => 'Video Only Appearance',
        'occurred_at' => '2024-05-02 12:00:00',
        'url' => null,
        'video_url' => 'https://www.youtube.com/watch?v=dQw4w9WgXcQ',
    ]);

    $page = visit($appearance->url());

    $page->assertNoJavaScriptErrors()
        ->assertSee('Watch on YouTube')
        ->assertDontSee('Show page');
});
