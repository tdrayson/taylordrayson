<?php

namespace App\Data;

use Illuminate\Contracts\Support\Arrayable;
use JsonSerializable;

/**
 * The download card for a `file` block: an uploaded file, or an asset resolved
 * from a repo's latest release. Only a release carries the version and date
 * keys, and both are null until the cache has been warmed once.
 *
 * @implements Arrayable<string, mixed>
 */
final readonly class FileData implements Arrayable, JsonSerializable
{
    private function __construct(
        public string $name,
        public ?string $mime,
        public ?int $size,
        public string $url,
        public ?string $version,
        public ?string $releasedAt,
        private bool $withRelease,
    ) {}

    /**
     * A file stored by the site, whose size and type are known at upload.
     */
    public static function uploaded(string $name, ?string $mime, ?int $size, string $url): self
    {
        return new self($name, $mime, $size, $url, null, null, false);
    }

    /**
     * An asset served from a repo's latest release. `$releasedAt` is a display
     * date, and both it and `$version` are null on a cold cache, where the card
     * shows the filename and the download button alone.
     */
    public static function release(
        string $name,
        ?string $mime,
        ?int $size,
        string $url,
        ?string $version,
        ?string $releasedAt,
    ): self {
        return new self($name, $mime, $size, $url, $version, $releasedAt, true);
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        $data = [
            'name' => $this->name,
            'mime' => $this->mime,
            'size' => $this->size,
            'url' => $this->url,
        ];

        if ($this->withRelease) {
            $data['version'] = $this->version;
            $data['releasedAt'] = $this->releasedAt;
        }

        return $data;
    }

    /**
     * @return array<string, mixed>
     */
    public function jsonSerialize(): array
    {
        return $this->toArray();
    }
}
