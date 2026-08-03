<?php

use App\Models\Event;
use App\Models\Tag;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

return new class extends Migration
{
    /**
     * The event category now lives in the relational tag table (like notes,
     * articles, and projects), so the legacy `type` column is dropped.
     *
     * Each event's category is first preserved as a tag, so any database still
     * carrying `type` values keeps its taxonomy. This is self-healing (it is
     * what the retired events:tag-from-type command used to do), so no operator
     * has to remember a manual backfill before migrating.
     */
    public function up(): void
    {
        if (Schema::hasColumn('events', 'type')) {
            Event::query()->whereNotNull('type')->where('type', '!=', '')->lazyById()->each(function (Event $event): void {
                $tag = Tag::query()->firstOrCreate(
                    ['slug' => Str::slug($event->getAttribute('type'))],
                    ['name' => Str::headline($event->getAttribute('type'))],
                );

                $event->tags()->syncWithoutDetaching([$tag->id]);
            });
        }

        Schema::table('events', function (Blueprint $table): void {
            $table->dropColumn('type');
        });
    }

    public function down(): void
    {
        Schema::table('events', function (Blueprint $table): void {
            $table->string('type')->nullable();
        });
    }
};
