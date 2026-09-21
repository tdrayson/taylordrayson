<?php

namespace App\Data;

use Illuminate\Contracts\Support\Arrayable;
use Illuminate\Support\Str;
use JsonSerializable;

/**
 * One labelled URL an export points at: a tag, a show, a trip, an archive, the
 * source permalink. `rel` is what mf2 maps onto p-category and u-syndication.
 */
final readonly class ExportLink implements Arrayable, JsonSerializable
{
    private function __construct(
        public string $key,
        public string $label,
        public string $title,
        public string $url,
        public ?string $rel,
    ) {}

    public static function make(string $key, string $label, string $title, string $url, ?string $rel = null): self
    {
        return new self($key, $label, $title, self::absolute($url), $rel);
    }

    /** The same link, dropped when there is no URL to point at. */
    public static function maybe(string $key, string $label, ?string $title, ?string $url, ?string $rel = null): ?self
    {
        return $url === null || $url === '' ? null : self::make($key, $label, $title ?? $url, $url, $rel);
    }

    /**
     * An export is read away from the site, so a consumer has no base to
     * resolve a relative path against.
     */
    private static function absolute(string $url): string
    {
        return Str::startsWith($url, ['http://', 'https://'])
            ? $url
            : rtrim((string) config('app.url'), '/').'/'.ltrim($url, '/');
    }

    /**
     * @return array<string, string>
     */
    public function toArray(): array
    {
        $data = ['key' => $this->key, 'label' => $this->label, 'title' => $this->title, 'url' => $this->url];

        if ($this->rel !== null) {
            $data['rel'] = $this->rel;
        }

        return $data;
    }

    /**
     * @return array<string, string>
     */
    public function jsonSerialize(): array
    {
        return $this->toArray();
    }
}
