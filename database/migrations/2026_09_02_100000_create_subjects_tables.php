<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('subjects', function (Blueprint $table) {
            $table->id();
            $table->string('kind');
            $table->string('category')->nullable();
            $table->string('name');
            $table->string('slug');
            $table->json('bio')->nullable();
            $table->json('meta')->nullable();
            $table->json('identities')->nullable();
            // Read by PR 2's syncs. Stored now so PR 2 adds no tables.
            $table->json('rules')->nullable();
            $table->decimal('latitude', 10, 7)->nullable();
            $table->decimal('longitude', 10, 7)->nullable();
            $table->timestamps();

            // Scoped rather than global: a pet Bella and a person Bella are
            // different rows and both deserve their name.
            $table->unique(['kind', 'slug']);
            $table->index(['kind', 'category']);
        });

        Schema::create('subjectables', function (Blueprint $table) {
            $table->foreignId('subject_id')->constrained()->cascadeOnDelete();
            $table->morphs('subjectable');
            $table->unique(['subject_id', 'subjectable_type', 'subjectable_id'], 'subjectables_unique');
        });

        Schema::create('attachment_subject', function (Blueprint $table) {
            $table->id();
            $table->foreignId('attachment_id')->constrained('attachments')->cascadeOnDelete();
            $table->foreignId('subject_id')->constrained()->cascadeOnDelete();
            $table->string('role');
            $table->decimal('x', 5, 2)->nullable();
            $table->decimal('y', 5, 2)->nullable();
            $table->timestamps();
            $table->unique(['attachment_id', 'subject_id', 'role']);
        });
    }
};
