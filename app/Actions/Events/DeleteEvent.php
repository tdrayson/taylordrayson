<?php

namespace App\Actions\Events;

use App\Models\Event;

class DeleteEvent
{
    public function __invoke(Event $event): void
    {
        $event->delete();
    }
}
