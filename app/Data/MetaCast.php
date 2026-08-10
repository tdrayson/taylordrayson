<?php

namespace App\Data;

use Illuminate\Contracts\Database\Eloquent\CastsAttributes;
use Illuminate\Database\Eloquent\Model;

/**
 * Casts a JSON `meta` column to and from one of the meta DTOs.
 *
 * Reading never yields null: an empty column hydrates to an empty DTO, so
 * every call site can reach for a field without first checking the bag exists.
 *
 * @implements CastsAttributes<MediaMeta|SeriesMeta, MediaMeta|SeriesMeta|array<string, mixed>|null>
 */
final readonly class MetaCast implements CastsAttributes
{
    /**
     * @param  class-string<MediaMeta|SeriesMeta>  $dto
     */
    public function __construct(private string $dto) {}

    /**
     * @param  array<string, mixed>  $attributes
     */
    public function get(Model $model, string $key, mixed $value, array $attributes): MediaMeta|SeriesMeta
    {
        return ($this->dto)::from($this->decode($value));
    }

    /**
     * @param  array<string, mixed>  $attributes
     * @return array<string, string|null>
     */
    public function set(Model $model, string $key, mixed $value, array $attributes): array
    {
        $meta = $value instanceof $this->dto
            ? $value
            : ($this->dto)::from($this->decode($value));

        $stored = $meta->toArray();

        // An empty bag stays NULL rather than becoming `{}`, matching how the
        // column read before it was cast.
        return [$key => $stored === [] ? null : json_encode($stored)];
    }

    /**
     * @return array<string, mixed>|null
     */
    private function decode(mixed $value): ?array
    {
        if (is_array($value)) {
            return $value;
        }

        if (! is_string($value)) {
            return null;
        }

        $decoded = json_decode($value, true);

        return is_array($decoded) ? $decoded : null;
    }
}
