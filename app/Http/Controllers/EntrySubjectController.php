<?php

namespace App\Http\Controllers;

use App\Actions\Subjects\SyncEntrySubjects;
use App\Fields\FieldRegistry;
use App\Http\Requests\EntrySubjectsRequest;
use App\Timeline\TypeRegistry;
use Illuminate\Http\RedirectResponse;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * Tags subjects onto any entry, synced ones included, bypassing
 * {@see FieldRegistry} on purpose. See the commit message for why.
 */
class EntrySubjectController extends Controller
{
    public function __invoke(EntrySubjectsRequest $request, string $type, int $id, SyncEntrySubjects $sync): RedirectResponse
    {
        $definition = TypeRegistry::find($type);

        if ($definition === null) {
            throw new NotFoundHttpException;
        }

        $entry = $definition['model']::query()->findOrFail($id);

        $sync($entry, $request->validated('subjects'));

        return back();
    }
}
