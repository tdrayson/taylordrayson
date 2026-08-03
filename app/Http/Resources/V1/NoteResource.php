<?php

namespace App\Http\Resources\V1;

use App\Support\PortableText;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class NoteResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'occurred_at' => $this->occurred_at?->toDateTimeString(),
            'content' => $this->content,
            // The flattened text alongside the blocks, so a client that only
            // wants a string does not have to walk the document itself.
            'text' => PortableText::plainText($this->content),
            'slug' => $this->getAttributes()['slug'] ?? null,
            'url' => $this->url(),
            'timezone' => $this->timezone,
            'tags' => $this->tagNames(),
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }
}
