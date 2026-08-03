<?php

namespace App\Http\Controllers;

use App\Queries\MentionSearch;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Candidates for the editor's @-mention menu.
 *
 * Session-guarded rather than token-guarded: the only caller is the editor,
 * which runs in an authenticated browser. It is also why it returns drafts —
 * a draft is exactly the thing you want to point at from another draft.
 */
class MentionSearchController extends Controller
{
    public function __invoke(Request $request, MentionSearch $search): JsonResponse
    {
        return response()->json([
            'data' => $search(trim((string) $request->query('q', ''))),
        ]);
    }
}
