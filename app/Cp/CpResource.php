<?php

namespace App\Cp;

use Illuminate\Contracts\Database\Eloquent\Builder;

abstract class CpResource
{
    /** @return class-string */
    abstract public function model(): string;

    abstract public function slug(): string;

    abstract public function label(): string;

    abstract public function pluralLabel(): string;

    public function group(): string
    {
        return 'Timeline';
    }

    /** @return array<int, string> */
    public function searchable(): array
    {
        return [];
    }

    /** @return array{0: string, 1: string} */
    public function defaultSort(): array
    {
        return ['id', 'desc'];
    }

    /**
     * Partial field definitions merged over the guessed fields, keyed by column.
     *
     * @return array<string, array<string, mixed>>
     */
    public function fieldOverrides(): array
    {
        return [];
    }

    /**
     * Declared layout sections. Empty by default, so the resource renders every
     * field in a single main section. Each entry: an area (main|sidebar), an
     * optional tab name, an optional section title, and the field keys it holds.
     *
     * @return array<int, array{area: string, tab?: string, title?: string, fields: array<int, string>}>
     */
    public function sections(): array
    {
        return [];
    }

    /**
     * Synthetic fields composed from a JSON column: typed groups of known
     * sub-keys, and a key/value remainder for the rest. Default none.
     *
     * @return array<int, array<string, mixed>>
     */
    public function composites(): array
    {
        return [];
    }

    /**
     * The resolved field definitions: guessed from the model, then overridden,
     * with composite defs appended and consumed raw columns removed.
     *
     * @return array<int, array<string, mixed>>
     */
    public function fields(): array
    {
        $guessed = app(FieldGuesser::class)->guess($this->model());

        foreach ($this->fieldOverrides() as $column => $override) {
            $guessed[$column] = array_merge($guessed[$column] ?? ['key' => $column, 'label' => $column], $override);
        }

        $composites = $this->composites();

        foreach ($composites as $composite) {
            unset($guessed[$composite['column']]);
        }

        return array_merge(array_values($guessed), $composites);
    }

    /**
     * Resolve the declared sections against the resolved fields. Fields not named
     * in any section fall through to a trailing main section so nothing is
     * dropped. Tabs are the distinct tab names in declared order.
     *
     * @return array{tabs: array<int, string>, sections: array<int, array{area: string, tab: string, title: ?string, fields: array<int, array<string, mixed>>}>}
     */
    public function layout(): array
    {
        $fields = collect($this->fields())->keyBy('key');
        $placed = [];
        $sections = [];

        foreach ($this->sections() as $section) {
            $resolved = [];

            foreach ($section['fields'] as $key) {
                if ($fields->has($key)) {
                    $resolved[] = $fields->get($key);
                    $placed[$key] = true;
                }
            }

            $sections[] = [
                'area' => $section['area'] ?? 'main',
                'tab' => $section['tab'] ?? 'Main',
                'title' => $section['title'] ?? null,
                'fields' => $resolved,
            ];
        }

        $unplaced = $fields
            ->reject(fn (array $field, string $key): bool => isset($placed[$key]))
            ->values()
            ->all();

        if ($unplaced !== []) {
            $sections[] = ['area' => 'main', 'tab' => 'Main', 'title' => null, 'fields' => $unplaced];
        }

        $tabs = collect($sections)->pluck('tab')->unique()->values()->all();

        return ['tabs' => $tabs, 'sections' => $sections];
    }

    /**
     * Index table columns. Defaults to the first four non-textual fields.
     *
     * @return array<int, array{key: string, label: string}>
     */
    public function columns(): array
    {
        return collect($this->fields())
            ->reject(fn (array $field): bool => in_array($field['type'], ['textarea', 'json', 'editor', 'group', 'keyvalue'], true))
            ->take(4)
            ->map(fn (array $field): array => ['key' => $field['key'], 'label' => $field['label']])
            ->values()
            ->all();
    }

    /**
     * Validation rules keyed by column, derived from the resolved fields.
     * Composite fields (group/keyvalue) default to ['nullable', 'array'].
     *
     * @return array<string, array<int, string>>
     */
    public function rules(): array
    {
        return collect($this->fields())
            ->mapWithKeys(fn (array $field): array => [
                $field['key'] => $field['rules'] ?? ['nullable', 'array'],
            ])
            ->all();
    }

    public function query(): Builder
    {
        return ($this->model())::query();
    }

    /**
     * The serializable metadata sent to the frontend.
     *
     * @return array<string, mixed>
     */
    public function meta(): array
    {
        return [
            'slug' => $this->slug(),
            'label' => $this->label(),
            'pluralLabel' => $this->pluralLabel(),
            'group' => $this->group(),
            'columns' => $this->columns(),
            'fields' => $this->fields(),
            'layout' => $this->layout(),
            'searchable' => $this->searchable(),
        ];
    }
}
