<?php

namespace App\Cp\Resources;

use App\Cp\TimelineCpResource;
use App\Models\Note;

class NoteResource extends TimelineCpResource
{
    public function model(): string
    {
        return Note::class;
    }

    public function slug(): string
    {
        return 'notes';
    }

    public function label(): string
    {
        return 'Note';
    }

    public function pluralLabel(): string
    {
        return 'Notes';
    }
}
