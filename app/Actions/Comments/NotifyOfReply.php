<?php

namespace App\Actions\Comments;

use App\Enums\CommentStatus;
use App\Models\Comment;
use App\Notifications\ReplyPosted;
use Illuminate\Support\Facades\Notification;

/**
 * Tell somebody their comment has been answered.
 *
 * Called from both paths a reply can become visible by: approved in the
 * moderation queue, or posted straight through by a name already trusted on
 * this entry. Living in the moderation controller alone meant the second path
 * silently sent nothing, which is the path a running conversation takes.
 */
final class NotifyOfReply
{
    public function __invoke(Comment $comment): void
    {
        if ($comment->status !== CommentStatus::Approved) {
            return;
        }

        $parent = $comment->parent;

        if (! $parent?->wantsReplyNotifications()) {
            return;
        }

        // Nobody is told they replied to themselves.
        if ($parent->author_email === $comment->author_email) {
            return;
        }

        Notification::route('mail', $parent->author_email)->notify(new ReplyPosted($comment, $parent));
    }
}
