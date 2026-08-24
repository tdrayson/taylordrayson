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
use App\Support\Distance;
use App\Support\PortableText;
use App\Support\ShowTitle;
use App\Support\Text;
use Illuminate\Database\Eloquent\Model;

/**
 * The meta description for a single entry page, written as a sentence.
 *
 * A card title alone makes a useless description: "9h 21m sleep" repeated under
 * a heading that already says it tells a reader nothing and gives a search
 * engine nothing to rank. Each type instead spends its own fields on a line
 * that reads on its own, away from the page.
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
        $date = $card->occurredAt->format('l j F Y');

        $description = match (true) {
            $model instanceof Sleep => self::sleep($model, $date),
            $model instanceof Activity => self::activity($model, $card, $date),
            $model instanceof Checkin => self::checkin($model, $date),
            $model instanceof Flight => self::flight($model, $card, $date),
            $model instanceof Media => self::media($model, $date),
            $model instanceof Calorie => self::calorie($card, $date),
            $model instanceof Fuel => self::fuel($model, $date),
            $model instanceof Event => self::event($model, $date),
            $model instanceof Appearance => self::appearance($model, $date),
            $model instanceof Podcast => self::podcast($model, $date),
            $model instanceof Article => self::article($model),
            $model instanceof Note => self::note($model),
            $model instanceof Project => self::project($model),
            default => self::fallback($card, $date),
        };

        return Text::excerpt($description, self::LIMIT) ?: self::fallback($card, $date);
    }

    private static function sleep(Sleep $model, string $date): string
    {
        $window = $model->bedtime->format('g:ia').' to '.$model->wake_time->format('g:ia');
        $score = $model->score ? ", scoring {$model->score}" : '';

        return sprintf('I slept %s on %s, %s%s.', self::duration($model->duration), $date, $window, $score);
    }

    private static function activity(Activity $model, CardData $card, string $date): string
    {
        $name = $model->name ?? ucfirst(str_replace('-', ' ', $model->type));

        return $card->subtitle
            ? sprintf('%s on %s: %s.', $name, $date, $card->subtitle)
            : sprintf('%s, logged on %s.', $name, $date);
    }

    private static function checkin(Checkin $model, string $date): string
    {
        $place = collect([$model->venue_name, $model->city])->filter()->implode(', ');
        $category = $model->category ? " ({$model->category})" : '';

        $lead = $model->event_name
            ? sprintf('%s at %s%s on %s.', $model->event_name, $place, $category, $date)
            : sprintf('I checked in at %s%s on %s.', $place, $category, $date);

        return trim($lead.' '.(string) $model->description);
    }

    private static function flight(Flight $model, CardData $card, string $date): string
    {
        $airline = $model->relationLoaded('airline') && $model->airline ? " with {$model->airline->name}" : '';
        $leg = "{$model->origin_iata} to {$model->destination_iata}";
        $distance = $model->distance
            ? sprintf(', %s miles', number_format(Distance::miles($model->distance)))
            : '';
        $cabin = $model->cabin_class ? " in {$model->cabin_class->value}" : '';

        return sprintf('I flew %s on %s%s%s%s.', $leg, $date, $airline, $distance, $cabin);
    }

    private static function media(Media $model, string $date): string
    {
        $rating = $model->rating ? " I rated it {$model->rating} out of 10." : '';

        $subject = match ($model->type) {
            MediaType::Film => $model->meta->year ? "{$model->title} ({$model->meta->year})" : $model->title,
            MediaType::TvEpisode => trim(collect([ShowTitle::for($model), self::episodeCode($model), $model->title])
                ->filter()->implode(', ')),
            MediaType::Book => $model->meta->author ? "{$model->title} by {$model->meta->author}" : $model->title,
        };

        $verb = $model->type === MediaType::Book ? 'read' : 'watched';

        return sprintf('I %s %s on %s.%s', $verb, $subject, $date, $rating);
    }

    /** The SxxExx code for an episode, or null when either number is missing. */
    private static function episodeCode(Media $model): ?string
    {
        if ($model->meta->season === null || $model->meta->episode === null) {
            return null;
        }

        return sprintf('S%02dE%02d', $model->meta->season, $model->meta->episode);
    }

    private static function calorie(CardData $card, string $date): string
    {
        $macros = $card->subtitle ? " {$card->subtitle}." : '';

        return sprintf('What I ate on %s: %s for the day.%s', $date, $card->title, $macros);
    }

    private static function fuel(Fuel $model, string $date): string
    {
        $where = collect([$model->station_name, $model->city])->filter()->implode(', ');

        return sprintf(
            'I put %s litres of fuel in for £%s on %s%s.',
            number_format((float) $model->litres, 2),
            number_format((float) $model->cost, 2),
            $date,
            $where !== '' ? ", at {$where}" : '',
        );
    }

    private static function event(Event $model, string $date): string
    {
        $where = collect([$model->venue_name, $model->city])->filter()->implode(', ');

        $lead = $where !== ''
            ? sprintf('I went to %s at %s on %s.', $model->name, $where, $date)
            : sprintf('I went to %s on %s.', $model->name, $date);

        return trim($lead.' '.(string) $model->description);
    }

    private static function appearance(Appearance $model, string $date): string
    {
        $lead = $model->show_name
            ? sprintf('%s on %s, %s.', $model->title, $model->show_name, $date)
            : sprintf('%s, %s.', $model->title, $date);

        return trim($lead.' '.(string) $model->description);
    }

    private static function podcast(Podcast $model, string $date): string
    {
        $lead = sprintf('%s of This Week With, %s.', $model->title, $date);

        return trim($lead.' '.(string) $model->topic);
    }

    /** The hand-written excerpt where there is one, else the opening prose. */
    private static function article(Article $model): string
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
     * Card title and subtitle stitched into a line. Reached only by a type with
     * no case above, so a new type reads sensibly before anyone writes its
     * sentence.
     */
    private static function fallback(CardData $card, string $date): string
    {
        return trim(sprintf(
            '%s%s, logged on %s.',
            $card->title,
            $card->subtitle ? ": {$card->subtitle}" : '',
            $date,
        ));
    }

    /** Seconds as "9h 21m", dropping a zero minute count. */
    private static function duration(int $seconds): string
    {
        $minutes = intdiv($seconds, 60);
        $hours = intdiv($minutes, 60);
        $remainder = $minutes % 60;

        if ($hours === 0) {
            return "{$remainder}m";
        }

        return $remainder > 0 ? "{$hours}h {$remainder}m" : "{$hours}h";
    }
}
