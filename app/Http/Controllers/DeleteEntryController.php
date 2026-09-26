<?php

namespace App\Http\Controllers;

use App\Enums\EntryStatus;
use App\Fields\AuthorableTypes;
use App\Support\TypeCatalogue;
use Illuminate\Http\RedirectResponse;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * Deletes a hand-authored entry of any type. A draft has nowhere to show, so
 * it returns to /drafts; anything else returns to its type's archive.
 */
class DeleteEntryController extends Controller
{
    public function __invoke(string $type, int $id): RedirectResponse
    {
        $definition = AuthorableTypes::get($type);

        if ($definition === null) {
            throw new NotFoundHttpException;
        }

        $model = $definition['model']::query()->findOrFail($id);
        $wasDraft = $model->getAttribute('status') === EntryStatus::Draft;

        app($definition['delete'])($model);

        return redirect($wasDraft ? '/drafts' : (TypeCatalogue::for($type)?->href ?? '/'));
    }
}
