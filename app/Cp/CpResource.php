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
     * The resolved field definitions: guessed from the model, then overridden.
     *
     * @return array<int, array<string, mixed>>
     */
    public function fields(): array
    {
        $guessed = app(FieldGuesser::class)->guess($this->model());

        foreach ($this->fieldOverrides() as $column => $override) {
            $guessed[$column] = array_merge($guessed[$column] ?? ['key' => $column, 'label' => $column], $override);
        }

        return array_values($guessed);
    }

    /**
     * Index table columns. Defaults to the first four non-textual fields.
     *
     * @return array<int, array{key: string, label: string}>
     */
    public function columns(): array
    {
        return collect($this->fields())
            ->reject(fn (array $field): bool => in_array($field['type'], ['textarea', 'json', 'editor'], true))
            ->take(4)
            ->map(fn (array $field): array => ['key' => $field['key'], 'label' => $field['label']])
            ->values()
            ->all();
    }

    /**
     * Validation rules keyed by column, derived from the resolved fields.
     *
     * @return array<string, array<int, string>>
     */
    public function rules(): array
    {
        return collect($this->fields())
            ->mapWithKeys(fn (array $field): array => [$field['key'] => $field['rules']])
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
            'searchable' => $this->searchable(),
        ];
    }
}
