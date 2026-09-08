<?php

namespace App\Enums;

use App\Models\Activity;
use App\Models\Appearance;
use App\Models\Article;
use App\Models\Calorie;
use App\Models\Checkin;
use App\Models\Concerns\Timelineable;
use App\Models\Event;
use App\Models\Flight;
use App\Models\Fuel;
use App\Models\Media;
use App\Models\Note;
use App\Models\Podcast;
use App\Models\Project;
use App\Models\Sleep;
use LogicException;

/**
 * The single source of truth for the 13 timeline card `type` keys. Backed
 * values are the exact strings the DB, `CardData::toArray()`, and the
 * frontend `entryTypes.js` map have always used, so introducing this enum
 * keeps every serialised payload byte-identical.
 */
enum TimelineType: string
{
    case Activity = 'activity';
    case Sleep = 'sleep';
    case Calorie = 'calorie';
    case Media = 'media';
    case Event = 'event';
    case Appearance = 'appearance';
    case Podcast = 'podcast';
    case Flight = 'flight';
    case Checkin = 'checkin';
    case Fuel = 'fuel';
    case Project = 'project';
    case Article = 'article';
    case Note = 'note';

    /**
     * The case a Timelineable model belongs to. Presenters that need only a
     * type (an accent, an icon) resolve it here rather than building a card.
     */
    public static function for(Timelineable $model): self
    {
        return match (true) {
            $model instanceof Activity => self::Activity,
            $model instanceof Sleep => self::Sleep,
            $model instanceof Calorie => self::Calorie,
            $model instanceof Media => self::Media,
            $model instanceof Event => self::Event,
            $model instanceof Appearance => self::Appearance,
            $model instanceof Podcast => self::Podcast,
            $model instanceof Flight => self::Flight,
            $model instanceof Checkin => self::Checkin,
            $model instanceof Fuel => self::Fuel,
            $model instanceof Project => self::Project,
            $model instanceof Article => self::Article,
            $model instanceof Note => self::Note,
            default => throw new LogicException('No timeline type registered for '.$model::class),
        };
    }

    /**
     * The --color-* token key for this type. Matches the case value everywhere
     * except food, where the type is named for the row and the colour for the
     * subject.
     */
    public function accent(): string
    {
        return $this === self::Calorie ? 'food' : $this->value;
    }
}
