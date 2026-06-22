<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('activities', function (Blueprint $table) {
            $table->id();
            $table->timestamp('occurred_at');
            $table->index('occurred_at');
            $table->string('type');
            $table->string('name')->nullable();
            $table->integer('duration');
            $table->integer('calories')->nullable();
            $table->decimal('distance_km', 8, 3)->nullable();
            $table->integer('average_heart_rate')->nullable();
            $table->integer('max_heart_rate')->nullable();
            $table->json('heart_rate')->nullable();
            $table->string('platform_type')->nullable();
            $table->string('platform_id')->nullable();
            $table->json('meta')->nullable();
            $table->timestamps();
            $table->unique(['platform_type', 'platform_id']);
        });

        Schema::create('sleep', function (Blueprint $table) {
            $table->id();
            $table->timestamp('occurred_at');
            $table->index('occurred_at');
            $table->timestamp('bedtime');
            $table->timestamp('wake_time');
            $table->integer('duration');
            $table->integer('awake')->nullable();
            $table->integer('rem')->nullable();
            $table->integer('core')->nullable();
            $table->integer('deep')->nullable();
            $table->string('source')->nullable();
            $table->json('stages')->nullable();
            $table->timestamps();
        });

        Schema::create('calories', function (Blueprint $table) {
            $table->id();
            $table->timestamp('occurred_at');
            $table->index('occurred_at');
            $table->string('name');
            $table->string('icon')->nullable();
            $table->string('meal');
            $table->decimal('quantity', 8, 2);
            $table->string('units');
            $table->integer('calories');
            $table->decimal('fat', 8, 2)->nullable();
            $table->decimal('protein', 8, 2)->nullable();
            $table->decimal('carbs', 8, 2)->nullable();
            $table->decimal('saturated_fat', 8, 2)->nullable();
            $table->decimal('sugars', 8, 2)->nullable();
            $table->decimal('fibre', 8, 2)->nullable();
            $table->decimal('cholesterol', 8, 2)->nullable();
            $table->decimal('sodium', 8, 2)->nullable();
            $table->timestamps();
        });

        Schema::create('media', function (Blueprint $table) {
            $table->id();
            $table->timestamp('occurred_at');
            $table->index('occurred_at');
            $table->string('type');
            $table->string('title');
            $table->integer('rating')->nullable();
            $table->string('platform_type')->nullable();
            $table->string('platform_id')->nullable();
            $table->json('meta')->nullable();
            $table->timestamps();
            $table->unique(['platform_type', 'platform_id']);
        });

        Schema::create('events', function (Blueprint $table) {
            $table->id();
            $table->timestamp('occurred_at');
            $table->index('occurred_at');
            $table->string('type');
            $table->string('name');
            $table->string('venue_name')->nullable();
            $table->string('address')->nullable();
            $table->string('city')->nullable();
            $table->string('country')->nullable();
            $table->decimal('latitude', 10, 7)->nullable();
            $table->decimal('longitude', 10, 7)->nullable();
            $table->decimal('ticket_price', 8, 2)->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();
        });

        Schema::create('appearances', function (Blueprint $table) {
            $table->id();
            $table->timestamp('occurred_at');
            $table->index('occurred_at');
            $table->string('type');
            $table->string('title');
            $table->string('show_name');
            $table->string('url')->nullable();
            $table->string('video_url')->nullable();
            $table->text('description')->nullable();
            $table->integer('duration')->nullable();
            $table->timestamps();
        });

        Schema::create('podcasts', function (Blueprint $table) {
            $table->id();
            $table->timestamp('occurred_at');
            $table->index('occurred_at');
            $table->integer('season_number');
            $table->integer('episode_number');
            $table->string('topic')->nullable();
            $table->text('show_notes')->nullable();
            $table->text('transcript')->nullable();
            $table->integer('duration')->nullable();
            $table->string('audio_url')->nullable();
            $table->string('youtube_url')->nullable();
            $table->string('thumbnail')->nullable();
            $table->string('cover_image')->nullable();
            $table->timestamps();
        });

        Schema::create('airports', function (Blueprint $table) {
            $table->id();
            $table->string('iata_code')->unique();
            $table->string('icao_code')->nullable();
            $table->string('name');
            $table->string('city')->nullable();
            $table->string('country')->nullable();
            $table->decimal('latitude', 10, 7)->nullable();
            $table->decimal('longitude', 10, 7)->nullable();
            $table->timestamps();
        });

        Schema::create('airlines', function (Blueprint $table) {
            $table->id();
            $table->string('iata_code')->nullable();
            $table->string('icao_code')->unique();
            $table->string('name');
            $table->string('country')->nullable();
            $table->timestamps();
        });

        Schema::create('flights', function (Blueprint $table) {
            $table->id();
            $table->timestamp('occurred_at');
            $table->index('occurred_at');
            $table->string('flight_number');
            $table->string('airline_icao');
            $table->string('origin_iata');
            $table->string('destination_iata');
            $table->integer('distance_miles')->nullable();
            $table->string('cabin_class')->nullable();
            $table->string('reason')->nullable();
            $table->json('meta')->nullable();
            $table->timestamps();
        });

        Schema::create('checkins', function (Blueprint $table) {
            $table->id();
            $table->timestamp('occurred_at');
            $table->index('occurred_at');
            $table->string('venue_name');
            $table->string('category')->nullable();
            $table->string('address')->nullable();
            $table->string('city')->nullable();
            $table->string('county')->nullable();
            $table->string('country')->nullable();
            $table->decimal('latitude', 10, 7)->nullable();
            $table->decimal('longitude', 10, 7)->nullable();
            $table->text('description')->nullable();
            $table->boolean('is_mayor')->default(false);
            $table->string('platform_type')->nullable();
            $table->string('platform_id')->nullable();
            $table->timestamps();
            $table->unique(['platform_type', 'platform_id']);
        });

        Schema::create('fuel', function (Blueprint $table) {
            $table->id();
            $table->timestamp('occurred_at');
            $table->index('occurred_at');
            $table->string('vehicle_id');
            $table->decimal('litres', 8, 3);
            $table->decimal('cost', 8, 2);
            $table->decimal('fuel_card_cost', 8, 2)->nullable();
            $table->decimal('price_per_litre', 8, 3)->nullable();
            $table->integer('odometer')->nullable();
            $table->string('station')->nullable();
            $table->string('city')->nullable();
            $table->timestamps();
        });

        Schema::create('projects', function (Blueprint $table) {
            $table->id();
            $table->timestamp('occurred_at');
            $table->index('occurred_at');
            $table->string('title');
            $table->string('slug');
            $table->text('description')->nullable();
            $table->text('long_description')->nullable();
            $table->string('url')->nullable();
            $table->string('github_url')->nullable();
            $table->string('status');
            $table->boolean('featured')->default(false);
            $table->json('tags')->nullable();
            $table->timestamp('started_at')->nullable();
            $table->timestamps();
        });

        Schema::create('articles', function (Blueprint $table) {
            $table->id();
            $table->timestamp('occurred_at');
            $table->index('occurred_at');
            $table->string('title');
            $table->string('slug');
            $table->text('excerpt')->nullable();
            $table->longText('content');
            $table->boolean('draft')->default(false);
            $table->json('tags')->nullable();
            $table->timestamps();
        });

        Schema::create('notes', function (Blueprint $table) {
            $table->id();
            $table->timestamp('occurred_at');
            $table->index('occurred_at');
            $table->text('content');
            $table->timestamps();
        });

        Schema::create('assets', function (Blueprint $table) {
            $table->id();
            $table->morphs('assetable');
            $table->string('type');
            $table->string('path');
            $table->string('original_filename')->nullable();
            $table->integer('width')->nullable();
            $table->integer('height')->nullable();
            $table->string('mime_type')->nullable();
            $table->integer('size_bytes')->nullable();
            $table->integer('order')->default(0);
            $table->timestamps();
        });

        Schema::create('timeline_entries', function (Blueprint $table) {
            $table->id();
            $table->morphs('timelineable');
            $table->timestamp('occurred_at');
            $table->index('occurred_at');
            $table->unique(['timelineable_type', 'timelineable_id']);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('timeline_entries');
        Schema::dropIfExists('assets');
        Schema::dropIfExists('notes');
        Schema::dropIfExists('articles');
        Schema::dropIfExists('projects');
        Schema::dropIfExists('fuel');
        Schema::dropIfExists('checkins');
        Schema::dropIfExists('flights');
        Schema::dropIfExists('airlines');
        Schema::dropIfExists('airports');
        Schema::dropIfExists('appearances');
        Schema::dropIfExists('podcasts');
        Schema::dropIfExists('events');
        Schema::dropIfExists('media');
        Schema::dropIfExists('calories');
        Schema::dropIfExists('sleep');
        Schema::dropIfExists('activities');
    }
};
