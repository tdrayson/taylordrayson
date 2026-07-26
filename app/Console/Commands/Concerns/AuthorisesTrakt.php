<?php

namespace App\Console\Commands\Concerns;

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

        return $trakt->pollForDeviceToken(
            $device['device_code'],
            (int) ($device['interval'] ?? 5),
            (int) ($device['expires_in'] ?? 600),
            fn (int $waited): mixed => $waited % 30 === 0 ? $this->line("  still waiting ({$waited}s)...") : null,
        );
    }
}
