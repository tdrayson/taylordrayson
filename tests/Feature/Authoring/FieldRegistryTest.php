<?php

use App\Enums\FieldType;
use App\Enums\MediaType;
use App\Fields\FieldRegistry;
use App\Models\Appearance;
use App\Models\Article;
use App\Models\Event;
use App\Models\Fuel;
use App\Models\Media;
use App\Models\Note;
use App\Models\Page;
use App\Models\Project;
use App\Models\Sleep;

it('resolves fields for every authorable type', function (string $model) {
    expect(FieldRegistry::for(new $model))->not->toBeEmpty();
})->with([Note::class, Page::class, Article::class, Project::class, Event::class, Fuel::class, Appearance::class]);

it('refuses a type nobody authors by hand', function () {
    // Sleep arrives from Apple Health; there is no form for it.
    expect(FieldRegistry::has(new Sleep))->toBeFalse();
    FieldRegistry::for(new Sleep);
})->throws(LogicException::class);

it('authors books but not the Trakt-sourced media types', function () {
    $book = Media::factory()->make(['type' => MediaType::Book]);
    $film = Media::factory()->make(['type' => MediaType::Film]);
    $episode = Media::factory()->make(['type' => MediaType::TvEpisode]);

    expect(FieldRegistry::has($book))->toBeTrue()
        ->and(FieldRegistry::has($film))->toBeFalse()
        ->and(FieldRegistry::has($episode))->toBeFalse();

    expect(collect(FieldRegistry::for($book))->pluck('name'))->toContain('meta.author');
});

it('gives the long-form types exactly one body field', function () {
    foreach ([Page::class, Article::class] as $model) {
        $bodies = array_filter(FieldRegistry::for(new $model), fn ($field): bool => $field->type->isBody());

        expect($bodies)->toHaveCount(1, "{$model} should declare exactly one body field");
    }
});

it('names only fields the model can actually be filled with', function (string $model) {
    // A field the model does not accept would silently do nothing on save.
    $fillable = (new $model)->getFillable();

    foreach (FieldRegistry::for(new $model) as $field) {
        // `tags` is a relationship the actions sync separately, not a column.
        if ($field->name === 'tags') {
            continue;
        }

        // Media names a Media Library collection, synced after the save by
        // SyncEntryMedia. It must NOT be fillable, or the list of uuids the
        // editor posts would be written into a column.
        if ($field->type->isMedia()) {
            expect($fillable)->not->toContain($field->name)
                ->and($field->collection)->not->toBeNull();

            continue;
        }

        // A dotted name addresses a key inside a JSON column, so the column
        // itself is what has to be fillable.
        $column = str_contains($field->name, '.')
            ? str($field->name)->before('.')->toString()
            : $field->name;

        expect($fillable)->toContain($column);
    }
})->with([Note::class, Page::class, Article::class, Project::class, Event::class, Fuel::class, Appearance::class, Media::class]);

it('marks the fields that hide behind Add field', function () {
    $fields = collect(FieldRegistry::for(new Page))->keyBy('name');

    expect($fields['title']->primary)->toBeTrue()
        ->and($fields['excerpt']->primary)->toBeFalse();
});

it('serialises a field for the client', function () {
    $status = collect(FieldRegistry::for(new Project))->firstWhere('name', 'status');

    expect($status->toArray())->toMatchArray([
        'name' => 'status',
        'label' => 'Status',
        'type' => FieldType::Select->value,
        'primary' => true,
        'isBody' => false,
    ])->and($status->toArray()['options'])->toHaveCount(4);
});
