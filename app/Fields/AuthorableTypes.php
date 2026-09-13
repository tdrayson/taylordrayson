<?php

namespace App\Fields;

use App\Actions\Appearances\CreateAppearance;
use App\Actions\Appearances\UpdateAppearance;
use App\Actions\Articles\CreateArticle;
use App\Actions\Articles\UpdateArticle;
use App\Actions\Books\CreateBook;
use App\Actions\Books\UpdateBook;
use App\Actions\Events\CreateEvent;
use App\Actions\Events\UpdateEvent;
use App\Actions\Flights\CreateFlight;
use App\Actions\Flights\UpdateFlight;
use App\Actions\Fuel\CreateFuel;
use App\Actions\Fuel\UpdateFuel;
use App\Actions\Notes\CreateNote;
use App\Actions\Notes\UpdateNote;
use App\Actions\Pages\CreatePage;
use App\Actions\Pages\UpdatePage;
use App\Actions\Projects\CreateProject;
use App\Actions\Projects\UpdateProject;
use App\Datasets\Datasets;
use App\Models\Appearance;
use App\Models\Article;
use App\Models\Book;
use App\Models\Event;
use App\Models\Flight;
use App\Models\Fuel;
use App\Models\Note;
use App\Models\Page;
use App\Models\Project;
use App\Support\TypeCatalogue;
use Illuminate\Database\Eloquent\Model;

/**
 * The types that can be written by hand, and what to reach for per type. One
 * table rather than a controller full of branches: the form, properties panel,
 * /new, /drafts and validation all read from it. Synced types are absent.
 *
 * Labels and icons are not here: they belong to App\Support\TypeCatalogue, which
 * every other surface draws the same type with.
 */
final class AuthorableTypes
{
    /**
     * @var array<string, array{model: class-string<Model>, create: class-string, update: class-string}>
     */
    private const TYPES = [
        'note' => ['model' => Note::class, 'create' => CreateNote::class, 'update' => UpdateNote::class],
        'article' => ['model' => Article::class, 'create' => CreateArticle::class, 'update' => UpdateArticle::class],
        'page' => ['model' => Page::class, 'create' => CreatePage::class, 'update' => UpdatePage::class],
        'project' => ['model' => Project::class, 'create' => CreateProject::class, 'update' => UpdateProject::class],
        'event' => ['model' => Event::class, 'create' => CreateEvent::class, 'update' => UpdateEvent::class],
        'book' => ['model' => Book::class, 'create' => CreateBook::class, 'update' => UpdateBook::class],
        'flight' => ['model' => Flight::class, 'create' => CreateFlight::class, 'update' => UpdateFlight::class],
        'fuel' => ['model' => Fuel::class, 'create' => CreateFuel::class, 'update' => UpdateFuel::class],
        'appearance' => ['model' => Appearance::class, 'create' => CreateAppearance::class, 'update' => UpdateAppearance::class],
    ];

    public static function has(string $type): bool
    {
        return isset(self::TYPES[$type]);
    }

    /**
     * @return array{model: class-string<Model>, create: class-string, update: class-string}|null
     */
    public static function get(string $type): ?array
    {
        return self::TYPES[$type] ?? null;
    }

    /**
     * The type slug for an existing model, or null when it is not hand-authored.
     */
    public static function forModel(Model $model): ?string
    {
        foreach (self::TYPES as $type => $definition) {
            if ($model instanceof $definition['model']) {
                return $type;
            }
        }

        return null;
    }

    /**
     * Everything offered on /new, quickest and most frequent first. The label and
     * glyph come from the catalogue, so a tile and its timeline entry cannot show
     * different marks.
     *
     * @return list<array{type: string, label: string, icon: string}>
     */
    public static function forPicker(): array
    {
        return array_values(array_map(
            fn (string $type): array => [
                'type' => $type,
                'label' => TypeCatalogue::for($type)->label,
                'icon' => TypeCatalogue::for($type)->icon,
            ],
            array_keys(self::TYPES),
        ));
    }

    /**
     * The types that can be a draft, and so the only ones /drafts lists.
     *
     * @return list<string>
     */
    public static function draftable(): array
    {
        return array_values(array_filter(
            array_keys(self::TYPES),
            fn (string $type): bool => $type === 'page' || (Datasets::for($type)?->draftable() ?? false),
        ));
    }
}
