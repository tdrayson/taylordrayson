<?php

namespace App\Console\Commands;

use App\Models\Attachment;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Storage;

#[Signature('assets:mirror {--days=7 : Days back to re-check for unmirrored originals} {--all : Walk every attachment, for the first run or an audit}')]
#[Description('Copy attachment originals to the R2 mirror, never deleting')]
class MirrorAssets extends Command
{
    /** Keeps originals clear of the database backups sharing this bucket. */
    private const PREFIX = 'assets/';

    /**
     * Copy originals to R2, add-only.
     *
     * Add-only rather than a sync: media files are written once and never
     * modified, so nothing needs updating, and never deleting means removing a
     * file here leaves the copy on R2 intact.
     *
     * Originals only. Conversions and responsive images are 715MB of the 1.3GB
     * and rebuild from these with `media-library:regenerate` at no API cost,
     * whereas the Mapbox PNGs stored as originals would cost 6784 calls.
     */
    public function handle(): int
    {
        if (blank(config('filesystems.disks.r2.bucket'))) {
            $this->components->error('No R2 bucket configured; nothing to mirror to.');

            return self::FAILURE;
        }

        $source = Storage::disk('public');
        $mirror = Storage::disk('r2');

        $copied = 0;
        $failed = 0;

        foreach ($this->candidates() as $attachment) {
            $path = $this->pathFor($attachment);

            if ($path === null) {
                continue;
            }

            $destination = self::PREFIX.$path;

            if ($mirror->exists($destination)) {
                continue;
            }

            $stream = $source->readStream($path);

            if ($stream === null) {
                $this->components->warn("Missing on disk, skipped: {$path}");
                $failed++;

                continue;
            }

            $mirror->writeStream($destination, $stream);
            $copied++;
        }

        $this->components->info($copied.' copied to the mirror'.($failed > 0 ? ", {$failed} missing on disk" : '').'.');

        return $failed > 0 ? self::FAILURE : self::SUCCESS;
    }

    /**
     * Attachments worth checking: everything on a first run, otherwise a
     * rolling window, so a missed run heals itself on the next one.
     *
     * @return iterable<Attachment>
     */
    private function candidates(): iterable
    {
        $query = Attachment::query()->orderBy('id');

        if (! $this->option('all')) {
            $query->where('created_at', '>=', Carbon::now()->subDays(max(1, (int) $this->option('days'))));
        }

        return $query->lazy();
    }

    /**
     * Where the original sits on its disk. Asked of Media Library rather than
     * assembled here, so a change of path generator does not silently mirror
     * to the wrong keys.
     */
    private function pathFor(Attachment $attachment): ?string
    {
        return blank($attachment->file_name) ? null : $attachment->getPathRelativeToRoot();
    }
}
