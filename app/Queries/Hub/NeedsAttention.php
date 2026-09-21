<?php

namespace App\Queries\Hub;

use App\Data\Hub\AttentionItem;
use App\Queries\Hub\Checks\Check;
use App\Queries\Hub\Checks\FailedJobs;
use App\Queries\Hub\Checks\HeldForModeration;
use App\Queries\Hub\Checks\IncompleteBooks;
use App\Queries\Hub\Checks\StaleDrafts;

/**
 * Everything waiting on a decision, in the order it is worth reading: someone
 * waiting on a reply first, then work that broke, then unfinished writing.
 */
final class NeedsAttention
{
    /** @var list<class-string<Check>> */
    private const CHECKS = [
        HeldForModeration::class,
        FailedJobs::class,
        IncompleteBooks::class,
        StaleDrafts::class,
    ];

    /**
     * @return list<AttentionItem>
     */
    public function __invoke(): array
    {
        $items = [];

        foreach (self::CHECKS as $check) {
            $items = [...$items, ...app($check)->items()];
        }

        return $items;
    }

    public function count(): int
    {
        return count($this());
    }
}
