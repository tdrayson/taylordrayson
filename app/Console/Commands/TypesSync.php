<?php

namespace App\Console\Commands;

use App\Support\TypeCatalogue;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('types:sync {--check : Report whether the module is stale instead of rewriting it}')]
#[Description('Write the data-type catalogue out for the frontend')]
class TypesSync extends Command
{
    /**
     * Regenerate resources/js/types.generated.js from App\Support\TypeCatalogue.
     *
     * The file is committed so a fresh clone can build without PHP: this only
     * has to run when the catalogue changes, and the sync test says when.
     */
    public function handle(): int
    {
        $path = base_path(TypeCatalogue::MODULE);
        $expected = TypeCatalogue::module();
        $current = is_file($path) ? file_get_contents($path) : null;

        if ($current === $expected) {
            $this->components->info(TypeCatalogue::MODULE.' is up to date.');

            return self::SUCCESS;
        }

        if ($this->option('check')) {
            $this->components->error(TypeCatalogue::MODULE.' is stale. Run `php artisan types:sync`.');

            return self::FAILURE;
        }

        if (file_put_contents($path, $expected) === false) {
            $this->components->error('Could not write '.TypeCatalogue::MODULE.'.');

            return self::FAILURE;
        }

        $this->components->info('Wrote '.TypeCatalogue::MODULE.'.');

        return self::SUCCESS;
    }
}
