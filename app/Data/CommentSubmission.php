<?php

namespace App\Data;

use App\Support\PortableText;

/**
 * One attempt at leaving a comment, as it arrives from the form. Everything
 * the gauntlet and the write both need, with no Request behind it.
 */
final readonly class CommentSubmission
{
    public function __construct(
        public string $authorName,
        public ?string $authorEmail,
        public bool $notifyReplies,
        /** @var array<int, array<string, mixed>> Portable Text. */
        public array $body,
        public ?int $parentId,
        public string $nonce,
        /** Filled only by a bot; a real browser never shows the field. */
        public bool $honeypotFilled,
        /** Hashed IP, for recognising a name that has been approved before. */
        public string $ipHash,
        public ?string $userAgent,
    ) {}

    /** The readable text, for the checks and notices that cannot read a document. */
    public function plainBody(): string
    {
        return PortableText::plainText($this->body);
    }

    /**
     * How many distinct places this comment sends a reader.
     *
     * Counted from the annotations and from any address written out in the
     * text, because both are a destination and only one of them is a link.
     * Deduplicated, so a URL that is both linked and spelled out is one place.
     */
    public function linkCount(): int
    {
        $destinations = [];

        foreach ($this->body as $block) {
            foreach ($block['markDefs'] ?? [] as $def) {
                if (($def['_type'] ?? null) === 'link' && is_string($def['href'] ?? null)) {
                    $destinations[self::normalise($def['href'])] = true;
                }
            }
        }

        preg_match_all('#(?:https?://|www\.)\S+#i', $this->plainBody(), $matches);

        foreach ($matches[0] as $written) {
            $destinations[self::normalise($written)] = true;
        }

        return count($destinations);
    }

    /** Compared without scheme, www or trailing punctuation a sentence added. */
    private static function normalise(string $url): string
    {
        $bare = preg_replace('#^(?:https?://)?(?:www\.)?#i', '', trim($url)) ?? $url;

        return strtolower(rtrim($bare, '.,;:!?)]/'));
    }

    /** An address is only worth keeping if it was asked to be used. */
    public function wantsNotifications(): bool
    {
        return $this->notifyReplies && filled($this->authorEmail);
    }
}
