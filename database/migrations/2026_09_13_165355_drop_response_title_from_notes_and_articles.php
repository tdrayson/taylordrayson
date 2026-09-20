<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        foreach (['notes', 'articles'] as $table) {
            Schema::table($table, function (Blueprint $table) {
                $table->dropColumn('response_title');
            });
        }
    }

    public function down(): void
    {
        foreach (['notes', 'articles'] as $table) {
            Schema::table($table, function (Blueprint $table) {
                $table->string('response_title')->nullable();
            });
        }
    }
};
