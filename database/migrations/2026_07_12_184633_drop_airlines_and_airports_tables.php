<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::dropIfExists('airlines');
        Schema::dropIfExists('airports');
    }

    public function down(): void
    {
        // Intentionally empty — airlines/airports are Sushi CSV lookups now.
    }
};
