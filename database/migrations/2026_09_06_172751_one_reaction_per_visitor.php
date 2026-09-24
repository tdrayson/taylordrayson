<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * One reaction per visitor per entry, rather than one per emoji.
 *
 * Keying the unique on the type as well let a single person hold every emoji at
 * once, which turned a reaction from "what I thought of this" into six votes.
 * Changing emoji now moves the row they already have.
 */
return new class extends Migration
{
    public function up(): void
    {
        $this->collapseToOnePerVisitor();

        Schema::table('reactions', function (Blueprint $table) {
            $table->dropUnique('reactions_identity_unique');
            $table->unique(['reactable_type', 'reactable_id', 'identity_key'], 'reactions_visitor_unique');
        });
    }

    public function down(): void
    {
        Schema::table('reactions', function (Blueprint $table) {
            $table->dropUnique('reactions_visitor_unique');
            $table->unique(['reactable_type', 'reactable_id', 'type', 'identity_key'], 'reactions_identity_unique');
        });
    }

    /**
     * Drop all but the last reaction each visitor left on an entry, since the
     * new unique cannot be added while anybody holds two. The highest id is the
     * one they picked most recently, which is the one they meant.
     */
    private function collapseToOnePerVisitor(): void
    {
        $keep = DB::table('reactions')
            ->groupBy('reactable_type', 'reactable_id', 'identity_key')
            ->selectRaw('max(id) as id')
            ->pluck('id');

        if ($keep->isEmpty()) {
            return;
        }

        DB::table('reactions')->whereNotIn('id', $keep)->delete();
    }
};
