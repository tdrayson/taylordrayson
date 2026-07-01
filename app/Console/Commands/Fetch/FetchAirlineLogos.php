<?php

namespace App\Console\Commands\Fetch;

use App\Models\Airline;
use App\Models\Flight;
use App\Services\LogoStream;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\File;

#[Signature('airlines:logos {iata?* : Specific IATA codes to fetch; defaults to every airline we have flights for} {--force : Re-download logos that already exist}')]
#[Description('Download airline icon and logo images from LogoStream, keyed by IATA code')]
class FetchAirlineLogos extends Command
{
    /**
     * Variant slug (subfolder) => the LogoStream API variant name.
     *
     * @var array<string, string>
     */
    private const VARIANTS = [
        'icon' => 'icon-transparent',
        'logo' => 'logo-transparent',
    ];

    public function handle(LogoStream $logoStream): int
    {
        if (! config('services.logostream.key')) {
            $this->components->error('LOGOSTREAM_KEY is not set.');

            return self::FAILURE;
        }

        $codes = $this->targetCodes();

        if ($codes->isEmpty()) {
            $this->components->warn('No airlines to fetch (no IATA codes given and no flown airlines have one).');

            return self::SUCCESS;
        }

        $downloaded = 0;
        $skipped = 0;
        $unavailable = 0;
        $failed = 0;

        foreach ($codes as $iata) {
            foreach (self::VARIANTS as $type => $variant) {
                $path = public_path("logos/airlines/{$type}/{$iata}.png");

                if (! $this->option('force') && File::exists($path)) {
                    $skipped++;

                    continue;
                }

                match ($this->download($logoStream, $iata, $variant, $path)) {
                    'saved' => [$this->components->task("{$iata} · {$type}"), $downloaded++],
                    'unavailable' => [$this->components->warn("{$iata} · {$type} — no logo available"), $unavailable++],
                    default => [$this->components->error("{$iata} · {$type} — request failed"), $failed++],
                };
            }
        }

        $this->newLine();
        $this->components->info("Downloaded {$downloaded}, skipped {$skipped}, unavailable {$unavailable}, failed {$failed}.");

        return $failed > 0 ? self::FAILURE : self::SUCCESS;
    }

    /**
     * Resolve which IATA codes to fetch: the command arguments, or every airline
     * we have a flight for that has an IATA code on record.
     *
     * @return Collection<int, string>
     */
    private function targetCodes(): Collection
    {
        $given = collect($this->argument('iata'))
            ->map(fn (string $code): string => strtoupper(trim($code)))
            ->filter();

        if ($given->isNotEmpty()) {
            return $given->unique()->values();
        }

        $flownIcao = Flight::query()->distinct()->pluck('airline_icao');

        return Airline::query()
            ->whereIn('icao_code', $flownIcao)
            ->whereNotNull('iata_code')
            ->where('iata_code', '!=', '')
            ->pluck('iata_code')
            ->map(fn (string $code): string => strtoupper(trim($code)))
            ->unique()
            ->values();
    }

    /**
     * Fetch a single variant for an IATA code and write it to disk.
     *
     * @return 'saved'|'unavailable'|'error' 'unavailable' when LogoStream has no
     *                                       real logo, 'error' on a request failure.
     */
    private function download(LogoStream $logoStream, string $iata, string $variant, string $path): string
    {
        $result = $logoStream->airlineLogo($iata, $variant);

        if ($result['status'] !== 'saved') {
            return $result['status'];
        }

        File::ensureDirectoryExists(dirname($path));
        File::put($path, $result['body']);

        return 'saved';
    }
}
