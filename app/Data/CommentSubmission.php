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

    /** An address is only worth keeping if it was asked to be used. */
    public function wantsNotifications(): bool
    {
        return $this->notifyReplies && filled($this->authorEmail);
    }
}
