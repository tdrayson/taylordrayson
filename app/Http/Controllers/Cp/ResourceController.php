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

    private function resolve(string $resource): CpResource
    {
        return $this->registry->find($resource) ?? abort(404);
    }

    /**
     * Cast submitted values for storage (decode JSON fields, coerce booleans).
     *
     * @param  array<string, mixed>  $validated
     * @return array<string, mixed>
     */
    private function prepare(CpResource $definition, array $validated): array
    {
        foreach ($definition->fields() as $field) {
            $key = $field['key'];

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
            $values[$field['key']] = $field['type'] === 'boolean' ? false : '';
        }

        return $values;
    }

    /**
     * Form values for an existing record, with datetimes and JSON serialized for editing.
     *
     * @return array<string, mixed>
     */
    private function recordValues(CpResource $definition, Model $record): array
    {
        $values = [];

        foreach ($definition->fields() as $field) {
            $key = $field['key'];
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
}
