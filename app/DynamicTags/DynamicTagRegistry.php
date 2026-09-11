<?php

namespace App\DynamicTags;

use Illuminate\Contracts\Container\Container;

/**
 * Every registered tag, keyed by name. Bound explicitly via the container tag
 * below rather than discovered, so nothing becomes referenceable by being
 * dropped in a folder.
 */
class DynamicTagRegistry
{
    /** The container tag every {@see DynamicTag} binding is registered under. */
    public const CONTAINER_TAG = 'dynamic-tags';

    /** @var array<string, DynamicTag>|null */
    private ?array $tags = null;

    /**
     * Resolved values, keyed by name and options, so a tag used twice in one
     * document costs one lookup.
     *
     * @var array<string, array{value: mixed, text: string}|null>
     */
    private array $resolved = [];

    public function __construct(private readonly Container $container) {}

    /**
     * Built from the container's tagged bindings on first call, then memoised.
     *
     * @return array<string, DynamicTag>
     */
    public function all(): array
    {
        if ($this->tags === null) {
            $this->tags = [];

            foreach ($this->container->tagged(self::CONTAINER_TAG) as $tag) {
                $this->tags[$tag->name()] = $tag;
            }
        }

        return $this->tags;
    }

    public function find(string $name): ?DynamicTag
    {
        return $this->all()[$name] ?? null;
    }

    /**
     * The typed value and its display string, or null when the tag is not
     * registered or has nothing to report.
     *
     * @param  array<string, string>  $options
     * @return array{value: mixed, text: string}|null
     */
    public function value(string $name, array $options): ?array
    {
        $key = $name.':'.json_encode($options);

        if (array_key_exists($key, $this->resolved)) {
            return $this->resolved[$key];
        }

        $tag = $this->find($name);
        $value = $tag?->resolve($options);

        return $this->resolved[$key] = $value === null
            ? null
            : ['value' => $value, 'text' => $tag->format($value, $options)];
    }

    /**
     * The icon payload for a resolved value, or null when the tag doesn't
     * support one or the author left the option off. `true` marks "show the
     * tag's own icon", for a tag whose {@see DynamicTag::iconPayload()} has
     * nothing extra to contribute beyond the value already on the span.
     *
     * @param  array<string, string>  $options
     */
    public function icon(DynamicTag $tag, mixed $value, array $options): mixed
    {
        if (! $tag->supportsIcon() || ($options['icon'] ?? null) !== 'on') {
            return null;
        }

        return $tag->iconPayload($value, $options) ?? true;
    }
}
