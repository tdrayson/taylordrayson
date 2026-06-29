<?php

namespace App\Http\Controllers\Cp;

use App\Cp\CpResource;
use App\Cp\ResourceRegistry;
use App\Http\Controllers\Controller;
use App\Http\Requests\Cp\ResourceRequest;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class ResourceController extends Controller
{
    public function __construct(private ResourceRegistry $registry) {}

    public function index(Request $request, string $resource): Response
    {
        $definition = $this->resolve($resource);

        [$sortColumn, $sortDirection] = $definition->defaultSort();
        $sortColumn = (string) $request->string('sort', $sortColumn);
        $sortDirection = $request->string('direction', $sortDirection) === 'asc' ? 'asc' : 'desc';

        $allowed = collect($definition->columns())->pluck('key')->push('id')->push($definition->defaultSort()[0])->unique()->all();
        if (! in_array($sortColumn, $allowed, true)) {
            $sortColumn = $definition->defaultSort()[0];
        }

        $search = trim((string) $request->string('search'));

        $query = $definition->query();

        if ($search !== '' && $definition->searchable() !== []) {
            $query->where(function (Builder $builder) use ($definition, $search): void {
                foreach ($definition->searchable() as $column) {
                    $builder->orWhere($column, 'like', "%{$search}%");
                }
            });
        }

        $records = $query->orderBy($sortColumn, $sortDirection)
            ->paginate(25)
            ->withQueryString();

        return Inertia::render('Cp/Resource/Index', [
            'resource' => $definition->meta(),
            'records' => $records,
            'filters' => ['search' => $search, 'sort' => $sortColumn, 'direction' => $sortDirection],
        ]);
    }

    public function create(string $resource): Response
    {
        $definition = $this->resolve($resource);

        return Inertia::render('Cp/Resource/Form', [
            'resource' => $definition->meta(),
            'record' => null,
            'values' => $this->emptyValues($definition),
        ]);
    }

    public function store(ResourceRequest $request, string $resource): RedirectResponse
    {
        $definition = $this->resolve($resource);

        ($definition->model())::create($this->prepare($definition, $request->validated()));

        return redirect()->route('cp.resource.index', $resource);
    }

    public function edit(string $resource, int $id): Response
    {
        $definition = $this->resolve($resource);
        $record = $definition->query()->findOrFail($id);

        return Inertia::render('Cp/Resource/Form', [
            'resource' => $definition->meta(),
            'record' => ['id' => $record->getKey()],
            'values' => $this->recordValues($definition, $record),
        ]);
    }

    public function update(ResourceRequest $request, string $resource, int $id): RedirectResponse
    {
        $definition = $this->resolve($resource);
        $record = $definition->query()->findOrFail($id);

        $record->update($this->prepare($definition, $request->validated()));

        return redirect()->route('cp.resource.index', $resource);
    }

    public function destroy(string $resource, int $id): RedirectResponse
    {
        $definition = $this->resolve($resource);
        $definition->query()->findOrFail($id)->delete();

        return redirect()->route('cp.resource.index', $resource);
    }

    public function options(Request $request, string $resource): JsonResponse
    {
        $definition = $this->resolve($resource);
        $key = (string) $request->string('field');

        $field = collect($definition->fields())->firstWhere('key', $key);

        abort_unless($field !== null && ($field['type'] ?? null) === 'relation', 422, 'Not a relation field.');

        /** @var class-string<Model> $source */
        $source = $field['source'];
        $valueKey = $field['valueKey'];
        $labelKey = $field['labelKey'];
        $term = trim((string) $request->string('q'));
        $current = $request->has('value') ? (string) $request->string('value') : null;

        $query = $source::query();

        if ($term !== '' && ($field['searchable'] ?? []) !== []) {
            $query->where(function (Builder $builder) use ($field, $term): void {
                foreach ($field['searchable'] as $column) {
                    $builder->orWhere($column, 'like', "%{$term}%");
                }
            });
        }

        $options = $query->orderBy($labelKey)
            ->limit(50)
            ->get()
            ->map(fn (Model $model): array => [
                'value' => $model->getAttribute($valueKey),
                'label' => (string) $model->getAttribute($labelKey),
            ]);

        if ($current !== null && ! $options->contains(fn (array $option): bool => (string) $option['value'] === $current)) {
            $record = $source::query()->where($valueKey, $current)->first();

            if ($record !== null) {
                $options->prepend([
                    'value' => $record->getAttribute($valueKey),
                    'label' => (string) $record->getAttribute($labelKey),
                ]);
            }
        }

        return response()->json(['options' => $options->values()]);
    }

    /**
     * Cast submitted values for storage (decode JSON fields, coerce booleans).
     * Composite fields (group/keyvalue) are merged back into their parent column.
     *
     * @param  array<string, mixed>  $validated
     * @return array<string, mixed>
     */
    protected function prepare(CpResource $definition, array $validated): array
    {
        foreach ($definition->fields() as $field) {
            $key = $field['key'];

            if (in_array($field['type'], ['group', 'keyvalue'], true)) {
                continue;
            }

            if (! array_key_exists($key, $validated)) {
                if ($field['type'] === 'boolean') {
                    $validated[$key] = false;
                }

                continue;
            }

            if ($field['type'] === 'json') {
                $validated[$key] = $validated[$key] === null || $validated[$key] === ''
                    ? null
                    : json_decode((string) $validated[$key], true);
            }

            if ($field['type'] === 'boolean') {
                $validated[$key] = (bool) $validated[$key];
            }
        }

        foreach ($this->compositesByColumn($definition) as $column => $composites) {
            $merged = [];

            foreach ($composites as $composite) {
                $submitted = $validated[$composite['key']] ?? [];
                unset($validated[$composite['key']]);

                if ($composite['type'] === 'group') {
                    foreach ($composite['fields'] as $sub) {
                        $value = $submitted[$sub['key']] ?? null;

                        if ($value !== null && $value !== '') {
                            $merged[$sub['key']] = $value;
                        }
                    }
                }

                if ($composite['type'] === 'keyvalue') {
                    foreach ((array) $submitted as $k => $value) {
                        if ($k !== '') {
                            $merged[$k] = $value;
                        }
                    }
                }
            }

            $validated[$column] = $merged === [] ? null : $merged;
        }

        return $validated;
    }

    /**
     * Empty form values keyed by field, suitable for a create form.
     *
     * @return array<string, mixed>
     */
    private function emptyValues(CpResource $definition): array
    {
        $values = [];

        foreach ($definition->fields() as $field) {
            $values[$field['key']] = match ($field['type']) {
                'boolean' => false,
                'group' => collect($field['fields'])->mapWithKeys(fn (array $sub): array => [$sub['key'] => ''])->all(),
                'keyvalue' => [],
                'tags' => [],
                default => '',
            };
        }

        return $values;
    }

    /**
     * Form values for an existing record, with datetimes and JSON serialized for editing.
     * Composite columns are split into their group sub-values and a key/value remainder.
     *
     * @return array<string, mixed>
     */
    protected function recordValues(CpResource $definition, Model $record): array
    {
        $values = [];

        foreach ($definition->fields() as $field) {
            $key = $field['key'];

            if ($field['type'] === 'group') {
                $stored = (array) ($record->getAttribute($field['column']) ?? []);
                $values[$key] = collect($field['fields'])
                    ->mapWithKeys(fn (array $sub): array => [$sub['key'] => $stored[$sub['key']] ?? ''])
                    ->all();

                continue;
            }

            if ($field['type'] === 'keyvalue') {
                $stored = (array) ($record->getAttribute($field['column']) ?? []);
                $claimed = $this->claimedKeys($definition, $field['column']);
                $values[$key] = collect($stored)
                    ->reject(fn (mixed $value, string $k): bool => in_array($k, $claimed, true))
                    ->all();

                continue;
            }

            $value = $record->getAttribute($key);

            $values[$key] = match ($field['type']) {
                'datetime' => $value?->format('Y-m-d\TH:i'),
                'date' => $value?->format('Y-m-d'),
                'json' => $value === null ? '' : json_encode($value, JSON_PRETTY_PRINT),
                'boolean' => (bool) $value,
                default => $value,
            };
        }

        return $values;
    }

    /**
     * Composite field defs (group/keyvalue) keyed by the JSON column they own.
     *
     * @return array<string, array<int, array<string, mixed>>>
     */
    private function compositesByColumn(CpResource $definition): array
    {
        $byColumn = [];

        foreach ($definition->composites() as $composite) {
            $byColumn[$composite['column']][] = $composite;
        }

        return $byColumn;
    }

    /**
     * Sub-keys owned by group composites on the given column.
     *
     * @return array<int, string>
     */
    private function claimedKeys(CpResource $definition, string $column): array
    {
        $keys = [];

        foreach ($this->compositesByColumn($definition)[$column] ?? [] as $composite) {
            if (($composite['type'] ?? null) === 'group') {
                foreach ($composite['fields'] as $sub) {
                    $keys[] = $sub['key'];
                }
            }
        }

        return $keys;
    }

    private function resolve(string $resource): CpResource
    {
        return $this->registry->find($resource) ?? abort(404);
    }
}
