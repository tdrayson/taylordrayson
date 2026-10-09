<?php

namespace App\Support;

use App\Data\Head\HeadData;
use App\Data\Head\LinkTagData;
use App\Data\Head\MetaTagData;
use App\Enums\MetaAttribute;
use App\Presenters\Heads\SiteHeads;
use Closure;
use InvalidArgumentException;

/**
 * The current request's document head: the page's own definition plus whatever
 * any other layer adds to it. Shared to the page as `head` once the controller is done.
 */
final class Head
{
    /** @var HeadData|(Closure(): HeadData)|null */
    private HeadData|Closure|null $definition = null;

    private ?string $canonical = null;

    private bool $noindex = false;

    /** @var array<string, MetaTagData> */
    private array $meta = [];

    /** @var array<string, LinkTagData> */
    private array $links = [];

    /**
     * Define the page's head. A closure defers work a partial reload never renders.
     *
     * @param  HeadData|(Closure(): HeadData)  $definition
     */
    public function set(HeadData|Closure $definition): self
    {
        $this->definition = $definition;

        return $this;
    }

    public function canonical(string $url): self
    {
        $this->canonical = $url;

        return $this;
    }

    public function noindex(): self
    {
        $this->noindex = true;

        return $this;
    }

    /**
     * Add a meta tag, replacing any earlier one with the same attribute and key.
     *
     * @throws InvalidArgumentException For a key HeadData already types.
     */
    public function meta(MetaAttribute $attribute, string $key, string $content): self
    {
        $normalised = strtolower($key);

        if (in_array($normalised, ['description', 'robots'], true) || str_starts_with($normalised, 'og:') || str_starts_with($normalised, 'twitter:')) {
            throw new InvalidArgumentException("The {$key} meta tag is set through HeadData, not Head::meta().");
        }

        $tag = new MetaTagData($attribute, $key, $content);
        $this->meta[self::metaKey($tag)] = $tag;

        return $this;
    }

    /**
     * Add a link tag, replacing any earlier one with the same rel, href and type.
     *
     * @throws InvalidArgumentException For rel=canonical, which has canonical().
     */
    public function link(string $rel, string $href, ?string $type = null, ?string $title = null, ?string $hreflang = null): self
    {
        if (strtolower($rel) === 'canonical') {
            throw new InvalidArgumentException('The canonical link is set through Head::canonical(), not Head::link().');
        }

        $link = new LinkTagData($rel, $href, $type, $title, $hreflang);
        $this->links[self::linkKey($link)] = $link;

        return $this;
    }

    /** The page's definition, or the site default, with every addition applied. */
    public function resolve(): HeadData
    {
        $definition = $this->definition instanceof Closure ? ($this->definition)() : $this->definition;
        $base = $definition ?? SiteHeads::default();

        $feeds = array_map(
            fn (array $feed): LinkTagData => new LinkTagData('alternate', $feed['href'], $feed['type'], $feed['title']),
            FeedDiscovery::forRoute(request()->route()),
        );

        return $base->with(
            canonical: $this->canonical ?? $base->canonical,
            noindex: $base->noindex || $this->noindex,
            meta: self::unique([...$base->meta, ...array_values($this->meta)], self::metaKey(...)),
            links: self::unique([...$feeds, ...$base->links, ...array_values($this->links)], self::linkKey(...)),
        );
    }

    /**
     * Drop earlier duplicates, keeping the first position and the last value.
     *
     * @template T
     *
     * @param  list<T>  $tags
     * @param  Closure(T): string  $key
     * @return list<T>
     */
    private static function unique(array $tags, Closure $key): array
    {
        $unique = [];

        foreach ($tags as $tag) {
            $unique[$key($tag)] = $tag;
        }

        return array_values($unique);
    }

    private static function metaKey(MetaTagData $tag): string
    {
        return $tag->attribute->value.'|'.strtolower($tag->key);
    }

    private static function linkKey(LinkTagData $link): string
    {
        return strtolower($link->rel).'|'.$link->href.'|'.$link->type;
    }
}
