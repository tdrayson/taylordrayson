<?php

namespace App\Http\Controllers;

use App\Enums\EntryStatus;
use App\Http\Responses\ExportResponse;
use App\Models\Page;
use App\Presenters\ExportPresenter;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/** A content page in another format. Visibility mirrors PageController::show() exactly. */
class PageExportController extends Controller
{
    public function __invoke(string $slug, string $format): Response
    {
        $page = Page::query()->where('slug', $slug)->viewableBy(Auth::user())->first();

        if ($page === null) {
            throw new NotFoundHttpException;
        }

        // A locked page has nothing to say in any format: even the title is
        // withheld on the page, so there is no header left to publish.
        if (! $page->isUnlockedFor(request())) {
            throw new NotFoundHttpException;
        }

        return ExportResponse::make(
            ExportPresenter::for($page),
            $format,
            $page->status === EntryStatus::Private,
        );
    }
}
