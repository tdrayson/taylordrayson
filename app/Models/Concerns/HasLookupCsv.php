<?php

namespace App\Models\Concerns;

use App\Support\LookupCsv;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Str;
use LogicException;
use Sushi\Sushi;

/**
 * A lookup table whose rows come from a CSV in database/lookups and are cached
 * by Sushi into storage/framework/cache.
 *
 * That cache file is an ordinary writable SQLite database, so a row created
 * outside the testing environment persists in it as real lookup data. Issue
 * #474 is what that looks like: faker rows for LGW and KRK, and a flight that
 * resolved to a different airport depending on whether the relation was eager
 * or lazy loaded. Two things stop it, at different levels. Eloquent writes
 * throw, and the lookup key is held unique on the cached table, so a duplicate
 * still fails even from a query builder that fires no model event.
 *
 * A query-builder insert carrying an unused key remains possible, as it does
 * against any writable cache. It cannot make a lookup ambiguous, which is the
 * failure that mattered, and deleting the file rebuilds from the CSV.
 */
trait HasLookupCsv
{
    use Sushi;

    /**
     * Absolute path to the CSV backing this lookup.
     */
    abstract protected function lookupPath(): string;

    /**
     * The column relations key on, held unique so a lookup is never ambiguous.
     */
    abstract protected function lookupKey(): string;

    /**
     * @return list<array<string, string|null>>
     */
    public function getRows(): array
    {
        if (app()->environment('testing')) {
            return [];
        }

        return LookupCsv::from($this->lookupPath());
    }

    /**
     * Caching stays off under test. A cached empty row set would be written to
     * the shared cache file and later served to dev as real data.
     */
    protected function sushiShouldCache(): bool
    {
        return ! app()->environment('testing');
    }

    protected function sushiCacheReferencePath(): string
    {
        return $this->lookupPath();
    }

    /**
     * The table definition is part of the file name, so changing $schema or the
     * unique key names a file that cannot exist yet and is therefore always
     * rebuilt. Sushi's own staleness check compares mtimes against the CSV and
     * would otherwise skip the rebuild on any machine whose cache is warm,
     * which is every machine that already has one. Superseded files are left
     * behind; they are derived data in a gitignored directory.
     */
    protected function sushiCacheFileName(): string
    {
        $definition = substr(md5(serialize([$this->getSchema(), $this->lookupKey()])), 0, 8);

        return config('sushi.cache-prefix', 'sushi')
            .'-'.Str::kebab(str_replace('\\', '', static::class))
            ."-{$definition}.sqlite";
    }

    /**
     * Only reached on the with-data path, which is exactly the intent: the
     * constraint exists wherever the CSV is loaded, and is absent under test
     * where Sushi builds an empty table and fixtures need to create freely.
     */
    protected function afterMigrate(Blueprint $table): void
    {
        $table->unique($this->lookupKey());
    }

    public static function bootHasLookupCsv(): void
    {
        foreach (['creating', 'updating', 'deleting'] as $event) {
            static::registerModelEvent($event, function (Model $model) use ($event): void {
                if (app()->environment('testing')) {
                    return;
                }

                throw new LogicException(sprintf(
                    '%s is a read-only lookup loaded from %s. %s it outside the testing environment would write to the shared Sushi cache and serve the result as real data.',
                    class_basename($model),
                    basename($model->lookupPath()),
                    ucfirst($event),
                ));
            });
        }
    }
}
