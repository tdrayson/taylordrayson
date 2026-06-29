<?php

namespace App\Http\Controllers;

use App\Models\Page;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

class PageController extends Controller
{
    /**
     * Render a CP-managed content page by slug. Drafts are visible only to
     * authenticated (CP) users; anyone else gets a 404.
     */
    public function show(string $slug): Response
    {
        $page = Page::query()->where('slug', $slug)->first();

        if ($page === null || ($page->draft && ! Auth::check())) {
            throw new NotFoundHttpException;
        }

        return Inertia::render('Page', [
            'title' => $page->title,
            'excerpt' => $page->excerpt,
            'content' => $page->content,
            'draft' => $page->draft,
            'og' => ['title' => $page->title, 'description' => $page->excerpt],
        ]);
    }
}
