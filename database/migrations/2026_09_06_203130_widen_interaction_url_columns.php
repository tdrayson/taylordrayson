<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * URLs are text, not varchar(255).
 *
 * Nothing caps the length of a source or target a sender chooses, and a long
 * but perfectly valid URL would fail the insert rather than the validation.
 * SQLite ignores the length either way, so this changes nothing today and
 * removes a trap on any engine that does not.
 */
return new class extends Migration
{
    /** [table => [required columns], [nullable columns]] */
    private const COLUMNS = [
        'webmentions' => [
            ['source_url', 'target_url'],
            ['author_url', 'author_photo_url'],
        ],
        'webmention_sends' => [
            ['source_url', 'target_url'],
            ['endpoint'],
        ],
    ];

    public function up(): void
    {
        $this->retype(fn (Blueprint $table, string $column) => $table->text($column));
    }

    public function down(): void
    {
        $this->retype(fn (Blueprint $table, string $column) => $table->string($column));
    }

    /** Nullability is preserved per column: only the type is being changed. */
    private function retype(callable $definition): void
    {
        foreach (self::COLUMNS as $table => [$required, $optional]) {
            Schema::table($table, function (Blueprint $blueprint) use ($definition, $required, $optional) {
                foreach ($required as $column) {
                    $definition($blueprint, $column)->change();
                }

                foreach ($optional as $column) {
                    $definition($blueprint, $column)->nullable()->change();
                }
            });
        }
    }
};
