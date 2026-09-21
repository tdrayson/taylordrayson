<?php

namespace App\Http\Controllers;

use App\Queries\Hub\EntryCounts;
use App\Queries\Hub\RecentResponses;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * SCAFFOLDING. Every figure below is a fixture, written to match real rows so
 * the page can be judged before the queries behind it exist. Replace wholesale
 * with App\Queries\Hub\* once the shape is agreed.
 *
 * ?preview=clear renders the zero state, which is the one worth getting right.
 */
class HubController extends Controller
{
    /** The hub is a glance, not a record: only the latest few. */
    private const RESPONSES = 6;

    public function __construct(
        private readonly RecentResponses $responses,
        private readonly EntryCounts $entries,
    ) {}

    public function __invoke(Request $request): Response
    {
        $clear = $request->query('preview') === 'clear';

        return Inertia::render('Hub', [
            'attention' => $clear ? [] : self::attention(),
            'responses' => ($this->responses)(self::RESPONSES),
            'entries' => ($this->entries)(),
        ]);
    }

    /**
     * @return list<array<string, mixed>>
     */
    private static function attention(): array
    {
        return [
            [
                'id' => 'comment-1',
                'kind' => 'comment',
                'icon' => 'Comment01Icon',
                'title' => 'Jo Bloggs commented on Rebuilding the timeline',
                'detail' => 'First time they have written, so it is held until you say so.',
                'body' => 'Been following this rebuild since the Statamic post. Curious how you landed on Portable Text over plain markdown for the articles?',
                'age' => '2 hours ago',
                'href' => '/articles/rebuilding-the-timeline',
                'actions' => [
                    ['label' => 'Approve', 'action' => 'approve', 'variant' => 'primary'],
                    ['label' => 'Reject', 'action' => 'spam', 'variant' => 'secondary'],
                ],
            ],
            [
                'id' => 'books-draft',
                'kind' => 'draft',
                'icon' => 'BookOpen01Icon',
                'title' => 'Two books are waiting on a title and a cover',
                'detail' => 'Sideloaded to the Kindle 5 days ago, so nothing could be looked up.',
                'age' => '5 days ago',
                'href' => '/drafts',
                'actions' => [],
            ],
            [
                'id' => 'failed-jobs',
                'kind' => 'failure',
                'icon' => 'Alert02Icon',
                'title' => 'Two image conversions failed',
                'detail' => 'Could not create a directory on disk, so those thumbnails were never made.',
                'age' => '13 September',
                'href' => '/hq',
                'actions' => [
                    ['label' => 'Retry', 'action' => 'retry', 'variant' => 'secondary'],
                ],
            ],
            [
                'id' => 'stale-drafts',
                'kind' => 'draft',
                'icon' => 'File02Icon',
                'title' => 'An article and a note are still in draft',
                'detail' => 'Both edited in the last month.',
                'age' => '3 weeks ago',
                'href' => '/drafts',
                'actions' => [],
            ],
        ];
    }
}
