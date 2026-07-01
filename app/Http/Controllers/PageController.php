<?php

namespace App\Http\Controllers;

use App\Content\ContentRepository;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

class PageController extends Controller
{
    /**
     * Render a Statamic pages-collection entry by slug. Drafts are visible
     * only to authenticated users; anyone else gets a 404.
     */
    public function show(string $slug, ContentRepository $content): Response
    {
        $page = $content->page($slug);

        if ($page === null || ($page->isDraft() && ! Auth::check())) {
            throw new NotFoundHttpException;
        }

        return Inertia::render('Page', [
            'title' => $page->title(),
            'excerpt' => $page->excerpt(),
            'bodyHtml' => $page->bodyHtml(),
            'og' => ['title' => $page->title(), 'description' => $page->excerpt()],
        ]);
    }
}
