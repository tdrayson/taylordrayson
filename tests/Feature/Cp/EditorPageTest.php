<?php

use App\Models\Article;
use App\Models\Flight;
use App\Models\User;
use Inertia\Testing\AssertableInertia;

beforeEach(fn () => $this->actingAs(User::factory()->create()));

it('renders the article editor page with the layout blueprint', function () {
    $article = Article::factory()->create();

    $this->get("/cp/articles/{$article->id}/edit")
        ->assertSuccessful()
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->component('Cp/Resource/Form')
            ->where('resource.slug', 'articles')
            ->has('resource.layout.sections')
            ->where('resource.layout.tabs', ['Main'])
        );
});

it('places draft and occurred_at in the article sidebar', function () {
    $article = Article::factory()->create();

    $this->get("/cp/articles/{$article->id}/edit")
        ->assertSuccessful()
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->where('resource.layout.sections', function ($sections): bool {
                $sidebar = collect($sections)->firstWhere('area', 'sidebar');

                expect($sidebar)->not->toBeNull();

                $keys = collect($sidebar['fields'])->pluck('key');
                expect($keys)->toContain('draft');
                expect($keys)->toContain('occurred_at');

                return true;
            })
        );
});

it('exposes the flight scheduling group and relation fields on the editor page', function () {
    $flight = Flight::factory()->create();

    $this->get("/cp/flights/{$flight->id}/edit")
        ->assertSuccessful()
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->component('Cp/Resource/Form')
            ->where('resource.layout.sections', function ($sections): bool {
                $fields = collect($sections)->flatMap(fn ($section) => collect($section['fields']));

                $scheduling = $fields->firstWhere('key', 'scheduling');
                expect($scheduling)->not->toBeNull();
                expect($scheduling['type'])->toBe('group');

                $airline = $fields->firstWhere('key', 'airline_icao');
                expect($airline)->not->toBeNull();
                expect($airline['type'])->toBe('relation');

                return true;
            })
        );
});

it('seeds composite values for the flight edit form', function () {
    $flight = Flight::factory()->create(['meta' => ['departed_scheduled' => '2026-06-03T17:20', 'gate' => 'B12']]);

    $this->get("/cp/flights/{$flight->id}/edit")
        ->assertSuccessful()
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->where('values.scheduling.departed_scheduled', '2026-06-03T17:20')
            ->where('values.meta_extra', ['gate' => 'B12'])
        );
});
