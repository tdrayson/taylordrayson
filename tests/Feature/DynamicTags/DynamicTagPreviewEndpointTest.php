<?php

use App\DynamicTags\DynamicTag;
use App\DynamicTags\DynamicTagRegistry;
use App\Enums\Placement;
use App\Models\Note;
use App\Models\User;

it('is closed to guests', function () {
    $this->getJson('/dynamic-tags/preview?name=entries.count')->assertUnauthorized();
});

it('resolves a tag against an arbitrary option set, not just its defaults', function () {
    Note::factory()->count(3)->create();

    $response = $this->actingAs(User::factory()->create())
        ->getJson('/dynamic-tags/preview?name=entries.count&options[type]=note&placement=inline');

    $response->assertOk()->assertJsonPath('data.preview', '3');
});

it('rejects a tag name the registry does not know', function () {
    $this->actingAs(User::factory()->create())
        ->getJson('/dynamic-tags/preview?name=entries.nope&placement=inline')
        ->assertInvalid('name');
});

it('rejects an option key the tag does not declare', function () {
    $this->actingAs(User::factory()->create())
        ->getJson('/dynamic-tags/preview?name=entries.count&options[bogus]=1&placement=inline')
        ->assertInvalid('options.bogus');
});

it('rejects a value outside the option\'s declared choices', function () {
    $this->actingAs(User::factory()->create())
        ->getJson('/dynamic-tags/preview?name=entries.count&options[type]=bogus&placement=inline')
        ->assertInvalid('options.type');
});

it('accepts a bare four-digit year as a period', function () {
    Note::factory()->create(['occurred_at' => '2019-06-01 12:00:00']);
    Note::factory()->create(['occurred_at' => '2023-06-01 12:00:00']);

    $response = $this->actingAs(User::factory()->create())
        ->getJson('/dynamic-tags/preview?name=entries.count&options[period]=2019&placement=inline');

    $response->assertOk()->assertJsonPath('data.preview', '1');
});

it('rejects a placement outside the enum', function () {
    $this->actingAs(User::factory()->create())
        ->getJson('/dynamic-tags/preview?name=entries.count&placement=bogus')
        ->assertInvalid('placement');
});

it('requires a placement', function () {
    $this->actingAs(User::factory()->create())
        ->getJson('/dynamic-tags/preview?name=entries.count')
        ->assertInvalid('placement');
});

it('previews the resolved href for href and image placements, not the display text', function () {
    $tag = new class extends DynamicTag
    {
        public function name(): string
        {
            return 'test.diverges';
        }

        public function label(): string
        {
            return 'Diverging test tag';
        }

        public function group(): string
        {
            return 'Test';
        }

        /** @return list<Placement> */
        public function supports(): array
        {
            return [Placement::Inline, Placement::Href, Placement::Image];
        }

        public function resolve(array $options): mixed
        {
            return 'value';
        }

        public function format(mixed $value, array $options): string
        {
            return 'Display text';
        }

        public function href(mixed $value, array $options): string
        {
            return 'https://example.com/target';
        }
    };

    app()->instance('test.diverges', $tag);
    app()->tag(['test.diverges'], DynamicTagRegistry::CONTAINER_TAG);

    $user = User::factory()->create();

    $this->actingAs($user)
        ->getJson('/dynamic-tags/preview?name=test.diverges&placement=inline')
        ->assertOk()->assertJsonPath('data.preview', 'Display text');

    $this->actingAs($user)
        ->getJson('/dynamic-tags/preview?name=test.diverges&placement=href')
        ->assertOk()->assertJsonPath('data.preview', 'https://example.com/target');

    $this->actingAs($user)
        ->getJson('/dynamic-tags/preview?name=test.diverges&placement=image')
        ->assertOk()->assertJsonPath('data.preview', 'https://example.com/target');
});
