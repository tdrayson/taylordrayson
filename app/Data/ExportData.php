<?php

namespace App\Data;

use App\Enums\TimelineType;
use Illuminate\Contracts\Support\Arrayable;
use JsonSerializable;

/**
 * Everything an export publishes about one resource: an ordered list of
 * labelled fields, an ordered list of labelled links, and typed aspects for
 * the capabilities some formats need.
 *
 * Nothing here is inherited from a model's toArray(). Every field and link is
 * named by a presenter, so ids, audit timestamps, passwords and relation dumps
 * cannot leak into a format.
 */
final readonly class ExportData implements Arrayable, JsonSerializable
{
    /**
     * @param  list<ExportField>  $fields
     * @param  list<ExportLink>  $links
     * @param  array<int, array<string, mixed>>|string|null  $body  A Portable Text document, plain text, or nothing.
     * @param  array<class-string, object>  $aspects  Keyed by class so a format can ask for exactly what it needs.
     * @param  ?string  $standfirst  A genuine summary distinct from the body, only when a presenter set one because
     *                               the page itself shows one (an article's hand-written excerpt). Deliberately not
     *                               $summary: that field falls back to a generated description for formats that
     *                               always want one, which is exactly the text a page-parity check must not publish.
     */
    public function __construct(
        public TimelineType|string $type,
        public string $url,
        public string $title,
        public ?string $summary,
        public ?ExportInstant $occurred,
        public array $fields,
        public array $links,
        public mixed $body = null,
        public array $aspects = [],
        public ?string $standfirst = null,
    ) {}

    public function typeValue(): string
    {
        return $this->type instanceof TimelineType ? $this->type->value : $this->type;
    }

    public function field(string $key): ?ExportField
    {
        foreach ($this->fields as $field) {
            if ($field->key === $key) {
                return $field;
            }
        }

        return null;
    }

    /**
     * @return list<ExportLink>
     */
    public function linksWithRel(string $rel): array
    {
        return array_values(array_filter($this->links, fn (ExportLink $link): bool => $link->rel === $rel));
    }

    /**
     * @template T of object
     *
     * @param  class-string<T>  $class
     * @return T|null
     */
    public function aspect(string $class): ?object
    {
        return $this->aspects[$class] ?? null;
    }

    /**
     * The payload without its trail: the controller adds `formats`, because
     * only it knows which of them this resource actually supports.
     *
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        $data = [
            'type' => $this->typeValue(),
            'url' => $this->url,
            'title' => $this->title,
            'summary' => $this->summary,
            'occurred' => $this->occurred?->toArray(),
        ];

        $data['fields'] = array_map(fn (ExportField $field): array => $field->toArray(), $this->fields);
        $data['links'] = array_map(fn (ExportLink $link): array => $link->toArray(), $this->links);

        return $data;
    }

    /**
     * @return array<string, mixed>
     */
    public function jsonSerialize(): array
    {
        return $this->toArray();
    }
}
