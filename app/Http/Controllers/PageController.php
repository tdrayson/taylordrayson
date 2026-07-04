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
            'title' => $page->title,
            'excerpt' => $page->excerpt,
            'content' => $page->content,
            'published' => $page->published,
            'og' => ['title' => $page->title, 'description' => $page->excerpt],
        ]);
    }
}
