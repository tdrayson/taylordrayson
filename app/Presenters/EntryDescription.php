<?php

namespace App\Presenters;

use App\Data\CardData;
use App\Enums\MediaType;
use App\Models\Activity;
use App\Models\Appearance;
use App\Models\Article;
use App\Models\Calorie;
use App\Models\Checkin;
use App\Models\Event;
use App\Models\Flight;
use App\Models\Fuel;
use App\Models\Media;
use App\Models\Note;
use App\Models\Podcast;
use App\Models\Project;
use App\Models\Sleep;
use App\Queries\DayFoodTotals;
use App\Support\Distance;
use App\Support\PortableText;
use App\Support\Text;
use Illuminate\Database\Eloquent\Model;

/**
 * The meta description for a single entry page, written as a sentence.
 *
 * Two rules decide what it says. Where the source gave us words of its own (a
 * Strava description, a check-in note, an episode topic), those words are the
 * description: they are always better than anything generated from the numbers.
 * Where it did not, the description carries the facts the title had no room
 * for, and never restates the title itself.
 *
 * Nothing here says the date. Every entry title ends with one, and a search
 * result printing it twice wastes the only two lines there are.
 *
 * Kept as one class rather than one per type, the way the cards are split: a
 * description is a single sentence, so thirteen files would each hold about
 * four lines.
 */
final class EntryDescription
{
    /**
     * Google truncates around 160 characters; the cap leaves room for the tail
     * to be cut on a word rather than mid-number.
     */
    private const LIMIT = 200;

    /**
     * A one-sentence description of an entry, falling back to the card's own
     * title and subtitle for a type with nothing better to say.
     */
    public static function for(Model $model, CardData $card): string
    {
        $description = match (true) {
            $model instanceof Sleep => self::sleep($model),
            $model instanceof Activity => self::activity($model, $card),
            $model instanceof Checkin => self::checkin($model),
            $model instanceof Flight => self::flight($model),
            $model instanceof Media => self::media($model),
            $model instanceof Calorie => self::calorie($model),
            $model instanceof Fuel => self::fuel($model),
            $model instanceof Event => self::event($model),
            $model instanceof Appearance => self::appearance($model),
            $model instanceof Podcast => self::podcast($model),
            $model instanceof Article => self::articleText($model),
            $model instanceof Note => self::note($model),
            $model instanceof Project => self::project($model),
            default => self::fallback($card),
        };

        $description = trim(preg_replace('/\s+/u', ' ', $description) ?? '');

        return Text::excerpt($description, self::LIMIT) ?: self::fallback($card);
    }

    /** The title already says how long I slept, so this spends itself on when. */
    private static function sleep(Sleep $model): string
    {
        $window = sprintf('From %s to %s', $model->bedtime->format('g:ia'), $model->wake_time->format('g:ia'));

        return $model->score
            ? "{$window}, with a sleep score of {$model->score}."
            : "{$window}.";
    }

    /**
     * What I wrote on Strava where I wrote anything, and otherwise the card's
     * own sentence, which already reads as one ("I walked 3 mi in 59m and
     * burned 303 kcal.").
     */
    private static function activity(Activity $model, CardData $card): string
    {
        return self::source($model->description) ?? (string) $card->subtitle;
    }

    /**
     * The note I left, placed at the venue it was left at, because the title
     * carries the venue alone and the town is worth having in a search result.
     */
    private static function checkin(Checkin $model): string
    {
        $place = collect([$model->venue_name, $model->city])->filter()->implode(', ');
        $note = self::source($model->description);

        if ($note !== null) {
            return $place === '' ? $note : self::join($note, "at {$place}").'.';
        }

        $category = $model->category ? " ({$model->category})" : '';

        return $place === '' ? '' : "I checked in at {$place}{$category}.";
    }

    /**
     * Airports named rather than coded, which is the whole reason this line
     * exists: "KRK to LGW" tells a reader nothing they can picture.
     */
    private static function flight(Flight $model): string
    {
        $leg = sprintf(
            '%s to %s',
            self::airport($model, 'origin') ?? $model->origin_iata,
            self::airport($model, 'destination') ?? $model->destination_iata,
        );

        $airline = $model->relationLoaded('airline') && $model->airline ? " with {$model->airline->name}" : '';
        $cabin = $model->cabin_class ? " in {$model->cabin_class->value}" : '';
        $distance = $model->distance
            ? sprintf(', %s miles%s', number_format(Distance::miles($model->distance)), $cabin)
            : $cabin;

        return "{$leg}{$airline}{$distance}.";
    }

    /**
     * An airport's full name, which is the only field that reads correctly:
     * its city is the parish the runway sits in ("Balice" for Kraków) and
     * cannot tell Gatwick, Heathrow and Stansted apart, all three being
     * "London". Null when the relation was not loaded for this caller.
     */
    private static function airport(Flight $model, string $relation): ?string
    {
        if (! $model->relationLoaded($relation)) {
            return null;
        }

        $airport = $model->{$relation};

        return $airport?->name ?? $airport?->city;
    }

