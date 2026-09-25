<?php

namespace App\Actions\Subjects;

use Illuminate\Database\Eloquent\Model;

/**
 * Replaces an entry's direct subjects wholesale, matching how the picker
 * always submits the entry's full tag list rather than one change at a time.
 */
final class SyncEntrySubjects
{
    /**
     * @param  list<int>  $subjectIds
     */
    public function __invoke(Model $entry, array $subjectIds): void
    {
        $entry->subjects()->sync($subjectIds);
    }
}
