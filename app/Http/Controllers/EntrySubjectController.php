<?php

namespace App\Http\Controllers;

use App\Actions\Subjects\SyncEntrySubjects;
use App\Fields\FieldRegistry;
use App\Http\Requests\EntrySubjectsRequest;
use App\Timeline\TypeRegistry;
use Illuminate\Http\RedirectResponse;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * Tags subjects onto any entry, synced ones included. Deliberately bypasses
 * {@see FieldRegistry}, which throws for a type with no authored
 * fields, exactly the Strava activities and Swarm check-ins that most need
 * tagging. The model comes from {@see TypeRegistry} rather than a hand-written
 * match, so a new timeline type needs no change here.
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
