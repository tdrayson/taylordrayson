<?php

namespace App\Console\Commands\Fetch;

use App\Models\Fuel;
use App\Services\LogoDev;
use App\Services\PetrolPrices\FuelBrands;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;

#[Signature('fuel:brand-logos {brand?* : Specific brand names to fetch; defaults to every brand on a fuel row} {--force : Re-download logos that already exist}')]
#[Description('Download fuel-station brand logos from logo.dev, keyed by brand slug')]
class FetchFuelBrandLogos extends Command
{
    public function handle(LogoDev $logoDev): int
    {
        if (! config('services.logodev.token')) {
            $this->components->error('LOGODEV_TOKEN is not set.');

            return self::FAILURE;
        }

        $brands = $this->targetBrands();

        if ($brands->isEmpty()) {
            $this->components->warn('No brands to fetch.');

            return self::SUCCESS;
        }

        $downloaded = 0;
        $skipped = 0;
        $unavailable = 0;
        $failed = 0;

        foreach ($brands as $brand) {
            $path = public_path('logos/brands/'.Str::slug($brand).'.png');

            if (! $this->option('force') && File::exists($path)) {
                $skipped++;

                continue;
            }

            $domain = FuelBrands::domain($brand);

            if ($domain === null) {
                $this->components->warn("{$brand} - no domain");
                $unavailable++;

                continue;
            }

            $result = $logoDev->logo($domain);

            match ($result['status']) {
                'saved' => [$this->store($path, $result['body']), $this->components->task($brand), $downloaded++],
                'unavailable' => [$this->components->warn("{$brand} - no logo available"), $unavailable++],
                default => [$this->components->error("{$brand} - request failed"), $failed++],
            };
        }

        $this->newLine();
        $this->components->info("Downloaded {$downloaded}, skipped {$skipped}, unavailable {$unavailable}, failed {$failed}.");

        return $failed > 0 ? self::FAILURE : self::SUCCESS;
    }

    /**
     * Brands to fetch: the command arguments, or every distinct brand that
     * appears on a fuel row.
     *
     * @return Collection<int, string>
     */
    private function targetBrands(): Collection
    {
        $given = collect($this->argument('brand'))
            ->map(fn (string $brand): string => trim($brand))
            ->filter();

        if ($given->isNotEmpty()) {
            return $given->unique()->values();
        }

        return Fuel::query()
            ->whereNotNull('brand')
            ->distinct()
            ->pluck('brand')
            ->values();
    }

    private function store(string $path, ?string $body): void
    {
        if ($body === null) {
            return;
        }

        File::ensureDirectoryExists(dirname($path));
        File::put($path, $body);
    }
}
