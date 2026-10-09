<?php

namespace App\Data\Head;

use App\Enums\MetaAttribute;
use Illuminate\Contracts\Support\Arrayable;
use JsonSerializable;

/** One `<meta>` tag beyond the ones HeadData types. */
final readonly class MetaTagData implements Arrayable, JsonSerializable
{
    public function __construct(
        public MetaAttribute $attribute,
        public string $key,
        public string $content,
    ) {}

    /**
     * @return array{attribute: string, key: string, content: string}
     */
    public function toArray(): array
    {
        return [
            'attribute' => $this->attribute->value,
            'key' => $this->key,
            'content' => $this->content,
        ];
    }

    /**
     * @return array{attribute: string, key: string, content: string}
     */
    public function jsonSerialize(): array
    {
        return $this->toArray();
    }
}
