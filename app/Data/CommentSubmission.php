<?php

namespace App\Data;

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
        public string $body,
        public ?int $parentId,
        public string $nonce,
        /** Filled only by a bot; a real browser never shows the field. */
        public bool $honeypotFilled,
        /** Hashed IP, for recognising a name that has been approved before. */
        public string $ipHash,
        public ?string $userAgent,
    ) {}

    /** An address is only worth keeping if it was asked to be used. */
    public function wantsNotifications(): bool
    {
        return $this->notifyReplies && filled($this->authorEmail);
    }
}
