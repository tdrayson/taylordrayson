<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::table('media')->whereIn('type', ['tv', 'tv_episode', 'show'])->update(['type' => 'episode']);

        DB::table('media')->where('type', 'episode')->get()->each(function ($row) {
            $meta = json_decode($row->meta ?? '[]', true) ?: [];
            if (isset($meta['season_number'])) {
                $meta['season'] = $meta['season_number'];
                unset($meta['season_number']);
            }
            if (isset($meta['episode_number'])) {
                $meta['episode'] = $meta['episode_number'];
                unset($meta['episode_number']);
            }
            DB::table('media')->where('id', $row->id)->update(['meta' => json_encode($meta)]);
        });
    }

    public function down(): void
    {
        // Irreversible normalisation; no-op.
    }
};
