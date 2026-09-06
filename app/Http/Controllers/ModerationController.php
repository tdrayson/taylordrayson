<?php

namespace App\Http\Controllers;

use App\Actions\Comments\NotifyOfReply;
use App\Enums\CommentStatus;
use App\Models\Comment;
use App\Models\Webmention;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;

/**
 * The queue for anything held back: first comments, and mentions from a site
 * that has not been seen before.
 *
 * Spam is listed too, but separately and last, so a false positive can be
 * released rather than being invisible.
 */
class ModerationController extends Controller
{
    public function index(): Response
    {
        return Inertia::render('Moderation', [
            'pending' => [
                'comments' => self::comments(CommentStatus::Pending),
                'mentions' => self::mentions(CommentStatus::Pending),
            ],
            'spam' => ['comments' => self::comments(CommentStatus::Spam)],
        ]);
    }

    /**
     * Approve, mark as spam, or delete. A redirect rather than JSON, so the
     * queue reloads and the row leaves the list on its own.
     */
    public function update(string $kind, int $id, string $action): RedirectResponse
    {
        $model = $kind === 'comment'
            ? Comment::query()->find($id)
            : Webmention::query()->find($id);

        abort_if($model === null, 404);

        match ($action) {
            'approve' => $this->approve($model),
            'spam' => $model->update(['status' => CommentStatus::Spam]),
            'delete' => $model->delete(),
            default => abort(404),
        };

        return back();
    }

    /**
     * Approving is also what sends a reply notification: never on submission,
     * or moderating would forward every spam comment to a stranger's inbox.
     */
    private function approve(Model $model): void
    {
        $wasPending = $model->status !== CommentStatus::Approved;

        $model->update(['status' => CommentStatus::Approved]);

        if (! $wasPending || ! $model instanceof Comment) {
            return;
        }

        app(NotifyOfReply::class)($model);
    }

    /**
     * @return list<array<string, mixed>>
     */
    private static function comments(CommentStatus $status): array
    {
        return Comment::query()
            ->where('status', $status)
            ->with('commentable')
            ->latest('id')
            ->get()
            ->map(fn (Comment $comment): array => [
                'id' => $comment->id,
                'kind' => 'comment',
                'author' => $comment->author_name,
                // Shown so a name can be recognised, never rendered publicly.
                'email' => $comment->author_email,
                'body' => $comment->body,
                'on' => self::targetUrl($comment->commentable),
                'received' => $comment->created_at->diffForHumans(),
            ])
            ->all();
    }

    /**
     * @return list<array<string, mixed>>
     */
    private static function mentions(CommentStatus $status): array
    {
        return Webmention::query()
            ->where('status', $status)
            ->whereNotNull('verified_at')
            ->with('target')
            ->latest('id')
            ->get()
            ->map(fn (Webmention $mention): array => [
                'id' => $mention->id,
                'kind' => 'mention',
                'author' => $mention->author_name ?: $mention->source_url,
                'email' => null,
                'body' => $mention->content,
                'sourceUrl' => $mention->source_url,
                'on' => self::targetUrl($mention->target),
                'received' => $mention->created_at->diffForHumans(),
            ])
            ->all();
    }

    private static function targetUrl(?Model $target): ?string
    {
        return $target === null ? null : $target->url();
    }
}
