<?php

namespace App\Http\Controllers;

use App\Actions\AttachedMediaValues;
use App\Actions\BuildLinkFavicons;
use App\Actions\BuildLinkPreviews;
use App\Enums\EntryStatus;
use App\Fields\FieldRegistry;
use App\Models\Page;
use App\Presenters\Conversation;
use App\Support\OgMeta;
use App\Support\PortableText;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;
use Symfony\Component\HttpFoundation\Response as SymfonyResponse;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

class PageController extends Controller
{
    /**
     * Render a content page by slug: drafts for the owner only, private ones behind a password.
     */
    public function show(string $slug): SymfonyResponse
    {
        $page = Page::query()->where('slug', $slug)->viewableBy(Auth::user())->first();

        if ($page === null) {
            throw new NotFoundHttpException;
        }

        $locked = ! $page->isUnlockedFor(request());
        $fields = Auth::check() ? FieldRegistry::for($page) : [];

        $response = Inertia::render('Page', [
            'id' => $page->id,
            // ?edit opens the editor in place. Only ever honoured for a
            // signed-in visitor; the save route enforces it again server-side.
            'editing' => Auth::check() && request()->has('edit'),
            'title' => $page->title,
            'excerpt' => $page->excerpt,
            'cover' => $locked ? null : $page->coverPhoto(),
            'og' => OgMeta::page($page->title, $page->excerpt, PortableText::plainText($page->content), $page->status),
            'locked' => $locked,
            'unlockUrl' => $locked ? route('unlock', ['dataset' => 'page', 'id' => $page->id], false) : null,
            ...($locked ? [] : [
                // Same as an entry: server-rendered so it is readable and
                // parseable without JS. This is also what makes a guestbook page
                // work, being a page like any other.
                'conversation' => Conversation::shownFor($page, request()),
                'fields' => $fields,
                // Taken from the field list rather than named one by one: a
                // field the editor offers but has no value for saves back as
                // empty, and for the slug that means a page that will not
                // save at all. Attributes plus what the media fields already
                // hold: a cover is a Media Library collection rather than a
                // column, so only() cannot see it and the picker opened
                // empty over an attached image.
                'values' => [
                    ...$page->only(array_diff(array_column($fields, 'name'), ['password'])),
                    ...(Auth::check() ? ['password' => $page->password] : []),
                    ...app(AttachedMediaValues::class)($page, $fields),
                ],
                'content' => $page->content,
                'linkPreviews' => app(BuildLinkPreviews::class)($page->content),
                'linkFavicons' => (new BuildLinkFavicons)($page->content),
            ]),
        ])->toResponse(request());

        // A private page differs per session, so no shared cache may keep it.
        if ($page->status === EntryStatus::Private) {
            $response->headers->set('Cache-Control', 'private, no-store');
        }

        return $response;
    }
}
