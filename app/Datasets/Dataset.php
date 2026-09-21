<?php

namespace App\Datasets;

use App\Enums\DatasetKind;
use App\Enums\SpanAnchor;
use App\Enums\TimelineType;
use Illuminate\Database\Eloquent\Model;

/**
 * Everything the app knows about one timeline data type. The type catalogue,
 * archive registry, search schema and card presenter are views over these.
 */
interface Dataset
{
    public function type(): TimelineType;

    /**
     * @return class-string<Model>
     */
    public function model(): string;

    public function kind(): DatasetKind;

    /** An icon-registry name, resolved by Icon.vue. */
    public function icon(): string;

    public function label(): string;

    public function plural(): string;

    /** The archive path without its leading slash, e.g. 'this-week-with'. */
    public function slug(): string;

    /** Command-palette synonyms. */
    public function keywords(): string;

    /** The OG card's section label, when it is not the label. */
    public function eyebrow(): ?string;

    /** The singular an archive counts its entries in. */
    public function noun(): string;

    /**
     * @return array{0: string, 1: string} Singular and plural for the /more counts.
     */
    public function countNouns(): array;

    /** The card presenter, exposing present() and title() for this type's model. */
    public function card(): object;

    /**
     * @return array<string, array<string, mixed>> Advanced-search field specs, keyed by field.
     */
    public function searchFields(): array;

    /**
     * @return list<string> Columns the "Anything" text search matches.
     */
    public function textColumns(): array;

    /**
     * @return (callable(class-string<Model>, string): array<string, mixed>)|null A taxonomy factory for the archive sub-route.
     */
    public function taxonomy(): ?callable;

    /** Whether /stats/{slug} exists for this type. */
    public function stats(): bool;

    /** Which end of a span occurred_at marks, or null when entries are moments. */
    public function spanAnchor(): ?SpanAnchor;

    /** Whether an entry of this type can be a draft: hand-written types only. */
    public function draftable(): bool;

    /** Whether entries arrive from a service rather than being written here. */
    public function synced(): bool;
}
