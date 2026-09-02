<?php

namespace App\Http\Controllers;

use App\Actions\AttachedMediaValues;
use App\Actions\Subjects\DeleteSubject;
use App\Actions\Subjects\UpsertSubject;
use App\Actions\SyncEntryMedia;
use App\Data\SubjectData;
use App\Enums\SubjectKind;
use App\Fields\FieldRegistry;
use App\Http\Requests\SubjectRequest;
use App\Models\Subject;
use App\Queries\SubjectCompanions;
use App\Queries\SubjectFeed;
use App\Queries\SubjectPhotos;
use App\Queries\SubjectStats;
use App\Support\OgMeta;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;
use Inertia\Response;

class SubjectController extends Controller
{
    public function __construct(
        private readonly SubjectFeed $feed,
        private readonly SubjectPhotos $photos,
        private readonly SubjectStats $stats,
        private readonly SubjectCompanions $companions,
    ) {}

    /**
     * A subject's page: everything it appears in and every photograph it is
     * tagged in. The subject itself existing is enough to render; an empty
     * feed is a valid state; a subject or kind mismatch is not.
     */
    public function show(string $kind, string $slug): Response
    {
        $subjectKind = SubjectKind::fromSegment($kind);

        abort_if($subjectKind === null, 404);

        $subject = Subject::query()->where('kind', $subjectKind)->where('slug', $slug)->first();

        abort_if($subject === null, 404);

        $fields = Auth::check() ? FieldRegistry::for($subject) : [];

        return Inertia::render('Life/Subject', [
            'subject' => SubjectData::from($subject)->toArray(),
            'groups' => ($this->feed)($subject),
            'photos' => ($this->photos)($subject),
            'stats' => ($this->stats)($subject),
            'companions' => ($this->companions)($subject)->map(fn (Subject $companion): array => [
                'name' => $companion->name,
                'url' => $companion->url(),
                'cover' => $companion->coverPhoto(),
            ])->all(),
            // ?edit opens the editor in place, the same pattern as a page.
            // Only ever honoured for a signed-in visitor; the write routes
            // enforce it again server-side via the `auth` middleware group.
            'editing' => Auth::check() && request()->has('edit'),
            'fields' => $fields,
            'values' => [
                ...$subject->only(array_column($fields, 'name')),
                ...app(AttachedMediaValues::class)($subject, $fields),
            ],
            'og' => OgMeta::subject($subject),
        ]);
    }

    public function store(SubjectRequest $request, UpsertSubject $upsert): RedirectResponse|JsonResponse
    {
        $attributes = $request->validated();

        $subject = $upsert(null, Arr::except($attributes, ['cover']));

        app(SyncEntryMedia::class)($subject, FieldRegistry::for($subject), $attributes);

        // The entry picker's one-click create posts here off its own fetch, not
        // an Inertia visit, and needs the new row back to attach without navigating away.
        if ($request->wantsJson()) {
            return response()->json(['data' => [
                'id' => $subject->id,
                'name' => $subject->name,
                'kind' => $subject->kind->label(),
            ]]);
        }

        return redirect($subject->url());
    }

    public function update(SubjectRequest $request, Subject $subject, UpsertSubject $upsert): RedirectResponse
    {
        $attributes = $request->validated();

        $subject = $upsert($subject, Arr::except($attributes, ['cover']));

        app(SyncEntryMedia::class)($subject, FieldRegistry::for($subject), $attributes);

        return redirect($subject->url().'?edit');
    }

    public function destroy(Subject $subject, DeleteSubject $delete): RedirectResponse
    {
        $delete($subject);

        return redirect('/life');
    }
}
