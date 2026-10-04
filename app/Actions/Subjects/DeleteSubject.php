<?php

namespace App\Actions\Subjects;

use App\Models\Subject;

class DeleteSubject
{
    /**
     * The `subjectables` and `attachment_subject` foreign keys cascade on
     * delete, so this alone removes every entry link and photo tag while
     * leaving the entries and photographs themselves untouched.
     */
    public function __invoke(Subject $subject): void
    {
        $subject->delete();
    }
}
