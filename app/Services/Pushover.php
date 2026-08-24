<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Client for the Pushover message API, used to put a failure on my phone.
 *
 * Chosen over email because it is one POST with no transport to configure:
 * the app cannot currently send mail at all (MAIL_MAILER=log).
 */
class Pushover
{
    private const ENDPOINT = 'https://api.pushover.net/1/messages.json';

    /** Pushover truncates at 1024; leave room rather than have it cut mid-word. */
    private const LIMIT = 900;

    /**
     * Send a message, or do nothing when no credentials are configured.
     *
     * Returns false rather than throwing: this is the path that reports a
     * failure, so it must never become a second failure of its own.
     */
    public function send(string $title, string $message): bool
    {
        $token = config('services.pushover.token');
        $user = config('services.pushover.user');

        if (blank($token) || blank($user)) {
            return false;
        }

        try {
            $response = Http::asForm()->post(self::ENDPOINT, [
                'token' => $token,
                'user' => $user,
                'title' => $title,
                'message' => str($message)->limit(self::LIMIT)->toString(),
            ]);
        } catch (\Throwable $e) {
            Log::warning('Pushover send failed: '.$e->getMessage());

            return false;
        }

        if ($response->failed()) {
            Log::warning('Pushover rejected the message: '.$response->status());
        }

        return $response->successful();
    }
}
