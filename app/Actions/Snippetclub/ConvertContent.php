<?php

namespace App\Actions\Snippetclub;

use App\Data\ConvertedContent;
use App\Support\Gutenberg\Block;
use App\Support\Gutenberg\BlockParser;
use App\Support\Gutenberg\InlineHtml;
use App\Support\Gutenberg\Url;
use App\Support\PortableText;
use Closure;

/**
 * WordPress block markup to this app's Portable Text.
 *
 * One-off, for the SnippetClub migration. Delete it once the content has moved.
 */
final class ConvertContent
{
    /** Extensions that make a link a download rather than a destination. */
    private const FILE_EXTENSIONS = ['zip', 'gz', 'tar', 'pdf', 'txt', 'md', 'csv', 'json', 'xml'];

    /** Wrappers that carry styling the new site does not have. */
    private const UNWRAPPED = [
        'fluent-crm/conditional-content',
        'generateblocks/container',
        'generateblocks/grid',
        'generateblocks/button-container',
    ];

    private const DROPPED = ['spacer'];

    /** @var list<string> */
    private array $notes = [];

    public function __construct(
        private readonly BlockParser $parser = new BlockParser,
        private readonly InlineHtml $inline = new InlineHtml,
    ) {}

    /**
     * @param  Closure(string): ?array{url: string, poster: ?string}  $resolveVideo
     *                                                                               Turns a share URL into something playable, or null to drop the video.
     */
    public function __invoke(string $markup, ?Closure $resolveVideo = null): ConvertedContent
    {
        $this->notes = [];

        $nodes = $this->blocks($this->parser->parse($markup), $resolveVideo, 1);

        return new ConvertedContent(array_values($nodes), $this->notes);
    }

