<?php

namespace App\Observers;

use App\Actions\Mentions\SyncMentions;
use App\Models\Mention;
use Illuminate\Database\Eloquent\Model;

/**
 * Keeps an entry's mentions in step with the links in its body, without anyone
 * remembering to run a command. Attached to the types you write by hand.
 */
class MentionObserver
{
    public function __construct(private readonly SyncMentions $sync) {}

    public function saved(Model $model): void
    {
        ($this->sync)($model);
    }

    /**
     * The rows this entry wrote, cleared before it goes.
     *
     * The ones pointing *at* it are cleared by HasInteractions, alongside the
     * comments and webmentions it was the target of.
     */
    public function deleting(Model $model): void
    {
        Mention::query()
            ->where('source_type', $model->getMorphClass())
            ->where('source_id', $model->getKey())
            ->delete();
    }
}
