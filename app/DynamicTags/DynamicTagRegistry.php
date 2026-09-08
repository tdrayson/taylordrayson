<?php

namespace App\DynamicTags;

use App\DynamicTags\Entries\EntriesCount;

/**
 * Every registered tag, keyed by name. Listed explicitly rather than
 * discovered, so nothing becomes referenceable by being dropped in a folder.
 */
class DynamicTagRegistry
{
    /** @var list<class-string<DynamicTag>> */
    private const TAGS = [
        EntriesCount::class,
    ];

    /** @var array<string, DynamicTag>|null */
    private ?array $tags = null;

    /**
     * Resolved values, keyed by name and options, so a tag used twice in one
     * document costs one lookup.
     *
     * @var array<string, array{value: mixed, text: string}|null>
     */
    private array $resolved = [];

    /**
     * @return array<string, DynamicTag>
     */
    public function all(): array
    {
        if ($this->tags === null) {
            $this->tags = [];

            foreach (self::TAGS as $class) {
                $tag = new $class;
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
}
