<?php

namespace App\Http\Controllers;

use App\Http\Responses\ExportResponse;
use App\Presenters\Exports\NowExport;
use Illuminate\Http\Response;

/** The /now dashboard in another format. No model, and no visibility rules: /now has none either. */
class NowExportController extends Controller
{
    public function __invoke(string $format): Response
    {
        return ExportResponse::make((new NowExport)->present(), $format);
    }
}
