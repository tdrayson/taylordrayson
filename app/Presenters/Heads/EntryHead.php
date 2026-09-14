<?php

namespace App\Presenters\Heads;

use App\Actions\Og\BuildEntryOgData;
use App\Data\CardData;
use App\Data\ExportData;
use App\Data\Head\HeadData;
use App\Enums\EntryStatus;
use App\Models\Article;
use App\Models\Place;
use App\Models\Project;
use App\Models\ThisWeekWith;
use App\Models\TimelineEntry;
use App\Models\TvEpisode;
use App\Presenters\CardPresenter;
use App\Presenters\EntryDescription;
use App\Support\OgRenderer;
use App\Support\ShowTitle;
use App\Support\Text;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\URL;

/** The head of a single timeline entry's page. */
final class EntryHead
{
    /** How much of an entry's own title the page title keeps; search engines truncate the display. */
    private const ENTRY_TITLE_LIMIT = 100;

    /**
     * @param  TimelineEntry|null  $entry  The entry whose pre-rendered card to point at, or null when
     *                                     the model has no spine row (e.g. a draft article
     *                                     previewed by its author), which gets a generated page card instead.
     * @param  Model  $model  The entry's content, which its description is written from.
     * @param  CardData  $card  The built card, for its title, subtitle and date.
     * @param  ExportData|null  $export  The entry's export, or null when it offers no formats.
     */
    public static function for(?TimelineEntry $entry, Model $model, CardData $card, ?ExportData $export = null): HeadData
    {
        $title = self::title($model, $card);

        $head = new HeadData(
            title: $title,
            description: EntryDescription::for($model, $card) ?? config('identity.bio'),
            noindex: $model->status !== EntryStatus::Published,
            type: $model instanceof Article ? 'article' : 'website',
            links: $export === null ? [] : FormatLinks::for($export, $title),
        );

        return $head->with(image: $entry !== null
            ? self::cardUrl($entry)
            : SiteHeads::cardUrl($head, '/'.ltrim(request()->path(), '/')));
    }

    /**
     * The entry's card URL, stamped with the card design and the entry's own
     * last-updated time.
     *
     * Both belong in the URL because the card is cached against both, and the
     * URL is what anyone holding a share preview refetches by. Without them a
     * redesigned or edited card keeps the address of the one it replaced.
     *
     * An unlisted or private entry's card URL is signed, so its card cannot be
     * found by walking timeline ids.
     */
    public static function cardUrl(TimelineEntry $entry): string
    {
        $parameters = [
            'entry' => $entry,
            'v' => OgRenderer::generation(),
            't' => BuildEntryOgData::entryTimestamp($entry),
        ];

        return $entry->status === EntryStatus::Published
            ? route('og.entry', $parameters)
            : URL::signedRoute('og.entry', $parameters);
    }

    /**
     * A page title that identifies one entry among its type's thousands.
     *
     * Log entries repeat their titles heavily: only 12% of activity names are
     * distinct, and a search result listing them is useless. Dating them is
     * what tells one from another. Hand-authored pieces are already named
     * deliberately, so they keep the title as written.
     *
     * The date hangs off a dash rather than a comma because the titles it
     * follows are full of commas of their own ("Season 7, Episode 255", "I ate
     * 1,745 calories"), where one more reads as another list item.
     */
    private static function title(Model $model, CardData $card): string
    {
        $cardTitle = CardPresenter::publicTitle($model, $card);

        if ($model instanceof Article || $model instanceof Project) {
            return $cardTitle;
        }

        // An episode's card title is the episode's alone, which off the show's
        // page names nothing: "Netherlands (Race)" needs "Formula 1" in front.
        // A podcast has the same problem for the same reason: on the timeline
        // its show is the type eyebrow, which does not travel with the title.
        $show = match (true) {
            $model instanceof TvEpisode => ShowTitle::for($model),
            $model instanceof ThisWeekWith => 'This Week With',
            default => null,
        };

        // A check-in card reads "at Cineworld", which is a phrase in a feed but
        // not a title. The venue is the name of the thing.
        $title = match (true) {
            $model instanceof Place => trim(collect([$model->event_name, $model->venue_name])->filter()->implode(' at ')),
            $show !== null => "{$show}: {$cardTitle}",
            default => $cardTitle,
        };

        $suffix = $card->occurredAt === null ? '' : ' - '.$card->occurredAt->format('j M Y');

        return Text::excerpt($title, self::ENTRY_TITLE_LIMIT).$suffix;
    }
}
