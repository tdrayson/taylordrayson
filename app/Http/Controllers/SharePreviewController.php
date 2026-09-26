<?php

namespace App\Http\Controllers;

use App\Actions\Og\PreviewShareCard;
use App\Fields\AuthorableTypes;
use App\Http\Requests\SharePreviewRequest;
use Illuminate\Http\JsonResponse;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Throwable;

/** The editor's preview of an entry's share card, drawn from its unsaved values. */
class SharePreviewController extends Controller
{
    public function __invoke(SharePreviewRequest $request, string $type, PreviewShareCard $preview): JsonResponse
    {
        $class = AuthorableTypes::get($type)['model'] ?? throw new NotFoundHttpException;
        $id = $request->validated('id');
        $model = $id === null ? new $class : $class::query()->findOrFail($id);

        try {
            $card = $preview($model, $request->except('id'));
        } catch (Throwable $exception) {
            report($exception);

            return response()->json(['message' => 'Could not draw the card from these values.'], 422);
        }

        return response()->json($card)->header('Cache-Control', 'no-store');
    }
}
