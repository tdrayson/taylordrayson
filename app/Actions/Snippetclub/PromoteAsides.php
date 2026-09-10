<?php

namespace App\Actions\Snippetclub;

/**
 * Lift an advisory paragraph into the callout it was always trying to be.
 *
 * The old site had no callout, so an aside was written as ordinary prose and
 * sometimes marked by hand with "Note:". Only openings that are unmistakably
 * the author stepping out of the walkthrough qualify: a step confirmation like
 * "you should now see the editor" reads the same way and is not an aside.
 *
 * One-off, for the SnippetClub migration. Delete it once the content has moved.
 */
final class PromoteAsides
{
    /** An opening the author marked themselves, and the panel it asks for. */
    private const MARKED = [
        'note:' => 'note',
        'note that' => 'note',
        'please note' => 'note',
        'important:' => 'important',
        'warning:' => 'warning',
        'caution:' => 'caution',
        'tip:' => 'tip',
    ];

    /** An instruction that is really a gotcha, whatever it opens with. */
    private const GOTCHAS = [
        'make sure', 'remember', 'ensure that', 'be careful', "don't forget",
        'do not forget', 'bear in mind', 'keep in mind', 'you must',
        'you will need to', 'i recommend',
    ];

    /** Narrating what the reader will see next is the walkthrough, not an aside. */
    private const STEPS = ['you should now', 'you should see', 'you should be able'];

    private const CAUTIONS = ["shouldn't", 'should not', 'never', 'be careful'];

    /**
     * @param  list<array<string, mixed>>  $nodes
     * @return array{nodes: list<array<string, mixed>>, promoted: list<string>}
     */
    public function __invoke(array $nodes): array
    {
        $promoted = [];

        $out = array_map(function (array $node) use (&$promoted): array {
            $variant = $this->variantFor($node);

            if ($variant === null) {
                return $node;
            }

            $promoted[] = $variant.': '.mb_strimwidth($this->text($node), 0, 60, '...');

            return [
                '_type' => 'callout',
                '_key' => $node['_key'],
                'variant' => $variant,
                'children' => [$this->withoutMarker($node)],
            ];
        }, $nodes);

        return ['nodes' => array_values($out), 'promoted' => $promoted];
    }

    /**
     * @param  array<string, mixed>  $node
     * @return 'note'|'tip'|'important'|'warning'|'caution'|null
     */
    private function variantFor(array $node): ?string
    {
        if (($node['_type'] ?? '') !== 'block' || ($node['style'] ?? '') !== 'normal' || isset($node['listItem'])) {
            return null;
        }

        $text = mb_strtolower(trim($this->text($node)));

        foreach (self::STEPS as $step) {
            if (str_starts_with($text, $step)) {
                return null;
            }
        }

        foreach (self::MARKED as $opening => $variant) {
            if (str_starts_with($text, $opening)) {
                return $this->sharpened($text, $variant);
            }
        }

        foreach (self::GOTCHAS as $opening) {
            if (str_starts_with($text, $opening)) {
                return $this->sharpened($text, 'note');
            }
        }

        return null;
    }

    /** A note that tells you not to do something is a warning. */
    private function sharpened(string $text, string $variant): string
    {
        if ($variant !== 'note') {
            return $variant;
        }

        foreach (self::CAUTIONS as $needle) {
            if (str_contains($text, $needle)) {
                return 'warning';
            }
        }

        return $variant;
    }

    /**
     * The block with its leading marker gone, the panel's own label already
     * saying which kind of aside it is.
     *
     * @param  array<string, mixed>  $node
     * @return array<string, mixed>
     */
    private function withoutMarker(array $node): array
    {
        $children = $node['children'] ?? [];
        $first = $children[0]['text'] ?? null;

        if (is_string($first)) {
            $stripped = preg_replace('/^\s*(note|important|warning|caution|tip)s?\s*:\s*/iu', '', $first);
            $node['children'][0]['text'] = $stripped ?? $first;
        }

        return $node;
    }

    /**
     * @param  array<string, mixed>  $node
     */
    private function text(array $node): string
    {
        $text = '';

        foreach ($node['children'] ?? [] as $child) {
            $text .= $child['text'] ?? '';
        }

        return $text;
    }
}
