<?php

namespace App\Actions\Notes;

use App\Models\Note;

class DeleteNote
{
    public function __invoke(Note $note): void
    {
        $note->delete();
    }
}