    /**
     * @param  list<Block>  $blocks
     * @return list<array<string, mixed>>
     */
    private function blocks(array $blocks, ?Closure $resolveVideo, int $level): array
    {
        $out = [];

        foreach ($blocks as $block) {
            foreach ($this->block($block, $resolveVideo, $level) as $node) {
                $out[] = $node;
            }
        }

        return $out;
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function block(Block $block, ?Closure $resolveVideo, int $level): array
    {
        if (in_array($block->name, self::DROPPED, true)) {
            return [];
        }

        // A wrapper contributes nothing itself; its children join the flow.
        // The membership gate is one of these, which is how the paywalled half
        // of the content comes across at all.
        if (in_array($block->name, self::UNWRAPPED, true)) {
            return $this->blocks($block->children, $resolveVideo, $level);
        }

        return match ($block->name) {
            'paragraph' => $this->textBlock($block->innerHtml, 'normal'),
            'heading', 'generateblocks/headline' => $this->heading($block),
            'quote' => $this->textBlock($block->innerHtml, 'blockquote'),
            'list' => $this->list($block, $resolveVideo, $level),
            'image', 'generateblocks/image' => $this->image($block),
            'blockstudio-element/code' => $this->code($block),
            'preformatted' => $this->preformatted($block),
            'generateblocks/button' => $this->button($block),
            'snippetclub/video', 'snippetclub/lightbox' => $this->video($block, $resolveVideo),
            'embed' => $this->embed($block, $resolveVideo),
            default => $this->unknown($block, $resolveVideo, $level),
        };
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function textBlock(string $html, string $style, ?string $listItem = null, int $level = 1): array
    {
        ['children' => $children, 'markDefs' => $markDefs, 'images' => $images] = $this->inline->convert($html);

        if ($children === []) {
            return $images;
        }

        $node = [
            '_type' => 'block',
            '_key' => PortableText::key(),
            'style' => $style,
            'markDefs' => $markDefs,
            'children' => $children,
        ];

        if ($listItem !== null) {
            $node['listItem'] = $listItem;
            $node['level'] = $level;
        }

        // An image written inside a paragraph follows it rather than nesting,
        // the dialect having nowhere to put it inside a text block.
        return [$node, ...$images];
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function heading(Block $block): array
    {
        // The rendered tag is the truth: level 2 is the editor's default and is
        // left out of the attributes entirely.
        preg_match('/<h([1-6])\b/i', $block->innerHtml, $match);

        $level = (int) ($match[1] ?? $block->attribute('level', 2));

        // h1 belongs to the page title, so a heading written as one steps down.
        return $this->textBlock($block->innerHtml, 'h'.max(2, min(6, $level)));
    }

    /**
     * A list flattens into consecutive blocks each carrying its own level, and
     * a list nested inside an item recurses one deeper.
     *
     * @return list<array<string, mixed>>
     */
    private function list(Block $block, ?Closure $resolveVideo, int $level): array
    {
        $listItem = $block->attribute('ordered') === true ? 'number' : 'bullet';
        $out = [];

        foreach ($block->children as $item) {
            if ($item->name !== 'list-item') {
                continue;
            }

            foreach ($this->textBlock($item->innerHtml, 'normal', $listItem, $level) as $node) {
                $out[] = $node;
            }

            foreach ($item->children as $child) {
                if ($child->name === 'list') {
                    foreach ($this->list($child, $resolveVideo, $level + 1) as $node) {
                        $out[] = $node;
                    }
                }
            }
        }

        return $out;
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function image(Block $block): array
    {
        preg_match('/<img[^>]+src="([^"]+)"/i', $block->innerHtml, $src);
        preg_match('/<img[^>]+alt="([^"]*)"/i', $block->innerHtml, $alt);
        preg_match('#<figcaption[^>]*>(.*?)</figcaption>#is', $block->innerHtml, $caption);

        if (($src[1] ?? '') === '') {
            return [];
        }

        return [$this->pruned([
            '_type' => 'image',
            '_key' => PortableText::key(),
            'url' => Url::normalise(html_entity_decode($src[1], ENT_QUOTES | ENT_HTML5)),
            'alt' => ($alt[1] ?? '') !== '' ? html_entity_decode($alt[1], ENT_QUOTES | ENT_HTML5) : null,
            'ratio' => null,
            'caption' => isset($caption[1]) ? trim(strip_tags($caption[1])) ?: null : null,
            'width' => null,
            'height' => null,
        ])];
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function code(Block $block): array
    {
        $code = $block->attribute('blockstudio.attributes.code');

        if (! is_string($code) || trim($code) === '') {
            return [];
        }

        return [$this->pruned([
            '_type' => 'code',
            '_key' => PortableText::key(),
            'code' => $code,
            'language' => $this->scalar($block->attribute('blockstudio.attributes.language')),
            'filename' => null,
            'lineNumbers' => $block->attribute('blockstudio.attributes.lineNumbers') === true,
        ])];
    }

    /**
     * A grey preformatted box was the old site's only aside, so it holds both
     * code fragments and notes. Emitted as code and flagged, since which one it
     * is cannot be read off the markup.
     *
     * @return list<array<string, mixed>>
     */
    private function preformatted(Block $block): array
    {
        $text = trim(html_entity_decode(strip_tags($block->innerHtml), ENT_QUOTES | ENT_HTML5));

        if ($text === '') {
            return [];
        }

        $this->notes[] = 'preformatted block, may want to be a callout: '.mb_strimwidth($text, 0, 80, '...');

        return [$this->pruned([
            '_type' => 'code',
            '_key' => PortableText::key(),
            'code' => $text,
            'language' => null,
            'filename' => null,
            'lineNumbers' => false,
        ])];
    }

    /**
     * A button is either a download or an ordinary link. In-page anchors go:
     * they pointed at a section of the old layout.
     *
     * @return list<array<string, mixed>>
     */
    private function button(Block $block): array
    {
        preg_match('#<a[^>]+href="([^"]+)"[^>]*>(.*?)</a>#is', $block->innerHtml, $match);

        $href = html_entity_decode($match[1] ?? '', ENT_QUOTES | ENT_HTML5);
        $label = trim(strip_tags($match[2] ?? ''));

        if ($href === '' || str_starts_with($href, '#')) {
            return [];
        }

        $extension = strtolower(pathinfo(parse_url($href, PHP_URL_PATH) ?? '', PATHINFO_EXTENSION));

        if (in_array($extension, self::FILE_EXTENSIONS, true)) {
            $name = basename(parse_url($href, PHP_URL_PATH) ?? '');

            return [$this->pruned([
                '_type' => 'file',
                '_key' => PortableText::key(),
                'source' => 'upload',
                'url' => $href,
                'name' => $name,
                'mime' => null,
                'size' => $this->labelledSize($label),
                'title' => $this->title($label, $name),
                'poster' => null,
            ])];
        }

        return $this->textBlock(
            sprintf('<a href="%s">%s</a>', htmlspecialchars($href, ENT_QUOTES), htmlspecialchars($label ?: $href, ENT_QUOTES)),
            'normal',
        );
    }

    /**
     * The old buttons carried the size in their label, which is the only record
     * of it: the files are hotlinked, so nothing here has stat'd them.
     */
    private function labelledSize(string $label): ?int
    {
        if (preg_match('/\(([\d.]+)\s*(B|KB|MB|GB)\)\s*$/i', $label, $match) !== 1) {
            return null;
        }

        $multiplier = ['b' => 1, 'kb' => 1024, 'mb' => 1024 ** 2, 'gb' => 1024 ** 3];

        return (int) round((float) $match[1] * $multiplier[strtolower($match[2])]);
    }

    /**
     * A label worth keeping, or null when it only repeats the filename. The
     * size is dropped either way, the card printing its own.
     */
    private function title(string $label, string $name): ?string
    {
        $label = trim((string) preg_replace('/\(([\d.]+)\s*(B|KB|MB|GB)\)\s*$/i', '', $label));

        $bare = fn (string $value): string => strtolower((string) preg_replace('/[^a-z0-9]/i', '', $value));

        if ($label === '' || $bare($label) === $bare($name) || $bare($label) === $bare(pathinfo($name, PATHINFO_FILENAME))) {
            return null;
        }

        return $label;
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function video(Block $block, ?Closure $resolveVideo): array
    {
        $url = $this->scalar($block->attribute('blockstudio.attributes.video_link'))
            ?? $this->scalar($block->attribute('blockstudio.attributes.target'));

        return $this->videoNode($url, $resolveVideo);
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function embed(Block $block, ?Closure $resolveVideo): array
    {
        return $this->videoNode($this->scalar($block->attribute('url')), $resolveVideo);
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function videoNode(?string $url, ?Closure $resolveVideo): array
    {
        if ($url === null || trim($url) === '') {
            return [];
        }

        $poster = null;

        // Only a host the player cannot stream from needs resolving; a YouTube
        // URL is already something VideoEmbed knows how to open.
        if ($resolveVideo !== null && ! preg_match('#(youtube\.com|youtu\.be|vimeo\.com)#i', $url)) {
            $resolved = $resolveVideo($url);

            if ($resolved === null) {
                $this->notes[] = 'video could not be resolved and was dropped: '.$url;

                return [];
            }

            $url = $resolved['url'];
            $poster = $resolved['poster'] ?? null;
        }

        return [$this->pruned([
            '_type' => 'video',
            '_key' => PortableText::key(),
            'url' => $url,
            'caption' => null,
            'poster' => $poster,
            'width' => null,
            'height' => null,
        ])];
    }

    /**
     * An unrecognised block still has children and markup worth keeping, so it
     * is unwrapped rather than dropped, and named so the run reports it.
     *
     * @return list<array<string, mixed>>
     */
    private function unknown(Block $block, ?Closure $resolveVideo, int $level): array
    {
        $this->notes[] = 'unhandled block kept as text: '.$block->name;

        return [
            ...$this->textBlock($block->innerHtml, 'normal'),
            ...$this->blocks($block->children, $resolveVideo, $level),
        ];
    }

    /**
     * Drop the keys with nothing in them. The rule treats a present key as a
     * promise that it holds a value, so `width => null` reads as a broken
     * dimension rather than as an absent one.
     *
     * @param  array<string, mixed>  $node
     * @return array<string, mixed>
     */
    private function pruned(array $node): array
    {
        return array_filter($node, fn (mixed $value): bool => $value !== null);
    }

    /** Blockstudio stores some attributes as `{"value": …}` and others bare. */
    private function scalar(mixed $value): ?string
    {
        if (is_array($value)) {
            $value = $value['value'] ?? null;
        }

        return is_string($value) && $value !== '' ? $value : null;
    }
}
