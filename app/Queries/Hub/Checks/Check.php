<?php

namespace App\Queries\Hub\Checks;

use App\Data\Hub\AttentionItem;

/**
 * One reason the hub might need the owner.
 *
 * A check must be self-clearing: resolving what it reports has to make it stop
 * reporting. There is no dismiss button, so a check that can emit an item the
 * owner would never action must be narrowed instead.
 */
interface Check
{
    /**
     * @return list<AttentionItem>
     */
    public function items(): array;
}
