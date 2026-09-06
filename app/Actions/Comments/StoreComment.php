<?php

namespace App\Actions\Comments;

use App\Data\CommentSubmission;
use App\Enums\CommentStatus;
use App\Models\Comment;
use App\Support\FormNonce;
use App\Support\ProfanityFilter;
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
     * More destinations than a person puts in a comment. Link stuffing is what
     * almost all comment spam is for, so the count is the signal rather than
     * anything about the words around it.
     */
    private const LINKS_BEFORE_HOLDING = 2;

    private const LINKS_BEFORE_SPAM = 5;

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
     *
     * Slurs skip the queue and go straight to spam: they are still kept, so a
     * false positive can be released, but they are not something to have to
     * read every morning. Ordinary swearing is not caught by this and should
     * not be.
     */
    private function statusFor(CommentSubmission $submission): CommentStatus
    {
        if (ProfanityFilter::isAbusive($submission->plainBody())) {
            return CommentStatus::Spam;
        }

        $links = $submission->linkCount();

        if ($links >= self::LINKS_BEFORE_SPAM) {
            return CommentStatus::Spam;
        }

        $knownGood = Comment::query()
            ->approved()
            ->where('author_name', $submission->authorName)
            ->where('ip_hash', $submission->ipHash)
            ->exists();

        // A link-heavy comment is never waved through on a name alone. Earning
        // approval once and then reusing the name is the shape link spam takes,
        // and holding it costs a real commenter one wait.
        return $knownGood && $links <= self::LINKS_BEFORE_HOLDING
            ? CommentStatus::Approved
            : CommentStatus::Pending;
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
