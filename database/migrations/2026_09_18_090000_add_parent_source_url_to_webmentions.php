<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('webmentions', function (Blueprint $table) {
            // The page that carried this response, for one read out of somebody
            // else's comment thread. Null for a mention sent to us directly,
            // which is still the only kind anybody can send.
            $table->string('parent_source_url')->nullable()->after('source_url');

            $table->index(['parent_source_url', 'target_url']);
        });
    }

    public function down(): void
    {
        Schema::table('webmentions', function (Blueprint $table) {
            $table->dropIndex(['parent_source_url', 'target_url']);
            $table->dropColumn('parent_source_url');
        });
    }
};
