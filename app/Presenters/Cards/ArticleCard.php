<?php

namespace App\Presenters\Cards;

use App\Actions\BuildResponseContext;
use App\Data\CardData;
use App\Data\CardMeta;
use App\Data\PhotoData;
use App\Enums\TimelineType;
use App\Models\Article;
use App\Support\PortableText;
use App\Support\Text;

/**
 * Builds the timeline card for an Article: a plain-text excerpt of the
 * Portable Text content (falling back to the stored excerpt) plus the cover
 * photo.
 */
final class ArticleCard
{
    public function present(Article $model): CardData
    {
        $cover = $model->coverPhoto();
        $response = app(BuildResponseContext::class)($model);

        return new CardData(
            type: $this->type(),
            icon: 'file-text',
            title: $this->title($model),
            titleLabel: null,
            subtitle: Text::excerpt(PortableText::plainText($model->content), 240) ?: $model->excerpt,
            subtitleTokens: null,
            occurredAt: $model->occurred_at,
            accent: 'article',
            range: null,
            meta: CardMeta::photos(
                $cover !== null ? [PhotoData::cover($cover['src'], $cover['srcset'], $cover['full'])] : [],
                $response?->toArray(),
            ),
        );
    }

    public function title(Article $model): string
    {
        return $model->title;
    }

    public function type(): TimelineType
    {
        return TimelineType::Article;
    }
}
