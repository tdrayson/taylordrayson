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
use App\Models\Fuel;
use App\Models\Media;
use App\Models\Note;
use App\Models\Page;
use App\Models\Project;
use Illuminate\Database\Eloquent\Model;

/**
 * The types that can be written by hand, and what to reach for per type.
 *
 * One table rather than a controller full of branches: adding a type means
 * adding a row here, its fields class, and its actions. Everything else — the
 * form, the properties panel, /new, /drafts, validation — reads from this.
 *
 * Absent by design: activities, sleep, calories, check-ins, podcasts, flights
 * and the Trakt-sourced media types all arrive from a sync.
 */
final class AuthorableTypes
{
    /**
     * @var array<string, array{model: class-string<Model>, create: class-string, update: class-string, label: string, icon: string, draftable: bool}>
     */
    private const TYPES = [
        'note' => ['model' => Note::class, 'create' => CreateNote::class, 'update' => UpdateNote::class, 'label' => 'Note', 'icon' => 'note', 'draftable' => false],
        'article' => ['model' => Article::class, 'create' => CreateArticle::class, 'update' => UpdateArticle::class, 'label' => 'Article', 'icon' => 'article', 'draftable' => true],
        'page' => ['model' => Page::class, 'create' => CreatePage::class, 'update' => UpdatePage::class, 'label' => 'Page', 'icon' => 'page', 'draftable' => true],
        'project' => ['model' => Project::class, 'create' => CreateProject::class, 'update' => UpdateProject::class, 'label' => 'Project', 'icon' => 'project', 'draftable' => false],
        'event' => ['model' => Event::class, 'create' => CreateEvent::class, 'update' => UpdateEvent::class, 'label' => 'Event', 'icon' => 'event', 'draftable' => false],
        'book' => ['model' => Media::class, 'create' => CreateBook::class, 'update' => UpdateBook::class, 'label' => 'Book', 'icon' => 'book', 'draftable' => false],
        'fuel' => ['model' => Fuel::class, 'create' => CreateFuel::class, 'update' => UpdateFuel::class, 'label' => 'Fuel', 'icon' => 'fuel', 'draftable' => false],
        'appearance' => ['model' => Appearance::class, 'create' => CreateAppearance::class, 'update' => UpdateAppearance::class, 'label' => 'Appearance', 'icon' => 'appearance', 'draftable' => false],
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
     * Everything offered on /new, in the order the tiles appear: the quickest
     * and most frequent first, so the common case is the first thing thumbed.
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
