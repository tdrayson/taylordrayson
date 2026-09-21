<?php

namespace App\Http\Controllers;

use App\Datasets\Datasets;
use App\Enums\EntryStatus;
use App\Http\Responses\ExportResponse;
use App\Presenters\ExportPresenter;
use App\Queries\EntryAtUrl;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/** An entry in another format. Visibility mirrors the entry page exactly. */
class EntryExportController extends Controller
{
    public function __construct(private readonly EntryAtUrl $entryAtUrl) {}

    public function __invoke(int $year, int $month, int $day, string $slug, string $format): Response
    {
        $model = ($this->entryAtUrl)($year, $month, $day, $slug);

        if ($model === null || ! $model->isViewableBy(Auth::user())) {
            throw new NotFoundHttpException;
        }

        if (Datasets::forModel($model) === null) {
            throw new NotFoundHttpException;
        }

        // A locked entry has nothing to say in any format: even the title is
        // withheld on the page, so there is no header left to publish.
        if (! $model->isUnlockedFor(request())) {
            throw new NotFoundHttpException;
        }

        return ExportResponse::make(
            ExportPresenter::for($model),
            $format,
            $model->status === EntryStatus::Private,
        );
    }
}
