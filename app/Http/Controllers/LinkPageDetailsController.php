<?php

namespace App\Http\Controllers;

use App\Enums\LinkPage;
use Inertia\Inertia;
use Inertia\Response;

class LinkPageDetailsController extends Controller
{
    public function __invoke(LinkPage $page): Response
    {
        abort_unless(config('profile.details_form'), 404);

        return Inertia::render($page->detailsComponent(), [
            'backHref' => route('link-page.show', $page, absolute: false),
            'contactHref' => route('link-page.contact', $page, absolute: false),
        ])->rootView('link-page');
    }
}
