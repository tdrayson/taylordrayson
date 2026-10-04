<?php

namespace App\Http\Controllers;

use App\Enums\LinkPage;
use App\Presenters\LinkPagePresenter;
use Inertia\Inertia;
use Inertia\Response;

class LinkPageController extends Controller
{
    public function __invoke(LinkPage $page, LinkPagePresenter $presenter): Response
    {
        return Inertia::render($page->component(), ['card' => $presenter->page($page)])
            ->rootView('link-page');
    }
}
