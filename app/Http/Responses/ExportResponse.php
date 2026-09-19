<?php

namespace App\Http\Responses;

use App\Data\ExportData;
use App\Enums\ExportFormat;
use App\Presenters\Exports\Formats\Formats;
use Illuminate\Http\Response;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/** Renders an export in one format, with the trail listing the others. */
final class ExportResponse
{
    public static function make(ExportData $data, string $extension, bool $private = false): Response
    {
        $format = ExportFormat::tryFrom($extension) ?? throw new NotFoundHttpException;
        $available = Formats::for($data);
        $renderer = $available[$format->value] ?? throw new NotFoundHttpException;

        $trail = [];

        foreach (array_keys($available) as $key) {
            if ($key !== $format->value) {
                $trail[$key] = $data->url.'.'.$key;
            }
        }

        $response = response($renderer->render($data, $trail), 200, [
            'Content-Type' => $format->contentType(),
        ]);

        // A private page differs per session, so no shared cache may keep it.
        if ($private) {
            $response->headers->set('Cache-Control', 'private, no-store');
        }

        return $response;
    }
}
