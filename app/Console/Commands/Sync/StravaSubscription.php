<?php

namespace App\Console\Commands\Sync;

use App\Services\Strava\Client;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('strava:subscription {action=show : show, create or delete}')]
#[Description('Inspect, create or delete the Strava push subscription that feeds the webhook')]
class StravaSubscription extends Command
{
    public function handle(Client $strava): int
    {
        return match ($this->argument('action')) {
            'show' => $this->show($strava),
            'create' => $this->create($strava),
            'delete' => $this->delete($strava),
            default => $this->invalid(),
        };
    }

    private function show(Client $strava): int
    {
        $result = $strava->subscription();

        if (! $result->ok) {
            $this->components->error($result->error ?? 'Could not read the subscription.');

            return self::FAILURE;
        }

        if ($result->id === null) {
            $this->components->warn('No subscription. Run `strava:subscription create` on the host serving the callback.');

            return self::SUCCESS;
        }

        $this->components->twoColumnDetail('Subscription', (string) $result->id);
        $this->components->twoColumnDetail('Callback', (string) $result->callbackUrl);

        return self::SUCCESS;
    }

    private function create(Client $strava): int
    {
        $secret = config('services.strava.webhook_secret');
        $verifyToken = config('services.strava.webhook_verify_token');

        if (blank($secret) || blank($verifyToken)) {
            $this->components->error('Set STRAVA_WEBHOOK_SECRET and STRAVA_WEBHOOK_VERIFY_TOKEN first.');

            return self::FAILURE;
        }

        $callback = route('strava.webhook.store', ['secret' => $secret]);

        // Strava GETs this URL before accepting the subscription, so it has to
        // be the deployed one. Printing it is the quickest way to catch an
        // APP_URL that still says localhost.
        $this->components->twoColumnDetail('Callback', $callback);

        $result = $strava->createSubscription($callback, $verifyToken);

        if (! $result->ok) {
            $this->components->error($result->error ?? 'Strava refused the subscription.');

            return self::FAILURE;
        }

        $this->components->info("Subscribed. Subscription {$result->id}.");

        return self::SUCCESS;
    }

    private function delete(Client $strava): int
    {
        $existing = $strava->subscription();

        if (! $existing->ok || $existing->id === null) {
            $this->components->warn('Nothing to delete.');

            return self::SUCCESS;
        }

        $result = $strava->deleteSubscription($existing->id);

        if (! $result->ok) {
            $this->components->error($result->error ?? 'Strava refused the delete.');

            return self::FAILURE;
        }

        $this->components->info("Deleted subscription {$existing->id}.");

        return self::SUCCESS;
    }

    private function invalid(): int
    {
        $this->components->error('Unknown action. Use show, create or delete.');

        return self::FAILURE;
    }
}
