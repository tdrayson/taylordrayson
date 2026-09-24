<?php

namespace App\Http\Controllers;

use App\Models\Comment;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Stops reply notifications for the address on one comment.
 *
 * Reached only through a signed URL in the mail itself, so it needs no login
 * and no token column: the signature is the proof.
 */
class UnsubscribeController extends Controller
{
    public function __invoke(Request $request, int $comment): Response
    {
        $found = Comment::query()->find($comment);

        abort_if($found === null, 404);

        // Stamped rather than blanking the address, so a reply already queued
        // still knows who it was for and simply does not send.
        $found->update(['unsubscribed_at' => now(), 'notify_replies' => false]);

        return Inertia::render('Unsubscribed');
    }
}
