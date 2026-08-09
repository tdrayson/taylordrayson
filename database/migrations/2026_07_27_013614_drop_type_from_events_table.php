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
     * Drop the legacy `type` column, the event category now living in the
     * relational tag table. Each category is preserved as a tag first, so no
     * operator has to remember a manual backfill.
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
