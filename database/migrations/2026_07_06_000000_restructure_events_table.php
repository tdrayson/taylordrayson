<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('events', function (Blueprint $table): void {
            $table->timestamp('ends_at')->nullable()->after('occurred_at');
            $table->boolean('all_day')->default(false)->after('ends_at');
            $table->string('company')->nullable()->after('name');
            $table->string('url')->nullable()->after('country');
            $table->json('meta')->nullable()->after('url');
        });

        Schema::table('events', function (Blueprint $table): void {
            $table->renameColumn('notes', 'description');
        });

        Schema::table('events', function (Blueprint $table): void {
            $table->dropColumn(['address', 'ticket_price']);
        });
    }

    public function down(): void
    {
        Schema::table('events', function (Blueprint $table): void {
            $table->text('address')->nullable()->after('venue_name');
            $table->decimal('ticket_price', 8, 2)->nullable()->after('longitude');
        });

        Schema::table('events', function (Blueprint $table): void {
            $table->renameColumn('description', 'notes');
        });

        Schema::table('events', function (Blueprint $table): void {
            $table->dropColumn(['ends_at', 'all_day', 'company', 'url', 'meta']);
        });
    }
};
