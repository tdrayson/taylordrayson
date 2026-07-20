<?php

namespace App\Enums;

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
}
