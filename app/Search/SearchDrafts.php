<?php

namespace App\Search;

use App\Datasets\Datasets;
use App\Enums\EntryStatus;
use App\Presenters\CardPresenter;
use Illuminate\Database\Eloquent\Model;

/**
 * Drafts matching a validated filter. Drafts have no spine row for RunSearch to
 * page through, so each typed group runs against its own model instead.
 */
final class SearchDrafts
{
    private const LIMIT = 25;

    public function __construct(private readonly SearchCompiler $compiler) {}

    /**
     * The most recently edited matching drafts as cards. "Anything" groups match none.
     *
     * @param  array<int, array<string, mixed>>  $groups  Validated filter groups.
     * @return list<array<string, mixed>>
     */
    public function __invoke(array $groups): array
    {
        $drafts = collect();

        foreach ($groups as $group) {
            $dataset = Datasets::for($group['type']);

            if ($dataset === null || ! $dataset->draftable()) {
                continue;
            }

            $query = $dataset->model()::query()->where('status', EntryStatus::Draft);
            $this->compiler->applyToModel($query, $group);

            $drafts = $drafts->concat($query->latest('updated_at')->limit(self::LIMIT)->get());
        }

        return $drafts
            ->unique(fn (Model $model): string => $model::class.':'.$model->getKey())
            ->sortByDesc('updated_at')
            ->take(self::LIMIT)
            ->map(fn (Model $model): array => [
                'url' => $model->url(),
                'status' => EntryStatus::Draft->value,
                'updated' => $model->updated_at?->toDateTimeString(),
                ...CardPresenter::for($model)->toArray(),
            ])
            ->values()
            ->all();
    }
}
