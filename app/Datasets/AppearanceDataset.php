<?php

namespace App\Datasets;

use App\Enums\DatasetKind;
use App\Enums\TimelineType;
use App\Models\Appearance;
use App\Presenters\Cards\AppearanceCard;
use App\Timeline\Taxonomies;

/**
 * Talks, interviews and other public speaking appearances.
 */
final class AppearanceDataset extends BaseDataset
{
    public function type(): TimelineType
    {
        return TimelineType::Appearance;
    }

    public function model(): string
    {
        return Appearance::class;
    }

    public function kind(): DatasetKind
    {
        return DatasetKind::Speaking;
    }

    public function icon(): string
    {
        return 'Mic01Icon';
    }

    public function label(): string
    {
        return 'Appearance';
    }

    public function plural(): string
    {
        return 'Appearances';
    }

    public function slug(): string
    {
        return 'appearances';
    }

    public function keywords(): string
    {
        return 'talk speaking interview';
    }

    public function card(): AppearanceCard
    {
        return new AppearanceCard;
    }

    /**
     * @return array<string, array<string, mixed>>
     */
    public function searchFields(): array
    {
        return [
            'title' => ['label' => 'Title', 'dataType' => 'text', 'column' => 'title', 'category' => 'Appearance'],
            'show' => ['label' => 'Show', 'dataType' => 'text', 'column' => 'show_name', 'category' => 'Appearance'],
            'kind' => ['label' => 'Type', 'dataType' => 'enum', 'column' => 'type', 'category' => 'Appearance'],
            'description' => ['label' => 'Description', 'dataType' => 'text', 'column' => 'description', 'category' => 'Appearance'],
            'photos' => ['label' => 'Photos', 'dataType' => 'media', 'column' => null, 'category' => 'Media', 'suffix' => 'photos'],
        ];
    }

    /**
     * @return list<string>
     */
    public function textColumns(): array
    {
        return ['title', 'show_name', 'description'];
    }

    public function taxonomy(): callable
    {
        return Taxonomies::column('type', 'Type', fn (string $label): string => "{$label} appearances");
    }
}
