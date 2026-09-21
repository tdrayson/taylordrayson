<?php

namespace App\Http\Controllers;

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

    public function __construct(private readonly RecentResponses $responses) {}

    public function __invoke(Request $request): Response
    {
        $clear = $request->query('preview') === 'clear';

        return Inertia::render('Hub', [
            'attention' => $clear ? [] : self::attention(),
            'responses' => ($this->responses)(self::RESPONSES),
            'entries' => self::entries(),
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

    /**
     * SCAFFOLDING: real figures, counted off timeline_entries. Replace with one
     * grouped query over that table, never over the models: a food row is an
     * item of food, while a food entry is a day of them.
     *
     * @return list<array<string, mixed>>
     */
    private static function entries(): array
    {
        return [
            ['label' => 'Food', 'icon' => 'UtensilsIcon', 'count' => 2554, 'lag' => '7 hours ago', 'synced' => true],
            ['label' => 'Places', 'icon' => 'Location01Icon', 'count' => 2481, 'lag' => 'yesterday', 'synced' => true],
            ['label' => 'Sleep', 'icon' => 'Moon02Icon', 'count' => 1782, 'lag' => '8 hours ago', 'synced' => true],
            ['label' => 'Activities', 'icon' => 'WorkoutRunIcon', 'count' => 1497, 'lag' => 'yesterday', 'synced' => true],
            ['label' => 'TV', 'icon' => 'TvMinimalPlayIcon', 'count' => 1393, 'lag' => '17 hours ago', 'synced' => true],
            ['label' => 'Podcast', 'icon' => 'PodcastIcon', 'count' => 258, 'lag' => 'a week ago', 'synced' => true],
            ['label' => 'Films', 'icon' => 'FlimSlateIcon', 'count' => 184, 'lag' => 'a week ago', 'synced' => true],
            ['label' => 'Books', 'icon' => 'BookOpen01Icon', 'count' => 0, 'lag' => 'nothing published', 'synced' => true],

            ['label' => 'Fuel', 'icon' => 'PetrolPumpIcon', 'count' => 126, 'lag' => '3 weeks ago', 'synced' => false],
            ['label' => 'Events', 'icon' => 'Ticket01Icon', 'count' => 88, 'lag' => '2 weeks ago', 'synced' => false],
            ['label' => 'Flights', 'icon' => 'Airplane01Icon', 'count' => 75, 'lag' => '3 months ago', 'synced' => false],
            ['label' => 'Notes', 'icon' => 'StickyNote02Icon', 'count' => 18, 'lag' => '51 minutes ago', 'synced' => false],
            ['label' => 'Articles', 'icon' => 'File01Icon', 'count' => 6, 'lag' => 'a month ago', 'synced' => false],
            ['label' => 'Appearances', 'icon' => 'Mic01Icon', 'count' => 5, 'lag' => '3 months ago', 'synced' => false],
            ['label' => 'Projects', 'icon' => 'RocketIcon', 'count' => 0, 'lag' => 'nothing yet', 'synced' => false],
        ];
    }
}
