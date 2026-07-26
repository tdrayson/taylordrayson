<?php

namespace App\Console\Commands\Concerns;

use App\Exceptions\TraktException;
use App\Services\Trakt;

/**
 * Drives the Trakt OAuth device flow from a console command, printing the
 * code for the operator to enter at trakt.tv and blocking until they do.
 *
 * Shared by the prune commands so the write-authorisation path exists once.
 */
trait AuthorisesTrakt
{
    private function authoriseTrakt(Trakt $trakt): string
    {
        $device = $trakt->deviceCode();

        $this->newLine();
        $this->line("Go to <options=bold>{$device['verification_url']}</> and enter this code:");
        $this->newLine();
        $this->line("    <options=bold;fg=yellow>{$device['user_code']}</>");
        $this->newLine();
        $this->line('Waiting for authorisation...');

        $token = $trakt->pollForDeviceToken(
            $device['device_code'],
            (int) ($device['interval'] ?? 5),
            (int) ($device['expires_in'] ?? 600),
            fn (int $waited): mixed => $waited % 30 === 0 ? $this->line("  still waiting ({$waited}s)...") : null,
        );

        $username = $trakt->authenticatedUsername($token);
        $expected = config('services.trakt.username');

        $this->newLine();
        $this->line("Authorised as <options=bold>@{$username}</>.");

        // A token for the wrong account makes every history-id removal come
        // back not_found, so this mismatch is stopped here rather than after
        // a confusing zero-effect run.
        if ($username !== null && $expected !== null && strcasecmp($username, $expected) !== 0) {
            throw new TraktException(
                "That is not @{$expected}. Log into @{$expected} at trakt.tv, then run this again."
            );
        }

        return $token;
    }
}
