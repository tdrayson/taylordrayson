<?php

namespace App\Search;

use App\Enums\CommentStatus;
use App\Enums\ResponseSource;
use App\Enums\WebmentionKind;
use App\Models\Comment;
use App\Models\Reaction;
use App\Models\SyndicatedResponse;
use App\Models\Webmention;
use Illuminate\Contracts\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Query\Builder as QueryBuilder;

/**
 * Constrains an entry query by the responses and reactions left on it. Every
 * per-response condition in a group must hold for the same response; a count
 * compares how many do, and is "at least one" when the group gives none.
 */
final class ResponseFilter
{
    private const COMPARATORS = ['eq' => '=', 'neq' => '!=', 'gt' => '>', 'gte' => '>=', 'lt' => '<', 'lte' => '<='];

    /**
     * Apply a group's response- and reaction-scoped conditions.
     *
     * @param  Builder  $query  The model query for one entry type.
     * @param  list<array{0: array<string, mixed>, 1: string, 2: mixed}>  $conditions  [field, operator, value] triples.
     */
    public function apply(Builder $query, array $conditions): void
    {
        $scoped = ['response' => [], 'reaction' => []];

        foreach ($conditions as $condition) {
            $scoped[$condition[0]['scope']][] = $condition;
        }

        foreach ($scoped as $scope => $inScope) {
            if ($inScope === []) {
                continue;
            }

            $counts = array_values(array_filter($inScope, fn (array $condition): bool => $condition[0]['dataType'] === 'number'));
            $filters = array_values(array_filter($inScope, fn (array $condition): bool => $condition[0]['dataType'] !== 'number'));

            $subqueries = $scope === 'reaction'
                ? [$this->reactionCount($query, $filters)]
                : $this->responseCounts($query, $filters);

            $this->compare($query, $subqueries, $counts);
        }
    }

    /**
     * One correlated count per response table the filters leave in play.
     *
     * @param  list<array{0: array<string, mixed>, 1: string, 2: mixed}>  $filters
     * @return list<QueryBuilder>
     */
    private function responseCounts(Builder $entry, array $filters): array
    {
        $sources = $this->sources($filters);
        $filters = array_values(array_filter($filters, fn (array $filter): bool => $filter[0]['key'] !== 'response_source'));

        $tables = [
            in_array(ResponseSource::LocalComment->value, $sources, true)
                ? $this->count($entry, new Comment, 'commentable', $filters, kind: null, platform: null, text: [], documents: ['body'], site: [])
                : null,
            in_array(ResponseSource::Webmention->value, $sources, true)
                ? $this->count($entry, new Webmention, 'target', $filters, kind: 'kind', platform: null, text: ['title'], documents: ['content'], site: ['author_host', 'source_url'])
                : null,
            in_array(ResponseSource::Syndicated->value, $sources, true)
                ? $this->count($entry, new SyndicatedResponse, 'target', $filters, kind: 'kind', platform: 'source', text: [], documents: ['body'], site: ['url'])
                : null,
        ];

        return array_values(array_filter($tables));
    }

    /**
     * Count one table's approved responses on the entry that meet every filter,
     * or null when a filter asks for something this table cannot hold.
     *
     * @param  list<array{0: array<string, mixed>, 1: string, 2: mixed}>  $filters
     * @param  string|null  $kind  The kind column, or null for a table whose rows are all replies.
     * @param  string|null  $platform  The column naming the platform, or null for a table that is not synced from one.
     * @param  list<string>  $text  Plain text columns searched by response text.
     * @param  list<string>  $documents  Portable Text columns searched by response text.
     * @param  list<string>  $site  Columns naming the site a response came from.
     */
    private function count(Builder $entry, Model $model, string $morph, array $filters, ?string $kind, ?string $platform, array $text, array $documents, array $site): ?QueryBuilder
    {
        $sub = $this->correlated($entry, $model, $morph)
            ->where($model->qualifyColumn('status'), CommentStatus::Approved->value);

        foreach ($filters as [$field, $operator, $value]) {
            $held = match ($field['key']) {
                'response_kind' => $kind === null
                    ? $this->allows(WebmentionKind::Reply->value, $operator, $value)
                    : $this->whereList($sub, $model->qualifyColumn($kind), $operator, $value),
                'response_platform' => $platform !== null && $this->whereList($sub, $model->qualifyColumn($platform), $operator, $value),
                'response_text' => $this->whereText($sub, array_map($model->qualifyColumn(...), $text), array_map($model->qualifyColumn(...), $documents), (string) $value),
                'response_author' => $this->whereLike($sub, [$model->qualifyColumn('author_name')], $operator, (string) $value),
                'response_site' => $this->whereLike($sub, array_map($model->qualifyColumn(...), $site), $operator, (string) $value),
                default => true,
            };

            if (! $held) {
                return null;
            }
        }

        return $sub;
    }

    /**
     * @param  list<array{0: array<string, mixed>, 1: string, 2: mixed}>  $filters
     */
    private function reactionCount(Builder $entry, array $filters): QueryBuilder
    {
        $sub = $this->correlated($entry, new Reaction, 'reactable');

        foreach ($filters as [, $operator, $value]) {
            $this->whereList($sub, 'reactions.type', $operator, $value);
        }

        return $sub;
    }

