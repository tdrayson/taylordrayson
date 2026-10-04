<?php

namespace App\Data;

use Illuminate\Contracts\Database\Eloquent\Castable;
use Illuminate\Contracts\Database\Eloquent\CastsAttributes;
use Illuminate\Contracts\Support\Arrayable;
use JsonSerializable;

/**
 * A subject's linked identities, one row per platform, rendered as `rel="me"`
 * links.
 */
final readonly class SubjectIdentities implements Arrayable, Castable, JsonSerializable
{
    /** @param list<array{platform: string, value: string}> $identities */
    private function __construct(public array $identities) {}

    public static function fromArray(?array $rows): self
    {
        $identities = [];

        foreach ($rows ?? [] as $row) {
            $platform = trim((string) ($row['platform'] ?? ''));
            $value = trim((string) ($row['value'] ?? ''));

            if ($platform !== '' && $value !== '') {
                $identities[] = ['platform' => $platform, 'value' => $value];
            }
        }

        return new self($identities);
    }

    public function urlFor(string $platform): ?string
    {
        foreach ($this->identities as $identity) {
            if ($identity['platform'] === $platform) {
                return $identity['value'];
            }
        }

        return null;
    }

    /** @return list<array{platform: string, value: string}> */
    public function toArray(): array
    {
        return $this->identities;
    }

    /** @return list<array{platform: string, value: string}> */
    public function jsonSerialize(): array
    {
        return $this->toArray();
    }

    /**
     * @param  array<int, mixed>  $arguments
     */
    public static function castUsing(array $arguments): CastsAttributes
    {
        return new class implements CastsAttributes
        {
            public function get($model, string $key, $value, array $attributes): SubjectIdentities
            {
                return SubjectIdentities::fromArray(json_decode($value ?? '[]', true));
            }

            public function set($model, string $key, $value, array $attributes): array
            {
                $identities = $value instanceof SubjectIdentities ? $value : SubjectIdentities::fromArray($value);

                return [$key => json_encode($identities->toArray())];
            }
        };
    }
}
