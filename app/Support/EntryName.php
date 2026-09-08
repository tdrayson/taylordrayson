<?php

namespace App\Support;

use App\Models\Calorie;
use App\Models\Checkin;
use App\Models\Fuel;
use App\Models\Note;
use App\Models\Sleep;
use App\Timeline\TypeRegistry;
use Illuminate\Database\Eloquent\Model;

/**
 * What to call one of my own entries when another one points at it.
 *
 * A card title is written to be read under its own icon, on its own row, where
 * "at Starbucks" and "I slept for 7h 55m" say plenty. Put one after "Replied
 * to" and it stops being a name: "Replied to at Starbucks" is not a sentence.
 *
 * So the types whose title is a phrase rather than a name are called by what
 * they are and when they happened, and every other type keeps its title.
 */
final class EntryName
{
    /**
     * The types with no name of their own: the four whose card sets a
     * titleLabel, which is those cards saying their title does not stand up
     * alone, plus notes, which have no title at all. EntryNameTest holds this
     * list to the cards so the two cannot drift.
     *
     * The words are chosen for this sentence rather than taken from
     * TypeRegistry, whose nouns are written for archive pages: "my place from
     * Tuesday" is not what anybody calls a check-in.
     *
     * @var array<class-string, string>
     */
    private const NOUNS = [
        Note::class => 'note',
        Sleep::class => 'sleep',
        Calorie::class => 'food log',
        Checkin::class => 'check-in',
        Fuel::class => 'fill-up',
    ];

    /**
     * @param  bool  $possessive  "my note from 6 September" where the sentence
     *                            has no subject of its own ("Replied to ..."),
     *                            "a note from 6 September" where one is already
     *                            named ("Taylor Drayson mentioned this in ...").
     */
    public static function for(Model $model, bool $possessive = false): string
    {
        $named = self::NOUNS[$model::class] ?? null
            ? null
            : ($model->title ?? $model->name ?? null);

        if (filled($named)) {
            return (string) $named;
        }

        // Deliberately not the card title, which would mean building a card:
        // a card can carry a response of its own, and a pair of posts replying
        // to each other would build cards forever.
        $noun = self::NOUNS[$model::class] ?? self::nounFor($model);
        $determiner = $possessive ? 'my' : 'a';
        $date = self::dateOf($model);

        return $date === null ? "{$determiner} {$noun}" : "{$determiner} {$noun} from {$date}";
    }

    /**
     * What a type with neither a title nor a name is called, from the label its
     * archive already uses: a flight is "my flight from 28 August", which reads
     * better after "Replied to" than the route its card shows.
     */
    private static function nounFor(Model $model): string
    {
        foreach (TypeRegistry::all() as $definition) {
            if ($definition['model'] === $model::class) {
                return $definition['noun'];
            }
        }

        return 'entry';
    }

    /** Whether this entry is one of the types whose title needs no context. */
    public static function isUnnamed(Model $model): bool
    {
        return array_key_exists($model::class, self::NOUNS);
    }

    /**
     * The day it happened, without the year while it is still this one: a note
     * from last week does not need telling which year it was.
     */
    private static function dateOf(Model $model): ?string
    {
        $occurred = method_exists($model, 'occurredAtForDisplay')
            ? $model->occurredAtForDisplay()
            : null;

        if ($occurred === null) {
            return null;
        }

        return $occurred->format($occurred->isCurrentYear() ? 'j F' : 'j F Y');
    }
}
