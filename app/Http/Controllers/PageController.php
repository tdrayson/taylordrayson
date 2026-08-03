<?php

namespace App\Http\Controllers;

use App\Actions\BuildLinkPreviews;
use App\Actions\Pages\UpdatePage;
use App\Actions\ResolveMentions;
use App\Fields\FieldRegistry;
use App\Http\Requests\UpdatePageRequest;
use App\Models\Page;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

class PageController extends Controller
{
    /**
     * A plain index of every standalone page (unpublished ones only for
     * authenticated visitors).
     */
    public function index(): Response
    {
        return Inertia::render('Pages', [
            'pages' => Page::query()
                ->when(! Auth::check(), fn ($query) => $query->where('published', true))
                ->orderBy('title')
                ->get(['title', 'slug'])
                ->map(fn (Page $page): array => [
                    'title' => $page->title,
                    'href' => '/'.$page->slug,
                ])
                ->all(),
        ]);
    }

    /**
     * Render a content page by slug. Unpublished pages are visible only to
     * authenticated users; anyone else gets a 404.
     */
    public function show(string $slug): Response
    {
        $page = Page::query()->where('slug', $slug)->first();

        if ($page === null || (! $page->published && ! Auth::check())) {
            throw new NotFoundHttpException;
        }

        return Inertia::render('Page', [
            'id' => $page->id,
            // ?edit opens the editor in place. Only ever honoured for a
            // signed-in visitor; the save route enforces it again server-side.
            'editing' => Auth::check() && request()->has('edit'),
            'fields' => Auth::check() ? FieldRegistry::for($page) : [],
            'title' => $page->title,
            'excerpt' => $page->excerpt,
            'content' => $page->content,
            'published' => $page->published,
            'og' => ['title' => $page->title, 'description' => $page->excerpt],
            'linkPreviews' => (new BuildLinkPreviews)($page->content),
            'mentions' => (new ResolveMentions)($page->content),
        ]);
    }

    /**
     * Save an edit made in place. Guarded by the auth middleware on the route;
     * the editing flag on show() only decides whether the UI is offered.
     */
    public function update(UpdatePageRequest $request, Page $page, UpdatePage $updatePage): RedirectResponse
    {
        $updatePage($page, $request->validated());

        return back();
    }
}
