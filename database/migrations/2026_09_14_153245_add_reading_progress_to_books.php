<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('books', function (Blueprint $table) {
            $table->decimal('progress_percent', 6, 3)->nullable();
            $table->unsignedInteger('current_page')->nullable();
            $table->unsignedInteger('pages')->nullable();
            $table->dateTime('progressed_at')->nullable()->index();
            $table->text('overview')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('books', function (Blueprint $table) {
            $table->dropIndex(['progressed_at']);
            $table->dropColumn(['progress_percent', 'current_page', 'pages', 'progressed_at', 'overview']);
        });
    }
};
