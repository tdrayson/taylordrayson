<?php

namespace App\Console\Commands;

use App\Models\Event;
use App\Models\Tag;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Str;

#[Signature('events:tag-from-type')]
#[Description('Seed each event\'s category tag from its legacy `type` value (theatre, gig, festival, ...) so events join the cross-type /tags pages. Idempotent and additive; re-run after re-importing events.csv.')]
class TagEventsFromType extends Command
{
    /**
     * Attach a category tag mirroring each event's `type`, sharing the tag with
     * any note/article/project already using it. Additive (syncWithoutDetaching)
     * so it never removes tags added by other means, and idempotent so it is
     * safe to re-run after events.csv is re-imported (which drops event rows).
     *
     * @return int The command exit code.
     */
    public function handle(): int
    {
        $tagged = 0;

        Event::query()->whereNotNull('type')->where('type', '!=', '')->lazyById()->each(function (Event $event) use (&$tagged): void {
            $tag = Tag::query()->firstOrCreate(
                ['slug' => Str::slug($event->type)],
                ['name' => Str::headline($event->type)],
            );

            $event->tags()->syncWithoutDetaching([$tag->id]);
            $tagged++;
        });

        $this->info("Tagged {$tagged} events from their type.");

        return self::SUCCESS;
    }
}
