<?php

namespace App\Http\Controllers;

use App\Actions\AttachedMediaValues;
use App\Actions\BuildLinkFavicons;
use App\Actions\BuildLinkPreviews;
use App\Actions\Files\BuildFileReleases;
use App\Fields\FieldRegistry;
use App\Models\Page;
use App\Support\OgMeta;
use App\Support\PortableText;
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

        $fields = Auth::check() ? FieldRegistry::for($page) : [];

        return Inertia::render('Page', [
            'id' => $page->id,
            // ?edit opens the editor in place. Only ever honoured for a
            // signed-in visitor; the save route enforces it again server-side.
            'editing' => Auth::check() && request()->has('edit'),
            'fields' => $fields,
            // Taken from the field list rather than named one by one: a field
            // the editor offers but has no value for saves back as empty, and
            // for the slug that means a page that will not save at all.
            // Attributes plus what the media fields already hold: a cover is a
            // Media Library collection rather than a column, so only() cannot
            // see it and the picker opened empty over an attached image.
            'values' => [
                ...$page->only(array_column($fields, 'name')),
                ...app(AttachedMediaValues::class)($page, $fields),
            ],
            'title' => $page->title,
            'excerpt' => $page->excerpt,
            'cover' => $page->coverPhoto(),
            'content' => $page->content,
            'published' => $page->published,
            'og' => OgMeta::page($page->title, $page->excerpt, PortableText::plainText($page->content)),
            'linkPreviews' => app(BuildLinkPreviews::class)($page->content),
            'linkFavicons' => (new BuildLinkFavicons)($page->content),
            'fileReleases' => app(BuildFileReleases::class)($page->content),
        ]);
    }
}
