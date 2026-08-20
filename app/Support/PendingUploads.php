<?php

namespace App\Support;

use App\Actions\Media\PrepareImage;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;

/**
 * Somewhere for a file to live between being chosen and the entry being saved.
 *
 * Media Library needs an owning model, and a new entry has none until it saves,
 * so an upload is parked here under a token and moved into the real collection
 * once there is something to attach it to.
 *
 * Each token gets its own directory so the original filename survives intact and
 * can be handed to Media Library on attach.
 */
final class PendingUploads
{
    /** How long an unattached upload is kept before a sweep discards it. */
    private const KEEP_HOURS = 24;

    public static function directory(): string
    {
        return storage_path('app/pending-uploads');
    }

    /**
     * Park a file and return its token.
     */
    public static function store(UploadedFile $file): string
    {
        self::prune();

        $token = Str::random(40);
        $path = self::directory().'/'.$token;

        File::ensureDirectoryExists($path);
        $moved = $file->move($path, $file->getClientOriginalName());

        app(PrepareImage::class)($moved->getPathname());

        return $token;
    }

    /**
     * The parked file for a token, or null when there is none. Tokens are
     * checked against the alphabet they are minted from, so a token can never
     * address anything outside the pending directory.
     */
    public static function path(string $token): ?string
    {
        if (preg_match('/^[A-Za-z0-9]{40}$/', $token) !== 1) {
            return null;
        }

        $files = File::files(self::directory().'/'.$token);

        return $files === [] ? null : $files[0]->getPathname();
    }

    public static function forget(string $token): void
    {
        if (preg_match('/^[A-Za-z0-9]{40}$/', $token) === 1) {
            File::deleteDirectory(self::directory().'/'.$token);
        }
    }

    /**
     * Drop anything left behind by a form that was never submitted. Called on
     * every upload and from `media:prune-pending`, since uploads can stop for
     * long enough that sweeping only on the next one leaves files for months.
     */
    public static function prune(): void
    {
        if (! File::isDirectory(self::directory())) {
            return;
        }

        $cutoff = now()->subHours(self::KEEP_HOURS)->getTimestamp();

        foreach (File::directories(self::directory()) as $directory) {
            if (File::lastModified($directory) < $cutoff) {
                File::deleteDirectory($directory);
            }
        }
    }
}
