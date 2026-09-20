<?php

namespace App\Actions\Citations;

use App\Actions\Webmentions\StoreAuthorPhoto;
use App\Data\CitationData;
use App\Models\Citation;
use Illuminate\Database\Eloquent\Model;

/**
 * Writes what a fetch found, only ever adding to the stored copy, and points a
 * reply at it when given one.
 */
final class StoreCitation
{
    public function __construct(private readonly StoreAuthorPhoto $storePhoto) {}

    public function __invoke(CitationData $data, ?Model $reply = null): Citation
    {
        $citation = Citation::query()->firstOrNew(['url' => $data->url]);

        // Only what this fetch actually found: a null means "not seen this time",
        // never "remove it".
        $found = array_filter($data->toArray(), fn (mixed $value): bool => $value !== null);

        $citation->fill($found);

        if ($data->authorPhotoUrl !== null) {
            $citation->author_photo_path = ($this->storePhoto)($data->authorPhotoUrl) ?? $citation->author_photo_path;
        }

        $citation->fetched_at = now();
        $citation->save();

        if ($reply !== null && $reply->citation_id !== $citation->id) {
            $this->link($reply, $citation, $data->url);
        }

        return $citation;
    }

    /** Points the reply at the citation, unless its URL changed while the fetch ran. */
    private function link(Model $reply, Citation $citation, string $url): void
    {
        $linked = $reply::query()
            ->whereKey($reply->getKey())
            ->where('response_url', $url)
            ->update(['citation_id' => $citation->id]);

        if ($linked > 0) {
            $reply->citation()->associate($citation);
            $reply->syncOriginalAttribute('citation_id');
        }
    }
}