    /**
     * What the title could not hold: the shape of the thing rather than its
     * name. A film has a runtime and genres, an episode its place in the run,
     * a book its author.
     */
    private static function media(Media $model): string
    {
        $rating = $model->rating ? " I rated it {$model->rating} out of 10." : '';

        $subject = match ($model->type) {
            MediaType::Film => self::filmShape($model),
            MediaType::TvEpisode => self::episodeShape($model),
            MediaType::Book => $model->meta->author ? "By {$model->meta->author}." : '',
        };

        return trim($subject.$rating);
    }

    /** "A 102-minute comedy romance from 2026", from whichever parts we hold. */
    private static function filmShape(Media $model): string
    {
        $genres = array_slice((array) data_get($model->meta->tmdb, 'genres', []), 0, 2);
        $runtime = $model->meta->runtime ? "{$model->meta->runtime}-minute" : null;
        $noun = $genres === [] ? 'film' : mb_strtolower(implode(' ', $genres));

        $shape = trim(($runtime ?? '').' '.$noun);
        $year = $model->meta->year ? " from {$model->meta->year}" : '';

        return ucfirst(self::indefiniteArticle($shape))." {$shape}{$year}.";
    }

    /** "Season 2, episode 17, 19 minutes", the episode's place in its show. */
    private static function episodeShape(Media $model): string
    {
        $where = $model->meta->season !== null && $model->meta->episode !== null
            ? sprintf('Season %d, episode %d', $model->meta->season, $model->meta->episode)
            : null;

        $runtime = $model->meta->runtime ? "{$model->meta->runtime} minutes" : null;
        $parts = array_values(array_filter([$where, $runtime]));

        return $parts === [] ? '' : implode(', ', $parts).'.';
    }

    /** "a" or "an", for the shape sentence a film's description opens with. */
    private static function indefiniteArticle(string $shape): string
    {
        return in_array(mb_substr($shape, 0, 1), ['a', 'e', 'i', 'o', 'u'], true) ? 'an' : 'a';
    }

    /** The macros, since the title already carries the calorie count. */
    private static function calorie(Calorie $model): string
    {
        $totals = app(DayFoodTotals::class)->for($model->occurred_at->toDateString());

        $macros = Text::sentenceList(array_values(array_filter([
            $totals['protein'] ? round($totals['protein']).'g of protein' : null,
            $totals['carbs'] ? round($totals['carbs']).'g of carbs' : null,
            $totals['fat'] ? round($totals['fat']).'g of fat' : null,
        ])));

        return $macros === '' ? '' : "{$macros} across the day.";
    }

    /** Litres and the pump price, where the title carries the total spend. */
    private static function fuel(Fuel $model): string
    {
        $rate = $model->price_per_litre
            ? sprintf(' at £%s a litre', number_format((float) $model->price_per_litre, 3))
            : '';

        $where = $model->city ? ", in {$model->city}" : '';

        return sprintf('%s litres%s%s.', number_format((float) $model->litres, 2), $rate, $where);
    }

    private static function event(Event $model): string
    {
        $where = collect([$model->venue_name, $model->city])->filter()->implode(', ');
        $note = self::source($model->description);

        if ($note !== null) {
            return $where === '' ? $note : self::join($note, "at {$where}").'.';
        }

        return $where === '' ? '' : "At {$where}.";
    }

    private static function appearance(Appearance $model): string
    {
        $note = self::source($model->description);
        $show = $model->show_name ? "On {$model->show_name}." : '';

        return $note ?? $show;
    }

    /** The episode's own topic line, which is the best summary anyone wrote. */
    private static function podcast(Podcast $model): string
    {
        return (string) self::source($model->topic);
    }

    /** The hand-written excerpt where there is one, else the opening prose. */
    private static function articleText(Article $model): string
    {
        return Text::excerpt($model->excerpt, self::LIMIT)
            ?: (string) Text::excerpt(PortableText::plainText($model->content), self::LIMIT);
    }

    private static function note(Note $model): string
    {
        return (string) Text::excerpt(PortableText::plainText($model->content), self::LIMIT);
    }

    private static function project(Project $model): string
    {
        return Text::excerpt($model->description, self::LIMIT)
            ?: sprintf('%s, a project of mine.', $model->title);
    }

    /**
     * Source text, or null when the field is absent or empty.
     *
     * Flattened to a single line: a Strava description or a show's notes can
     * run to paragraphs, and a meta description is one line whatever it holds.
     */
    private static function source(?string $value): ?string
    {
        $value = trim(preg_replace('/\s+/u', ' ', (string) $value) ?? '');

        return $value === '' ? null : $value;
    }

    /**
     * Attach a trailing clause to text somebody else wrote, without editing it.
     * A note that already ends in a full stop gets the clause as its own
     * sentence rather than "Lovely coffee. at Costa".
     */
    private static function join(string $text, string $clause): string
    {
        return preg_match('/[.!?…]$/u', $text) === 1
            ? $text.' '.ucfirst($clause)
            : $text.' '.$clause;
    }

    /**
     * Card title and subtitle stitched into a line. Reached only by a type with
     * no case above, or one whose own fields turned out empty, so a new type
     * reads sensibly before anyone writes its sentence.
     */
    private static function fallback(CardData $card): string
    {
        return trim(sprintf('%s%s', $card->title, $card->subtitle ? ": {$card->subtitle}" : ''));
    }
}
