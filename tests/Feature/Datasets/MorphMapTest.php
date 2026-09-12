<?php

use App\Datasets\Datasets;
use App\Models\Activity;
use App\Models\Concerns\HasAttachments;
use App\Models\Concerns\HasTags;
use App\Models\Concerns\HasTimelineEntry;
use App\Models\TimelineEntry;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

it('keys every dataset model by its dataset key', function () {
    foreach (Datasets::all() as $key => $dataset) {
        expect(Relation::getMorphedModel($key))->toBe($dataset->model())
            ->and((new ($dataset->model()))->getMorphClass())->toBe($key);
    }
});

it('stores the dataset key, not a class path, on a new timeline entry', function () {
    $activity = Activity::factory()->create();

    expect(TimelineEntry::query()->sole()->dataset)->toBe('activity')
        ->and(TimelineEntry::query()->sole()->entry->is($activity))->toBeTrue();
});

it('maps every model found in a morph column', function () {
    $columns = ['timeline_entries' => 'dataset', 'attachments' => 'model_type', 'taggables' => 'taggable_type', 'oauth_clients' => 'owner_type'];

    foreach ($columns as $table => $column) {
        foreach (DB::table($table)->whereNotNull($column)->distinct()->pluck($column) as $value) {
            expect(Relation::getMorphedModel($value))->not->toBeNull("{$table}.{$column} holds {$value}, which the morph map does not know.");
        }
    }
});

it('names a morph alias for every model that carries attachments, tags, or a timeline entry', function () {
    $models = collect(glob(app_path('Models/*.php')))
        ->map(fn (string $path): string => 'App\\Models\\'.basename($path, '.php'))
        ->filter(fn (string $class): bool => in_array(HasAttachments::class, class_uses_recursive($class), true)
            || in_array(HasTimelineEntry::class, class_uses_recursive($class), true)
            || in_array(HasTags::class, class_uses_recursive($class), true));

    foreach ($models as $class) {
        expect(array_search($class, Datasets::morphMap(), true))->not->toBeFalse("{$class} can be a morph target but has no alias.");
    }
});

it('rewrites stored class paths to dataset keys and back', function () {
    $migration = require database_path('migrations/2026_09_13_000001_key_morph_columns_by_dataset.php');
    $migration->down();

    DB::table('timeline_entries')->insert(['timelineable_type' => 'App\Models\Flight', 'timelineable_id' => 1, 'occurred_at' => now(), 'url_slug' => 'x']);
    DB::table('attachments')->insert(['model_type' => 'App\Models\Series', 'model_id' => 1, 'uuid' => (string) Str::uuid(), 'collection_name' => 'cover', 'name' => 'x', 'file_name' => 'x.webp', 'disk' => 'public', 'size' => 1, 'manipulations' => '[]', 'custom_properties' => '[]', 'generated_conversions' => '[]', 'responsive_images' => '[]']);

    $migration->up();

    expect(DB::table('timeline_entries')->value('dataset'))->toBe('flight')
        ->and(DB::table('attachments')->value('model_type'))->toBe('series');
});
