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
use App\Support\ShowTitle;
use App\Support\Text;
use App\Support\Units;
use Illuminate\Database\Eloquent\Model;

/**
 * The meta description for a single entry page, written as a sentence.
 *
 * Where the source gave us words of its own (a Strava description, a check-in
 * note, an episode topic), those words are the description: they are always
 * better than anything generated from the numbers. Where it did not, the
 * description is written from the entry's own fields.
 *
 * Every line here is a sentence somebody would say out loud. Naming the same
 * thing the title named is fine and often unavoidable; opening on the same
 * words is not, which is why these lead with the verb or the context rather
 * than with the subject the title already gave.
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
            $model instanceof Sleep => self::sleep($card),
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

    /**
     * The card's own sentence, which already opens on bed and waking rather
     * than on the duration the title carries.
     */
    private static function sleep(CardData $card): string
    {
        return (string) $card->subtitle;
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

        if ($model->venue_name === null) {
            return '';
        }

        $category = $model->category ? ", a {$model->category}" : '';
        $city = $model->city ? " in {$model->city}" : '';

        return "I checked in at {$model->venue_name}{$category}{$city}.";
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
            ? sprintf(' It was %s miles%s.', number_format(Distance::miles($model->distance)), $cabin)
            : ($cabin === '' ? '' : ' I flew'.$cabin.'.');

        return "I flew from {$leg}{$airline}.{$distance}";
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
     * What was watched or read, said the way it would be said aloud, with what
     * the title could not hold: a film's runtime and genre, an episode's place
     * in its run, a book's author.
     */
    private static function media(Media $model): string
    {
        $rating = $model->rating ? " I rated it {$model->rating} out of 10." : '';

        $sentence = match ($model->type) {
            MediaType::Film => sprintf('I watched %s%s', $model->title, self::filmShape($model)),
            MediaType::TvEpisode => self::episodeSentence($model),
            MediaType::Book => sprintf(
                'I read %s%s.',
                $model->title,
                $model->meta->author ? " by {$model->meta->author}" : '',
            ),
        };

        return trim($sentence.$rating);
    }

    /**
     * ", a comedy romance from 2026. It was 1 hour 42 minutes long.", from
     * whichever parts we hold.
     *
     * The runtime is a clause of its own rather than a modifier: spelled out is
     * how the rest of the site writes a duration in prose, and "a 1 hour 42
     * minutes comedy romance" is not a thing anyone says. Left exact, because
     * every other number on the site is.
     */
    private static function filmShape(Media $model): string
    {
        $genres = array_slice((array) data_get($model->meta->tmdb, 'genres', []), 0, 2);
        $noun = $genres === [] ? 'film' : mb_strtolower(implode(' ', $genres));
        $article = in_array(mb_substr($noun, 0, 1), ['a', 'e', 'i', 'o', 'u'], true) ? 'an' : 'a';

        $year = $model->meta->year ? " from {$model->meta->year}" : '';
        $runtime = $model->meta->runtime
            ? ' It was '.Units::spokenDuration((int) $model->meta->runtime * 60).' long.'
            : '';

        return ", {$article} {$noun}{$year}.{$runtime}";
    }

    /**
     * The episode said as it would be said out loud: "season 2, episode 17 of
     * Georgie & Mandy's First Marriage". "S02E17" is shorthand for a filename.
     */
    private static function episodeSentence(Media $model): string
    {
        $show = ShowTitle::for($model);
        $where = $model->meta->season !== null && $model->meta->episode !== null
            ? sprintf('season %d, episode %d', $model->meta->season, $model->meta->episode)
            : null;

        $subject = match (true) {
            $where !== null && $show !== null => "{$where} of {$show}",
            $show !== null => "an episode of {$show}",
            $where !== null => $where,
            default => $model->title,
        };

        return "I watched {$subject}.";
    }

    /** The day's total broken into its macros, as its own sentence. */
    private static function calorie(Calorie $model): string
    {
        $totals = app(DayFoodTotals::class)->for($model->occurred_at->toDateString());

        $macros = Text::sentenceList(array_values(array_filter([
            $totals['protein'] ? round($totals['protein']).'g of protein' : null,
            $totals['carbs'] ? round($totals['carbs']).'g of carbs' : null,
            $totals['fat'] ? round($totals['fat']).'g of fat' : null,
        ])));

        if ($macros === '') {
            return '';
        }

        return sprintf(
            "That day's food came to %s calories, with %s.",
            number_format($totals['calories']),
            $macros,
        );
    }

    /**
     * The fill as a sentence. Named by brand rather than by forecourt: "a BP
     * garage" is what anyone would call it, where "Beddington Lane Service
     * Station" is a name only its own paperwork uses. Both are absent on the
     * 81 older rows, which say the amount and the cost and stop there.
     */
    private static function fuel(Fuel $model): string
    {
        $garage = match (true) {
            (bool) $model->brand => 'a '.$model->brand.' garage',
            (bool) $model->station_name => $model->station_name,
            default => null,
        };

        $where = trim(($garage !== null ? " at {$garage}" : '').($model->city ? " in {$model->city}" : ''));
        $lead = sprintf('I filled my car with %sL', number_format((float) $model->litres, 2));
        $lead = $where === '' ? $lead : "{$lead} {$where}";

        $cost = number_format((float) $model->cost, 2);

        // One subject, two verbs: the price and the total are both things the
        // fuel did, so neither needs a connective to carry it.
        return $model->price_per_litre
            ? sprintf('%s. Fuel was %s/L and cost £%s.', $lead, Units::pencePerLitre($model->price_per_litre), $cost)
            : sprintf('%s. It cost £%s.', $lead, $cost);
    }

    private static function event(Event $model): string
    {
        $where = collect([$model->venue_name, $model->city])->filter()->implode(', ');
        $note = self::source($model->description);

        if ($note !== null) {
            return $where === '' ? $note : self::join($note, "at {$where}").'.';
        }

        return $where === '' ? '' : "I went to {$model->name} at {$where}.";
    }

    private static function appearance(Appearance $model): string
    {
        $note = self::source($model->description);
        $show = $model->show_name ? "I appeared on {$model->show_name}." : '';

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
