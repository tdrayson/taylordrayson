<?php

namespace App\Actions\Snippetclub;

/**
 * Per-article fixes the conversion rules cannot express.
 *
 * The importer overwrites content on every run, so a correction made by hand
 * would not survive one. Anything found while reading the migrated articles
 * belongs here instead, where re-running the import keeps it.
 *
 * One-off, for the SnippetClub migration. Delete it once the content has moved.
 */
final class Corrections
{
    /**
     * Nodes to drop, by article slug and the url they carry. Each entry says
     * why, because a bare url tells the next reader nothing.
     *
     * @var array<string, array<string, string>>
     */
    private const DROPPED = [
        // A "Click Me" demo link for the lightbox shortcode, which the article
        // shows in full right below. As an embed it is a rickroll in the middle
        // of a tutorial.
        'lightbox-any-gutenberg-image-with-lity' => [
            'https://www.youtube.com/embed/dQw4w9WgXcQ' => 'was a demo link, not content',
        ],
    ];

    /**
     * Blocks to drop, by article slug and the text they open with. Same idea as
     * DROPPED, for prose that carries no url to match on.
     *
     * @var array<string, array<string, string>>
     */
    private const DROPPED_TEXT = [
        'dynamic-template-tags' => [
            'I can add support for other custom field plugins' => 'an offer that no longer stands',
        ],
    ];

    /**
     * @param  list<array<string, mixed>>  $nodes
     * @return array{nodes: list<array<string, mixed>>, applied: list<string>}
     */
    public function __invoke(string $slug, array $nodes): array
    {
        $drops = self::DROPPED[$slug] ?? [];
        $text = self::DROPPED_TEXT[$slug] ?? [];

        if ($drops === [] && $text === []) {
            return ['nodes' => $nodes, 'applied' => []];
        }

        $applied = [];

        $kept = array_values(array_filter($nodes, function (array $node) use ($drops, $text, &$applied): bool {
            $url = $node['url'] ?? null;

            if (is_string($url) && isset($drops[$url])) {
                $applied[] = $drops[$url].': '.$url;

                return false;
            }

            $opening = '';

            foreach ($node['children'] ?? [] as $child) {
                $opening .= $child['text'] ?? '';
            }

            foreach ($text as $needle => $why) {
                if ($opening !== '' && str_starts_with(trim($opening), $needle)) {
                    $applied[] = $why.': '.mb_strimwidth(trim($opening), 0, 60, '...');

                    return false;
                }
            }

            return true;
        }));

        return ['nodes' => $kept, 'applied' => $applied];
    }
}
