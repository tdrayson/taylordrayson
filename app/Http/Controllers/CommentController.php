<?php

namespace App\Http\Controllers;

use App\Actions\Comments\NotifyOfReply;
use App\Actions\Comments\StoreComment;
use App\Enums\CommentStatus;
use App\Http\Requests\Interactions\StoreCommentRequest;
use App\Models\Comment;
use App\Services\Pushover\Client as Pushover;
use App\Support\FormNonce;
use App\Support\InteractionTarget;
use Illuminate\Http\JsonResponse;

class CommentController extends Controller
{
    /**
     * Issue a token when someone starts filling the form in, rather than with
     * every page render: only a fraction of readers ever comment, and this way
     * only they cost a cache write.
     */
    public function token(): JsonResponse
    {
        return response()->json(['nonce' => FormNonce::issue(StoreComment::NONCE_PURPOSE)]);
    }

    public function store(StoreCommentRequest $request, string $type, int $id): JsonResponse
    {
        $target = InteractionTarget::resolve($type, $id);

        abort_if($target === null, 404);

        $comment = app(StoreComment::class)($target, $request->submission());

        // Spam is filed, not announced: pinging a phone for it would undo the
        // point of catching it.
        if ($comment !== null && $comment->status !== CommentStatus::Spam) {
            $this->notify($comment);
        }

        // A reply that skipped the queue still owes the person it answers an
        // email. Only the moderation path used to send one.
        if ($comment !== null) {
            app(NotifyOfReply::class)($comment);
        }

        // A dropped bot submission answers exactly as a held one does, so
        // nothing on the other end learns which check it failed.
        return response()->json([
            'status' => ($comment?->status ?? CommentStatus::Pending)->value,
        ], 201);
    }

    /**
     * A held comment is invisible until it is read, so it needs to say so out
     * loud or it waits forever. After the response, so a slow Pushover never
     * becomes a slow comment box.
     */
    private function notify(Comment $comment): void
    {
        $held = $comment->status === CommentStatus::Pending;
        $title = $held ? 'Comment held for moderation' : 'New comment';
        $body = "{$comment->author_name}: ".str($comment->body)->limit(120);

        dispatch(fn () => app(Pushover::class)->send($title, $body))->afterResponse();
    }
}
