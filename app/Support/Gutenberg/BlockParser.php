<?php

namespace App\Support\Gutenberg;

/**
 * Parse WordPress block markup into a tree.
 *
 * Blocks are HTML comments wrapping their own rendered output, either paired
 * (`<!-- wp:paragraph -->…<!-- /wp:paragraph -->`) or void
 * (`<!-- wp:snippetclub/video {…} /-->`), and they nest. Reading the raw markup
 * rather than the rendered HTML is the point of this class: the source site
 * gates half its content behind a membership plugin, which renders to nothing
 * for an anonymous request while the markup still holds every word.
 */
final class BlockParser
{
    /**
     * Matches one delimiter: an optional closing slash, the block name, the
     * optional JSON attributes, and an optional void slash. The trailing `-->`
     * anchors the non-greedy attribute match, so nested braces still land
     * inside it rather than cutting the JSON short at its first `}`.
     */
    private const DELIMITER = '/<!--\s+(?P<closing>\/)?wp:(?P<name>[a-z][a-z0-9_-]*(?:\/[a-z][a-z0-9_-]*)?)\s*(?P<attributes>\{.*?\})?\s*(?P<void>\/)?-->/s';

    /**
     * @return list<Block>
     */
    public function parse(string $markup): array
    {
        preg_match_all(self::DELIMITER, $markup, $matches, PREG_OFFSET_CAPTURE | PREG_SET_ORDER);

        // Each level of nesting collects into its own frame; a closing
        // delimiter pops one and hands the finished block to its parent.
        $stack = [['children' => [], 'open' => null, 'from' => 0]];

        foreach ($matches as $match) {
            [$delimiter, $offset] = $match[0];
            $closing = ($match['closing'][0] ?? '') === '/';
            $void = ($match['void'][0] ?? '') === '/';

            $frame = &$stack[count($stack) - 1];
            $frame['html'] = ($frame['html'] ?? '').substr($markup, $frame['from'], $offset - $frame['from']);
            $frame['from'] = $offset + strlen($delimiter);

            if ($void) {
                $frame['children'][] = new Block($match['name'][0], $this->attributes($match));

                continue;
            }

            if (! $closing) {
                $stack[] = [
                    'children' => [],
                    'open' => new Block($match['name'][0], $this->attributes($match)),
                    'from' => $offset + strlen($delimiter),
                    'html' => '',
                ];

                continue;
            }

            $finished = array_pop($stack);
            $parent = &$stack[count($stack) - 1];
            $open = $finished['open'];

            // A stray closing delimiter with nothing open: keep the markup
            // rather than dropping it, since losing content is the one failure
            // this parser must not have.
            if ($open === null) {
                $parent['html'] = ($parent['html'] ?? '').$finished['html'];

                continue;
            }

            $parent['children'][] = new Block(
                $open->name,
                $open->attributes,
                trim($finished['html']),
                $finished['children'],
            );
            $parent['from'] = $offset + strlen($delimiter);
        }

        return $stack[0]['children'];
    }

    /**
     * @param  array<string, array{0: string, 1: int}>  $match
     * @return array<string, mixed>
     */
    private function attributes(array $match): array
    {
        $json = $match['attributes'][0] ?? '';

        if ($json === '') {
            return [];
        }

        $decoded = json_decode($json, true);

        return is_array($decoded) ? $decoded : [];
    }
}
