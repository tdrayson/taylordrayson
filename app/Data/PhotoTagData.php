<?php

namespace App\Data;

use App\Enums\PhotoTagRole;
use App\Models\PhotoTag;
use App\Models\Subject;
use Illuminate\Contracts\Support\Arrayable;
use JsonSerializable;

/**
 * One subject or camera credit tagged on a photograph, with its point when
 * the role carries one.
 */
final readonly class PhotoTagData implements Arrayable, JsonSerializable
{
    public function __construct(
        public int $subjectId,
        public string $name,
        public string $url,
        public PhotoTagRole $role,
        public ?float $x,
        public ?float $y,
    ) {}

    /** Built from a subject loaded through Attachment::subjects(), its pivot carrying the role and point. */
    public static function fromSubject(Subject $subject): self
    {
        /** @var PhotoTag $pivot */
        $pivot = $subject->pivot;

        return new self(
            subjectId: $subject->id,
            name: $subject->name,
            url: $subject->url(),
            role: $pivot->role,
            x: $pivot->x,
            y: $pivot->y,
        );
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'subjectId' => $this->subjectId,
            'name' => $this->name,
            'url' => $this->url,
            'role' => $this->role->value,
            'x' => $this->x,
            'y' => $this->y,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function jsonSerialize(): array
    {
        return $this->toArray();
    }
}