    /** A count of the model's rows left on the entry row the outer query is looking at. */
    private function correlated(Builder $entry, Model $model, string $morph): QueryBuilder
    {
        $owner = $entry->getModel();

        return $model->newQuery()->toBase()
            ->selectRaw('count(*)')
            ->where($model->qualifyColumn("{$morph}_type"), $owner->getMorphClass())
            ->whereColumn($model->qualifyColumn("{$morph}_id"), $owner->getQualifiedKeyName());
    }

    /**
     * Compare the summed counts against each count condition, or "at least one" without any.
     *
     * @param  list<QueryBuilder>  $subqueries
     * @param  list<array{0: array<string, mixed>, 1: string, 2: mixed}>  $counts
     */
    private function compare(Builder $query, array $subqueries, array $counts): void
    {
        $sum = $subqueries === [] ? '0' : implode(' + ', array_map(fn (QueryBuilder $sub): string => '('.$sub->toSql().')', $subqueries));
        $bindings = array_merge(...array_map(fn (QueryBuilder $sub): array => $sub->getBindings(), $subqueries));

        foreach ($counts ?: [[[], 'gte', 1]] as [, $operator, $value]) {
            if ($operator === 'between' || $operator === 'not_between') {
                if (is_array($value) && count($value) === 2) {
                    $range = array_map('intval', array_values($value));
                    sort($range);
                    $query->whereRaw("({$sum}) ".($operator === 'not_between' ? 'not between' : 'between').' ? and ?', [...$bindings, ...$range]);
                }

                continue;
            }

            if (isset(self::COMPARATORS[$operator])) {
                $query->whereRaw("({$sum}) ".self::COMPARATORS[$operator].' ?', [...$bindings, (int) $value]);
            }
        }
    }

    /**
     * The sources left once every "response from" condition has narrowed them.
     *
     * @param  list<array{0: array<string, mixed>, 1: string, 2: mixed}>  $filters
     * @return list<string>
     */
    private function sources(array $filters): array
    {
        $sources = array_map(fn (ResponseSource $source): string => $source->value, ResponseSource::cases());

        foreach ($filters as [$field, $operator, $value]) {
            $chosen = $field['key'] === 'response_source' ? $this->values($value) : [];

            if ($chosen !== []) {
                $sources = $operator === 'is_not' ? array_diff($sources, $chosen) : array_intersect($sources, $chosen);
            }
        }

        return array_values($sources);
    }

    /** Whether a fixed value satisfies an is / is-not list condition. */
    private function allows(string $fixed, string $operator, mixed $value): bool
    {
        $chosen = $this->values($value);

        return $chosen === [] || in_array($fixed, $chosen, true) === ($operator !== 'is_not');
    }

    private function whereList(QueryBuilder $sub, string $column, string $operator, mixed $value): bool
    {
        $chosen = $this->values($value);

        if ($chosen !== []) {
            $operator === 'is_not' ? $sub->whereNotIn($column, $chosen) : $sub->whereIn($column, $chosen);
        }

        return true;
    }

    /**
     * Match text against plain columns or the text spans of Portable Text ones.
     *
     * @param  list<string>  $columns
     * @param  list<string>  $documents
     */
    private function whereText(QueryBuilder $sub, array $columns, array $documents, string $value): bool
    {
        $pattern = "%{$value}%";
        // Only the text spans, so a search for "span" or "block" misses the document's own keys.
        $spans = "exists (select 1 from json_tree(%s) where json_tree.key = 'text' and json_tree.value like ?)";

        $sub->where(function (QueryBuilder $any) use ($columns, $documents, $pattern, $spans): void {
            foreach ($columns as $column) {
                $any->orWhere($column, 'like', $pattern);
            }

            foreach ($documents as $document) {
                $any->orWhereRaw(sprintf($spans, $document), [$pattern]);
            }
        });

        return true;
    }

    /**
     * A text operator over one or more columns: any column may match, and a
     * negated operator needs every column to miss. False when there is no column.
     *
     * @param  list<string>  $columns
     */
    private function whereLike(QueryBuilder $sub, array $columns, string $operator, string $value): bool
    {
        if ($columns === []) {
            return false;
        }

        [$comparison, $pattern] = match ($operator) {
            'equals' => ['=', $value],
            'starts_with' => ['like', "{$value}%"],
            'ends_with' => ['like', "%{$value}"],
            default => ['like', "%{$value}%"],
        };

        if ($operator === 'not_contains') {
            foreach ($columns as $column) {
                $sub->where(fn (QueryBuilder $miss) => $miss->whereNull($column)->orWhere($column, 'not like', $pattern));
            }

            return true;
        }

        $sub->where(function (QueryBuilder $any) use ($columns, $comparison, $pattern): void {
            foreach ($columns as $column) {
                $any->orWhere($column, $comparison, $pattern);
            }
        });

        return true;
    }

    /**
     * The non-empty values of a list condition.
     *
     * @return list<string>
     */
    private function values(mixed $value): array
    {
        return array_values(array_filter(
            array_map('strval', is_array($value) ? $value : [$value]),
            fn (string $item): bool => $item !== '',
        ));
    }
}
