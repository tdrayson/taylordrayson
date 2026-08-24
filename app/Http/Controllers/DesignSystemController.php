<?php

namespace App\Http\Controllers;

use App\Actions\BuildLinkFavicons;
use App\Actions\BuildLinkPreviews;
use App\Support\OgMeta;
use App\Support\PortableText;
use Inertia\Inertia;
use Inertia\Response;

class DesignSystemController extends Controller
{
    public function __invoke(): Response
    {
        $document = $this->smartLinkSample();

        return Inertia::render('DesignSystem', [
            'og' => OgMeta::designSystem(),
            'smartLinks' => $document,
            'linkPreviews' => app(BuildLinkPreviews::class)($document),
            'linkFavicons' => (new BuildLinkFavicons)($document),
        ]);
    }

    /**
     * Prose exercising every link treatment at once: an external favicon chip
     * on chosen anchor text, a bare URL that autolinks and collapses to its
     * domain, and an internal link that renders as its entry's chip.
     *
     * Built from a real published article so the internal chip resolves; the
     * paragraph falls back to describing it when there is nothing published.
     *
     * @return array<int, array<string, mixed>>
     */
    private function smartLinkSample(): array
    {
        $blocks = PortableText::fromPlainText(
            'A bare URL collapses to its domain rather than sprawling across the line: '
            .'https://github.com. Anchor text the author chose is never rewritten.'
        );

        // /now rather than a published entry: the sample must render the same
        // on a fresh database as on a full one.
        $blocks[] = $this->linked('An internal link renders as its own chip', route('now', absolute: false));

        return $blocks;
    }

    /**
     * One paragraph that is a single link. Built by hand rather than autolinked,
     * which only matches absolute URLs and so never sees an internal path.
     *
     * @return array<string, mixed>
     */
    private function linked(string $text, string $href): array
    {
        $key = PortableText::key();

        return [
            '_type' => 'block',
            '_key' => PortableText::key(),
            'style' => 'normal',
            'markDefs' => [['_type' => 'link', '_key' => $key, 'href' => $href]],
            'children' => [PortableText::span($text, [$key])],
        ];
    }
}
