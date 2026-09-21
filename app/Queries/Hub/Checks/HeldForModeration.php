<?php

namespace App\Queries\Hub\Checks;

use App\Data\Hub\AttentionItem;
use App\Enums\CommentStatus;
use App\Models\Comment;
use App\Models\Webmention;
use App\Support\InteractionTarget;
use App\Support\PortableText;

/**
 * Comments and mentions held back until they are read. Clears when one is
 * approved or rejected.
 */
final class HeldForModeration implements Check
{
    /**
     * @return list<AttentionItem>
     */
    public function items(): array
    {
        return [...$this->comments(), ...$this->mentions()];
    }

    /**
     * @return list<AttentionItem>
     */
    private function comments(): array
    {
        return Comment::query()
            ->pending()
            ->with('commentable')
            ->latest('id')
            ->get()
            ->map(fn (Comment $comment): AttentionItem => new AttentionItem(
                id: 'comment-'.$comment->id,
                kind: 'comment',
                icon: 'Comment01Icon',
                title: $comment->author_name.' commented on '.InteractionTarget::titleFor($comment->commentable),
                detail: 'First time they have written, so it is held until you say so.',
                body: PortableText::plainText($comment->body),
                age: $comment->created_at->diffForHumans(),
                href: $comment->commentable?->url() ?? '/hq',
                actions: [
                    ['label' => 'Approve', 'action' => 'approve', 'variant' => 'primary'],
                    ['label' => 'Reject', 'action' => 'spam', 'variant' => 'secondary'],
                ],
            ))
            ->all();
    }

    /**
     * @return list<AttentionItem>
     */
    private function mentions(): array
    {
        return Webmention::query()
            ->where('status', CommentStatus::Pending)
            ->whereNotNull('verified_at')
            ->with('target')
            ->latest('id')
            ->get()
            ->map(fn (Webmention $mention): AttentionItem => new AttentionItem(
                id: 'mention-'.$mention->id,
                kind: 'comment',
                icon: 'Link04Icon',
                title: ($mention->author_name ?: $mention->source_url).' linked to '.InteractionTarget::titleFor($mention->target),
                detail: 'From a site that has not been seen here before.',
                body: PortableText::plainText($mention->content ?? []),
                age: $mention->created_at->diffForHumans(),
                href: $mention->target?->url() ?? '/hq',
                actions: [
                    ['label' => 'Approve', 'action' => 'approve', 'variant' => 'primary'],
                    ['label' => 'Reject', 'action' => 'spam', 'variant' => 'secondary'],
                ],
            ))
            ->all();
    }
}
