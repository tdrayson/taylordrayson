<?php

namespace App\Enums;

/** Who can find an entry and who can open it. */
enum EntryStatus: string
{
    case Draft = 'draft';
    case Published = 'published';
    case Unlisted = 'unlisted';
    case Private = 'private';

    public function label(): string
    {
        return match ($this) {
            self::Draft => 'Draft',
            self::Published => 'Published',
            self::Unlisted => 'Unlisted',
            self::Private => 'Private',
        };
    }

    /**
     * Every status as a select option; the first is what a new entry starts as.
     *
     * @return list<array{value: string, label: string}>
     */
    public static function options(bool $draftFirst = false): array
    {
        $order = $draftFirst
            ? [self::Draft, self::Published, self::Unlisted, self::Private]
            : [self::Published, self::Unlisted, self::Private, self::Draft];

        return array_map(fn (self $status): array => ['value' => $status->value, 'label' => $status->label()], $order);
    }
}
