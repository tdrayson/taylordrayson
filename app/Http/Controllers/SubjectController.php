<?php

namespace App\Http\Controllers;

use App\Data\SubjectData;
use App\Enums\SubjectKind;
use App\Models\Subject;
use App\Queries\SubjectCompanions;
use App\Queries\SubjectFeed;
use App\Queries\SubjectPhotos;
use App\Queries\SubjectStats;
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
        ]);
    }
}
