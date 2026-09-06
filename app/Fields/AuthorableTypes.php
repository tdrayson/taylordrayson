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
use App\Enums\MediaType;
use App\Models\Appearance;
use App\Models\Article;
use App\Models\Event;
use App\Models\Flight;
use App\Models\Fuel;
use App\Models\Media;
use App\Models\Note;
use App\Models\Page;
use App\Models\Project;
use Illuminate\Database\Eloquent\Model;

/**
 * The types that can be written by hand, and what to reach for per type. One
 * table rather than a controller full of branches: the form, properties panel,
 * /new, /drafts and validation all read from it. Synced types are absent.
 */
final class AuthorableTypes
{
    /**
     * @var array<string, array{model: class-string<Model>, create: class-string, update: class-string, label: string, icon: string, draftable: bool}>
     */
    private const TYPES = [
        'note' => ['model' => Note::class, 'create' => CreateNote::class, 'update' => UpdateNote::class, 'label' => 'Note', 'icon' => 'StickyNote02Icon', 'draftable' => false],
        'article' => ['model' => Article::class, 'create' => CreateArticle::class, 'update' => UpdateArticle::class, 'label' => 'Article', 'icon' => 'File01Icon', 'draftable' => true],
        'page' => ['model' => Page::class, 'create' => CreatePage::class, 'update' => UpdatePage::class, 'label' => 'Page', 'icon' => 'File02Icon', 'draftable' => true],
        'project' => ['model' => Project::class, 'create' => CreateProject::class, 'update' => UpdateProject::class, 'label' => 'Project', 'icon' => 'RocketIcon', 'draftable' => false],
        'event' => ['model' => Event::class, 'create' => CreateEvent::class, 'update' => UpdateEvent::class, 'label' => 'Event', 'icon' => 'Ticket01Icon', 'draftable' => false],
        'book' => ['model' => Media::class, 'create' => CreateBook::class, 'update' => UpdateBook::class, 'label' => 'Book', 'icon' => 'BookOpen01Icon', 'draftable' => false],
        'flight' => ['model' => Flight::class, 'create' => CreateFlight::class, 'update' => UpdateFlight::class, 'label' => 'Flight', 'icon' => 'AirplaneTakeOff01Icon', 'draftable' => false],
        'fuel' => ['model' => Fuel::class, 'create' => CreateFuel::class, 'update' => UpdateFuel::class, 'label' => 'Fuel', 'icon' => 'PetrolPumpIcon', 'draftable' => false],
        'appearance' => ['model' => Appearance::class, 'create' => CreateAppearance::class, 'update' => UpdateAppearance::class, 'label' => 'Appearance', 'icon' => 'Mic01Icon', 'draftable' => false],
    ];

    public static function has(string $type): bool
    {
        return isset(self::TYPES[$type]);
    }

    /**
     * @return array{model: class-string<Model>, create: class-string, update: class-string, label: string, icon: string, draftable: bool}|null
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
            if (! $model instanceof $definition['model']) {
                continue;
            }

            // Media covers films and episodes too, and only books are authored.
            if ($model instanceof Media) {
                return $model->getAttribute('type') === MediaType::Book ? 'book' : null;
            }

            return $type;
        }

        return null;
    }

    /**
     * Everything offered on /new, quickest and most frequent first. Icon names
     * match resources/js/entryTypes.js so a tile and its timeline entry never
     * show different marks.
     *
     * @return list<array{type: string, label: string, icon: string}>
     */
    public static function forPicker(): array
    {
        return array_values(array_map(
            fn (string $type): array => [
                'type' => $type,
                'label' => self::TYPES[$type]['label'],
                'icon' => self::TYPES[$type]['icon'],
            ],
            array_keys(self::TYPES),
        ));
    }

    /**
     * The types with a publication gate, which are the only ones that can be a
     * draft and so the only ones /drafts has anything to list.
     *
     * @return list<string>
     */
    public static function draftable(): array
    {
        return array_keys(array_filter(self::TYPES, fn (array $definition): bool => $definition['draftable']));
    }
}
