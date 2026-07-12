<?php

namespace App\Content;

use App\Contracts\DefinesContentSchema;
use App\Models\Tag;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Schema;

/**
 * Rebuildable content SQLite index helpers. The live app still uses the default
 * DB; warm/bench temporarily point database.default at the content connection.
 *
 * Table shapes come from each model's schema() (Orbit-style), not a central blueprint.
 * Which models are indexed comes from ContentTypes::indexModels().
 */
class ContentIndex
{
    /**
     * Models that own a table in the content SQLite index.
     *
     * @return list<class-string<Model&DefinesContentSchema>>
     */
    public static function models(): array
    {
        return ContentTypes::indexModels();
    }

    public function databasePath(): string
    {
        return (string) Config::get('database.connections.content.database');
    }

    public function ensureDatabaseFile(): void
    {
        $path = $this->databasePath();

        if ($path === '' || $path === ':memory:') {
            return;
        }

        File::ensureDirectoryExists(dirname($path));

        if (! File::exists($path)) {
            File::put($path, '');
        }
    }

    public function clear(): void
    {
        $path = $this->databasePath();

        DB::purge('content');

        if ($path !== '' && $path !== ':memory:' && File::exists($path)) {
            File::delete($path);
        }
    }

    /**
     * Recreate the content index schema (destructive).
     */
    public function migrate(): void
    {
        $this->ensureDatabaseFile();
        DB::purge('content');
        DB::reconnect('content');

        Schema::connection('content')->dropAllTables();

        $schema = Schema::connection('content');

        foreach (self::models() as $class) {
            /** @var Model&DefinesContentSchema $model */
            $model = new $class;

            $schema->create($model->getTable(), function (Blueprint $table) use ($class): void {
                $class::schema($table);
            });
        }

        $schema->create('taggables', function (Blueprint $table): void {
            Tag::schemaTaggables($table);
        });
    }

    /**
     * Run a callback with database.default temporarily set to the content connection.
     *
     * @template TReturn
     *
     * @param  callable(): TReturn  $callback
     * @return TReturn
     */
    public function using(callable $callback): mixed
    {
        $this->ensureDatabaseFile();

        $previous = Config::get('database.default');
        Config::set('database.default', 'content');
        DB::purge('content');
        DB::reconnect('content');

        try {
            return $callback();
        } finally {
            Config::set('database.default', $previous);
            // Never purge the previous default — PHPUnit's :memory: sqlite would
            // be wiped and RefreshDatabase schema lost for subsequent tests.
            DB::purge('content');
        }
    }

    /**
     * Clear, migrate, and warm the content index from the content tree.
     *
     * @return list<Model>
     */
    public function rebuild(EntryFileImporter $importer): array
    {
        $this->clear();
        $this->migrate();

        return $this->using(fn (): array => $importer->importAll());
    }
}
