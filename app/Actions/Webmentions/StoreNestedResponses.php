<?php

namespace App\Actions\Webmentions;

use App\Data\MentionData;
use App\Enums\WebmentionKind;
use App\Models\Webmention;
use App\Support\PortableText;
use App\Support\WebmentionTarget;

/**
 * Files the responses somebody else's page carries inside the post that
 * mentioned us, which is the receiving half of a salmention.
 *
 * Their post replied to mine, then people replied to theirs. Re-fetching their
 * page is the only way that thread reaches this one, so every h-cite nested in
 * the h-entry becomes an ordinary webmention row naming the page that carried
 * it, and renders indented under the mention it arrived with.
 *
 * One level only, and never further: a thread of threads is somebody else's
 * page to render, and following it is how a two-site loop starts.
 */
final class StoreNestedResponses
{
    /**
     * More responses than a thread worth quoting under an entry, and the cap
     * that stops a hostile page writing a row per kilobyte it serves.
     */
    private const MAX_NESTED = 20;

    public function __construct(
        private readonly ParseMentionSource $parse,
        private readonly StoreAuthorPhoto $storePhoto,
        private readonly DecideMentionStatus $decideStatus,
    ) {}

    /**
     * @param  Webmention  $parent  The mention whose source page was just fetched.
     * @param  array<string, mixed>  $entry  That page's h-entry, or [] when it had none.
     */
    public function __invoke(Webmention $parent, array $entry = []): void
    {
        $seen = [];

        foreach (array_slice($this->citesIn($entry), 0, self::MAX_NESTED) as $cite) {
            $response = $this->parse->fromEntry($cite, null);

            if ($this->isMine($response)) {
                continue;
            }

            $url = $this->urlOf($cite, $response, $parent);

            if ($url === null || isset($seen[$url]) || $this->alreadySentToUs($url, $parent)) {
                continue;
            }

            $this->store($parent, $url, $response);
            $seen[$url] = true;
        }

        // A response that has left the thread stops being shown, the same way a
        // source that stops linking here loses its mention altogether.
        Webmention::query()
            ->where('parent_source_url', $parent->source_url)
            ->where('target_url', $parent->target_url)
            ->whereNotIn('source_url', array_keys($seen))
            ->delete();
    }

    /**
     * The h-cites nested inside an h-entry.
     *
     * Both places mf2 puts them: `u-comment` and `p-comment` land on the
     * `comment` property, and a citation marked up without a property at all is
     * an unassigned child, which is how plenty of sites publish their threads.
     *
     * @param  array<string, mixed>  $entry
     * @return list<array<string, mixed>>
     */
    private function citesIn(array $entry): array
    {
        $candidates = [
            ...($entry['properties']['comment'] ?? []),
            ...($entry['children'] ?? []),
        ];

        return array_values(array_filter(
            $candidates,
            fn (mixed $cite): bool => is_array($cite)
                && array_intersect(['h-cite', 'h-entry'], $cite['type'] ?? []) !== []
                && is_array($cite['properties'] ?? null),
        ));
    }

    /**
     * Whether this is my own writing coming back to me. A page quoting my reply
     * alongside everybody else's would otherwise put me in my own comment
     * thread, and re-import a response the entry already holds.
     */
    private function isMine(MentionData $response): bool
    {
        return $response->authorUrl !== null && WebmentionTarget::isOurs($response->authorUrl);
    }

    /**
     * Where the nested response lives, which is the key it is stored under.
     *
     * A response with no url of its own gets one derived from the page that
     * carried it and a hash of who said what and when. Position is deliberately
     * not part of it: a new reply arriving at the top of a thread would renumber
     * every row below it and re-import the lot. Two responses that really are
     * identical collapse into one, which is the right answer for a thread shown
     * as a quote.
     *
     * @param  array<string, mixed>  $cite
     */
    private function urlOf(array $cite, MentionData $response, Webmention $parent): ?string
    {
        $url = $cite['properties']['url'][0] ?? null;
        $url = is_array($url) ? ($url['value'] ?? null) : $url;

        if (is_string($url) && str_starts_with($url, 'http')) {
            // Ours, so it is my own post being quoted in their thread, or the
            // entry itself. Either way it is already on this page.
            return WebmentionTarget::isOurs($url) || $url === $parent->source_url ? null : $url;
        }

        $identity = implode('|', [
            $response->authorUrl ?? $response->authorName ?? '',
            PortableText::plainText($response->content ?? []),
            $response->publishedAt?->toIso8601String() ?? '',
        ]);

        return $parent->source_url.'#salmention-'.substr(hash('sha256', $identity), 0, 12);
    }

    /** Whether this response already reached us under its own steam, as a mention of its own. */
    private function alreadySentToUs(string $url, Webmention $parent): bool
    {
        return Webmention::query()
            ->topLevel()
            ->where('source_url', $url)
            ->where('target_url', $parent->target_url)
            ->exists();
    }

    private function store(Webmention $parent, string $url, MentionData $response): void
    {
        $mention = Webmention::query()->firstOrNew([
            'source_url' => $url,
            'target_url' => $parent->target_url,
        ]);

        $mention->target()->associate($parent->target);
        $mention->fill([
            'parent_source_url' => $parent->source_url,
            'kind' => ($response->isReacji() ? WebmentionKind::Reacji : $response->kind)->value,
            'title' => $response->title,
            'author_name' => $response->authorName,
            'author_url' => $response->authorUrl,
            'author_photo_path' => ($this->storePhoto)($response->authorPhoto),
            'author_photo_url' => $response->authorPhoto,
            'content' => $response->content,
            'published_at' => $response->publishedAt,
            'timezone' => $response->publishedTimezone,
            // Verified by the page it was read from being verified: we fetched
            // that page ourselves and confirmed it links here.
            'verified_at' => now(),
            'last_checked_at' => now(),
        ]);

        // Decided once. Re-fetching the thread must not undo a moderation call
        // already made, in either direction.
        if (! $mention->exists) {
            $mention->status = ($this->decideStatus)($response->authorUrl);
        }

        $mention->save();
    }
}
