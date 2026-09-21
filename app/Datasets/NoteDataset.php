<?php

namespace App\Datasets;

use App\Enums\DatasetKind;
use App\Enums\TimelineType;
use App\Models\Note;
use App\Presenters\Cards\NoteCard;
use App\Presenters\Exports\NoteExport;
use App\Timeline\Taxonomies;

/**
 * Short plaintext notes.
 */
final class NoteDataset extends BaseDataset
{
    public function type(): TimelineType
    {
        return TimelineType::Note;
    }

    public function model(): string
    {
        return Note::class;
    }

    public function kind(): DatasetKind
    {
        return DatasetKind::Writing;
    }

    public function icon(): string
    {
        return 'StickyNote02Icon';
    }

    public function label(): string
    {
        return 'Note';
    }

    public function plural(): string
    {
        return 'Notes';
    }

    public function slug(): string
    {
        return 'notes';
    }

    public function keywords(): string
    {
        return 'memo journal thought';
    }

    public function card(): NoteCard
    {
        return new NoteCard;
    }

    public function export(): object
    {
        return new NoteExport;
    }

    /**
     * @return array<string, array<string, mixed>>
     */
    public function searchFields(): array
    {
        return [
            'content' => ['label' => 'Content', 'dataType' => 'text', 'column' => 'content', 'category' => 'Note'],
        ];
    }

    /**
     * @return list<string>
     */
    public function textColumns(): array
    {
        return ['content'];
    }

    public function taxonomy(): callable
    {
        return Taxonomies::tags(fn (string $label): string => "Notes tagged {$label}");
    }

    public function draftable(): bool
    {
        return true;
    }
}
