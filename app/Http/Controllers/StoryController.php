<?php

namespace App\Http\Controllers;

use App\Presenters\Heads\SiteHeads;
use App\Stories\Story;
use App\Stories\StoryRegistry;
use App\Support\Head;
use Inertia\Inertia;
use Inertia\Response;

class StoryController extends Controller
{
    public function __construct(private readonly StoryRegistry $registry) {}

    /**
     * The archive listing of every data story.
     */
    public function index(): Response
    {
        app(Head::class)->set(SiteHeads::stories());

        return Inertia::render('Stories/Index', [
            'stories' => array_map(fn (Story $story): array => $story->card(), $this->registry->all()),
        ]);
    }

    /**
     * Render a data story by slug, 404-ing on an unknown one.
     */
    public function show(string $story): Response
    {
        $found = $this->registry->find($story);

        abort_if($found === null, 404);

        app(Head::class)->set($found->head());

        return Inertia::render($found->component(), [
            'story' => $found->build(),
        ]);
    }
}
