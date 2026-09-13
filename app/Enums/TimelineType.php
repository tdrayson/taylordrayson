<?php

namespace App\Enums;

use App\Datasets\Dataset;
use App\Datasets\Datasets;
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
    case Food = 'food';
    case Media = 'media';
    case Event = 'event';
    case Appearance = 'appearance';
    case ThisWeekWith = 'this-week-with';
    case Flight = 'flight';
    case Place = 'place';
    case Fuel = 'fuel';
    case Project = 'project';
    case Article = 'article';
    case Note = 'note';

    /**
     * The --color-* token key for this type. Matches the case value.
     */
    public function accent(): string
    {
        return $this->value;
    }

    /**
     * The dataset that describes this type.
     */
    public function dataset(): Dataset
    {
        return Datasets::for($this) ?? throw new LogicException("No dataset declared for {$this->value}.");
    }
}
