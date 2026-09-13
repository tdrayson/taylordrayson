<?php

use App\Datasets\Datasets;
use App\Enums\EntryStatus;
use App\Models\Activity;
use App\Models\Article;
use App\Models\Concerns\HasStatus;
use App\Models\Food;
use App\Models\Note;
use App\Models\Page;
use App\Models\Scopes\ListedScope;
use App\Models\TimelineEntry;
use App\Models\User;
use Illuminate\Support\Facades\Schema;

it('gives every dataset model and Page the status trait and columns', function () {
    $models = [...array_map(fn ($dataset): string => $dataset->model(), array_values(Datasets::all())), Page::class];

    foreach ($models as $model) {
        $table = (new $model)->getTable();

        expect(class_uses_recursive($model))->toContain(HasStatus::class)
            ->and(Schema::hasColumns($table, ['status', 'password']))->toBeTrue("{$table} needs status and password");
    }

    expect(Schema::hasColumn('timeline_entries', 'status'))->toBeTrue();
});

it('defaults to published and never serialises the password', function () {
    $activity = Activity::factory()->create(['password' => 'secret']);

    expect($activity->fresh()->status)->toBe(EntryStatus::Published)
        ->and($activity->fresh()->toArray())->not->toHaveKey('password');
});

it('copies the status onto the spine row, which lists only published rows', function () {
    $listed = Activity::factory()->create();
    $unlisted = Activity::factory()->create(['status' => EntryStatus::Unlisted]);

    expect(TimelineEntry::query()->pluck('entry_id')->all())->toBe([$listed->id])
        ->and(TimelineEntry::withoutGlobalScope(ListedScope::class)->where('entry_id', $unlisted->id)->value('status'))
        ->toBe(EntryStatus::Unlisted);
});

it('keeps a draft off the spine and puts it back when it leaves draft', function () {
    $note = Note::factory()->create(['status' => EntryStatus::Draft]);

    expect($note->timelineEntry()->exists())->toBeFalse();

    $note->update(['status' => EntryStatus::Published]);

    expect($note->fresh()->timelineEntry()->exists())->toBeTrue();
});

it('gives a food day the status of its first row', function () {
    Food::factory()->create(['occurred_at' => '2026-06-01 08:00:00', 'status' => EntryStatus::Unlisted]);
    Food::factory()->create(['occurred_at' => '2026-06-01 12:00:00']);

    expect(TimelineEntry::withoutGlobalScope(ListedScope::class)->where('dataset', 'food')->sole()->status)
        ->toBe(EntryStatus::Unlisted);
});

it('scopes what a guest and the owner may open and search', function () {
    foreach (EntryStatus::cases() as $status) {
        Article::factory()->create(['status' => $status, 'slug' => $status->value]);
    }

    $owner = User::factory()->create();
    $slugs = fn ($query): array => $query->orderBy('slug')->pluck('slug')->all();

    expect($slugs(Article::query()->listed()))->toBe(['published'])
        ->and($slugs(Article::query()->viewableBy(null)))->toBe(['private', 'published', 'unlisted'])
        ->and($slugs(Article::query()->viewableBy($owner)))->toBe(['draft', 'private', 'published', 'unlisted'])
        ->and($slugs(Article::query()->searchable(null)))->toBe(['published'])
        ->and($slugs(Article::query()->searchable($owner)))->toBe(['private', 'published', 'unlisted']);
});

it('declares the draftable datasets', function () {
    $draftable = collect(Datasets::all())->filter(fn ($dataset): bool => $dataset->draftable())->keys()->all();

    expect($draftable)->toEqualCanonicalizing(['note', 'article', 'project', 'event', 'book', 'flight', 'fuel', 'appearance']);
});
