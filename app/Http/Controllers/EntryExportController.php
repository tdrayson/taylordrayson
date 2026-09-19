<?php

namespace App\Http\Controllers;

use App\Data\ExportData;
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

        $data = ExportPresenter::for($model);

        // A locked entry publishes its header and nothing else, the same as
        // the page holds its body back.
        if (! $model->isUnlockedFor(request())) {
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

        return ExportResponse::make($data, $format, $model->status === EntryStatus::Private);
    }
}
