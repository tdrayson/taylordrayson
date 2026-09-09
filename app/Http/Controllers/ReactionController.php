<?php

namespace App\Http\Controllers;

use App\Actions\Reactions\ToggleReaction;
use App\Http\Requests\Interactions\StoreReactionRequest;
use App\Queries\ReactionsFor;
use App\Support\InteractionTarget;
use App\Support\VisitorIdentity;
use Illuminate\Http\JsonResponse;

class ReactionController extends Controller
{
    /**
     * Toggle the visitor's reaction and return the whole refreshed bar, so a
     * click that raced another visitor's still lands on the true counts.
     */
    public function store(StoreReactionRequest $request, string $type, int $id): JsonResponse
    {
        $target = InteractionTarget::resolve($type, $id);

        abort_if($target === null, 404);

        $identity = VisitorIdentity::onTarget($request, $target);

        $on = app(ToggleReaction::class)($target, $request->reactionType(), $identity);

        return response()->json([
            'reactions' => app(ReactionsFor::class)($target, $identity),
            'on' => $on,
        ]);
    }
}
