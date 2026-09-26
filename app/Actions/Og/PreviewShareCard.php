<?php

namespace App\Actions\Og;

use App\Data\SharePreviewData;
use App\Enums\EntryStatus;
use App\Fields\FieldRegistry;
use App\Models\Concerns\Timelineable;
use App\Models\Page;
use App\Presenters\CardPresenter;
use App\Support\OgMeta;
use App\Support\OgRenderer;
use App\Support\PortableText;
use Carbon\CarbonInterface;
use Closure;
use Illuminate\Contracts\Support\Arrayable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Arr;
use Illuminate\Support\Str;
use LogicException;

/**
 * Draw the share card and title an entry would have with the editor's current
 * values. The model is filled but never saved, and nothing is written to disk.
 */
final class PreviewShareCard
{
    public function __construct(
        private readonly BuildEntryOgData $entryOgData,
        private readonly BuildPageOgData $pageOgData,
        private readonly OgGalleryUrls $galleryUrls,
        private readonly OgRenderer $renderer,
    ) {}

    /**
     * Fill the model with the editor's values and draw its card and head metadata.
     *
     * @param  Model  $model  A persisted entry, or a blank one of the type being written.
     * @param  array<string, mixed>  $values  The editor's form values; only the type's own fillable fields are used.
     */
    public function __invoke(Model $model, array $values): SharePreviewData
    {
        $model->fill($this->fillable($model, $values));

        $cutout = fn (): string => $this->galleryUrls->dataUri('taylor-cutout.png', 'image/png');
        [$og, $card] = $model instanceof Page ? $this->page($model, $cutout) : $this->entry($model, $cutout);

        $png = $this->renderer->png(view('og.card', $card));

        return new SharePreviewData(
            title: $og['title'] ?: config('identity.name'),
            description: $og['description'],
            image: 'data:image/png;base64,'.base64_encode($png),
        );
    }

    /**
     * The submitted values that are this type's own columns. Media fields name a
     * collection rather than a column, so they never reach fill(). A dotted field
     * (`meta.author`) is merged over the column's current value, as the update actions do.
     *
     * @param  array<string, mixed>  $values
     * @return array<string, mixed>
     */
    private function fillable(Model $model, array $values): array
    {
        $picked = [];

        foreach (FieldRegistry::for($model) as $field) {
            if (! $field->type->isMedia() && $model->isFillable(Str::before($field->name, '.')) && Arr::has($values, $field->name)) {
                $picked[$field->name] = Arr::get($values, $field->name);
            }
        }

        $attributes = Arr::undot($picked);

        foreach (array_keys($picked) as $name) {
            if (str_contains($name, '.')) {
                $root = Str::before($name, '.');
                $current = $model->getAttribute($root);
                $current = $current instanceof Arrayable ? $current->toArray() : (array) $current;
                $attributes[$root] = [...$current, ...$attributes[$root]];
            }
        }

        return $attributes;
    }

    /**
     * A timeline type's head metadata and card, as its entry page and card route build them.
     *
     * @param  Closure(): string  $cutout
     * @return array{0: array<string, mixed>, 1: array<string, mixed>}
     */
    private function entry(Model $model, Closure $cutout): array
    {
        if (! $model instanceof Timelineable) {
            throw new LogicException('No share card for '.$model::class);
        }

        $occurredAt = $model->getAttribute('occurred_at');
        $card = $this->entryOgData->forModel(
            $model,
            'preview|'.now()->timestamp,
            $occurredAt instanceof CarbonInterface ? $occurredAt : now(),
            $cutout,
        );

        return [OgMeta::entry(null, $model, CardPresenter::for($model)), $card];
    }

    /**
     * A page's head metadata and card, as PageController and the page card route build them.
     *
     * @param  Closure(): string  $cutout
     * @return array{0: array<string, mixed>, 1: array<string, mixed>}
     */
    private function page(Page $page, Closure $cutout): array
    {
        $og = OgMeta::page((string) $page->title, $page->excerpt, PortableText::plainText($page->content), $page->status ?? EntryStatus::Published);

        $card = ($this->pageOgData)($og['heading'] ?? $og['title'], $og['eyebrow'], $og['accent'], $og['variant'], $og['description']);

        return [$og, [...$card, 'cutout' => $cutout()]];
    }
}
