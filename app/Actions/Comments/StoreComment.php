<?php

namespace App\Actions\Comments;

use App\Data\CommentSubmission;
use App\Enums\CommentStatus;
use App\Models\Comment;
use App\Support\FormNonce;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Validation\ValidationException;

/**
 * The gauntlet a comment runs before it is written, and the decision about
 * whether it needs reading first.
 *
 * None of it depends on the email address: an unverified one stops no bots
 * while costing real commenters, so it is optional and plays no part here.
 */
final class StoreComment
{
    public const NONCE_PURPOSE = 'comment';

    /**
     * The fastest a person could plausibly have typed a name and a comment
     * after first touching the form.
     */
    private const MIN_SECONDS_ON_FORM = 3;

    /**
     * The stored comment, or null when the submission was a bot and has been
     * dropped. The caller answers the same either way, so nothing learns which
     * check it failed.
     */
    public function __invoke(Model $target, CommentSubmission $submission): ?Comment
    {
        if ($submission->honeypotFilled) {
            return null;
        }

        $elapsed = FormNonce::claim(self::NONCE_PURPOSE, $submission->nonce);

        if ($elapsed === null) {
            throw ValidationException::withMessages([
                'nonce' => 'This form went stale. Reload the page and try again.',
            ]);
        }

        if ($elapsed < self::MIN_SECONDS_ON_FORM) {
            throw ValidationException::withMessages([
                'body' => 'That came in faster than it can be typed. Try again.',
            ]);
        }

        return $target->morphMany(Comment::class, 'commentable')->create([
            'parent_id' => $this->parentFor($target, $submission),
            'author_name' => $submission->authorName,
            'author_email' => $submission->wantsNotifications() ? $submission->authorEmail : null,
            'notify_replies' => $submission->wantsNotifications(),
            'body' => $submission->body,
            'status' => $this->statusFor($submission),
            'ip_hash' => $submission->ipHash,
            'user_agent' => $submission->userAgent,
        ]);
    }

    /**
     * Hold the first comment from a name, then let that name through. Matched
     * on the IP too, so approving "Jo" does not hand the name to anyone else.
     */
    private function statusFor(CommentSubmission $submission): CommentStatus
    {
        $knownGood = Comment::query()
            ->approved()
            ->where('author_name', $submission->authorName)
            ->where('ip_hash', $submission->ipHash)
            ->exists();

        return $knownGood ? CommentStatus::Approved : CommentStatus::Pending;
    }

    /**
     * The comment being replied to, or null. A parent on another entry, or one
     * not yet approved, is dropped rather than rejected: the reply still says
     * something, it just says it at the top level.
     */
    private function parentFor(Model $target, CommentSubmission $submission): ?int
    {
        if ($submission->parentId === null) {
            return null;
        }

        return Comment::query()
            ->approved()
            ->whereMorphedTo('commentable', $target)
            ->whereKey($submission->parentId)
            ->value('id');
    }
}
