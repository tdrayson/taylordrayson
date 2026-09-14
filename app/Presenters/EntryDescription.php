<?php

namespace App\Presenters;

use App\Data\CardData;
use App\Enums\EntryStatus;
use App\Models\Article;
use App\Models\Concerns\Timelineable;
use App\Support\Text;
use Illuminate\Database\Eloquent\Model;

/**
 * The meta description for a single entry page. Each card writes its own line
 * through description(); this flattens it to one line and caps it for search.
 */
final class EntryDescription
{
    /** Google truncates around 160 characters; the cap leaves room to cut on a word. */
    private const LIMIT = 200;

    /**
     * The card's standalone line, else its title and subtitle stitched together. A
     * private entry gets its written excerpt or null, never anything read from its fields.
     */
    public static function for(Model $model, CardData $card): ?string
    {
        if ($model->status === EntryStatus::Private) {
            return $model instanceof Article ? Text::excerpt($model->excerpt, self::LIMIT) : null;
        }

        $description = $model instanceof Timelineable ? CardPresenter::card($model)->description($model) : '';
        $description = trim(preg_replace('/\s+/u', ' ', $description) ?? '');

        return Text::excerpt($description, self::LIMIT) ?: self::fallback($card);
    }

    /** Card title and subtitle as one line, for a card with nothing of its own to say. */
    private static function fallback(CardData $card): string
    {
        return trim(sprintf('%s%s', $card->title, $card->subtitle ? ": {$card->subtitle}" : ''));
    }
}
