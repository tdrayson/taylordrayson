<?php

namespace App\Http\Controllers;

use App\Actions\Hub\MarkSeen;
use App\Queries\Hub\EntryCounts;
use App\Queries\Hub\NeedsAttention;
use App\Queries\Hub\RecentResponses;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * The back-of-house overview: what needs a decision, who has responded, and how
 * much of each kind of data there is.
 */
class HubController extends Controller
{
    /** The hub is a glance, not a record: only the latest few. */
    private const RESPONSES = 6;

    public function __construct(
        private readonly NeedsAttention $attention,
        private readonly RecentResponses $responses,
        private readonly EntryCounts $entries,
        private readonly MarkSeen $markSeen,
    ) {}

    public function __invoke(Request $request): Response
    {
        $user = $request->user();
        $seenAt = $this->markSeen->previous($user);

        $props = [
            'attention' => ($this->attention)(),
            'responses' => ($this->responses)(self::RESPONSES, $seenAt),
            'entries' => ($this->entries)(),
        ];

        // Moved only once the page above built without error, so a failed
        // query leaves the stamp where it was and the next attempt still
        // sees the same "new since" line.
        ($this->markSeen)($user);

        return Inertia::render('Hub', $props);
    }
}
