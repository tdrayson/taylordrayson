<?php

namespace App\Http\Controllers;

use App\Data\ExportData;
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

        $data = ExportPresenter::for($page);

        // A locked page publishes its header and nothing else, the same as
        // the page it mirrors holds its body back.
        if (! $page->isUnlockedFor(request())) {
            $data = new ExportData(
                type: $data->type,
                url: $data->url,
                title: $data->title,
                summary: null,
                occurred: $data->occurred,
                fields: [],
                links: [],
                locked: true,
            );
        }

        return ExportResponse::make($data, $format, $page->status === EntryStatus::Private);
    }
}
