<?php

namespace App\Content;

use App\Contracts\DefinesContentSchema;
use App\Models\Activity;
use App\Models\Appearance;
use App\Models\Article;
use App\Models\Attachment;
use App\Models\Calorie;
use App\Models\Checkin;
use App\Models\Event;
use App\Models\Flight;
use App\Models\Fuel;
use App\Models\FuelStation;
use App\Models\Media;
use App\Models\Note;
use App\Models\Page;
use App\Models\Podcast;
use App\Models\Project;
use App\Models\Sleep;
use App\Models\Tag;
use App\Models\TimelineEntry;
use Illuminate\Database\Eloquent\Model;

/**
 * Single registry for flat-file content kinds and content-index tables.
 *
 * File tools (import/export/sync) use fileTypes(); the SQLite index uses
 * indexModels() which inserts support tables in FK-safe order.
 */
final class ContentTypes
{
    /**
     * Frontmatter `type` → model for entries stored under content/.
     *
     * @return array<string, class-string<Model>>
     */
    public static function fileTypes(): array
    {
        return [
            'note' => Note::class,
            'article' => Article::class,
            'project' => Project::class,
            'page' => Page::class,
            'appearance' => Appearance::class,
            'event' => Event::class,
            'checkin' => Checkin::class,
            'flight' => Flight::class,
            'fuel' => Fuel::class,
            'activity' => Activity::class,
            'sleep' => Sleep::class,
            'calorie' => Calorie::class,
            'podcast' => Podcast::class,
            'media' => Media::class,
        ];
    }

    /**
     * CSV import/export kinds (file types that are not page JSON).
     *
     * @return array<string, class-string<Model>>
     */
    public static function csvTypes(): array
    {
        return array_filter(
            self::fileTypes(),
            fn (string $class, string $type): bool => $type !== 'page',
            ARRAY_FILTER_USE_BOTH,
        );
    }

    /**
     * Models that own a table in the content SQLite index (migrate order).
     *
     * @return list<class-string<Model&DefinesContentSchema>>
     */
    public static function indexModels(): array
    {
        $models = [];

        foreach (self::fileTypes() as $type => $class) {
            if ($type === 'fuel') {
                $models[] = FuelStation::class;
            }

            $models[] = $class;
        }

        $models[] = TimelineEntry::class;
        $models[] = Tag::class;
        $models[] = Attachment::class;

        return $models;
    }

    /**
     * @return class-string<Model>|null
     */
    public static function modelFor(string $type): ?string
    {
        return self::fileTypes()[$type] ?? null;
    }

    public static function has(string $type): bool
    {
        return isset(self::fileTypes()[$type]);
    }
}
