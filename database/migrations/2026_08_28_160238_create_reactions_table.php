<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('reactions', function (Blueprint $table) {
            $table->id();
            $table->morphs('reactable');
            $table->string('type');

            // Anonymous but stable: a hash of the visitor's client token and
            // their IP. The unique below is what turns a second click into a
            // toggle rather than a second vote.
            $table->string('identity_key', 64);

            $table->timestamps();

            $table->unique(['reactable_type', 'reactable_id', 'type', 'identity_key'], 'reactions_identity_unique');
            $table->index(['reactable_type', 'reactable_id', 'type']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('reactions');
    }
};
