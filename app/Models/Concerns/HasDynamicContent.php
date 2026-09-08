<?php

namespace App\Models\Concerns;

use App\Actions\ResolveDynamicTags;

/**
 * Content with its dynamic tags realised. Every read path uses this rather
 * than `content`, or a tag reaches the page unresolved.
 */
trait HasDynamicContent
{
    /** @var array<int, array<string, mixed>>|null */
    private ?array $resolvedContent = null;

    /**
     * @return array<int, array<string, mixed>>
     */
    public function resolvedContent(): array
    {
        return $this->resolvedContent ??= app(ResolveDynamicTags::class)($this->content);
    }
}
