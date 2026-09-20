<?php

namespace App\Jobs;

use App\Actions\Citations\FetchCitation;
use App\Actions\Citations\StoreCitation;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Queue\SerializesModels;

/** Fetches and stores the citation for a reply written without the editor. */
class FetchCitationFor implements ShouldQueue
{
    use Queueable, SerializesModels;

    public int $tries = 2;

    public bool $deleteWhenMissingModels = true;

    public function __construct(private readonly Model $reply) {}

    public function handle(FetchCitation $fetch, StoreCitation $store): void
    {
        $url = (string) $this->reply->response_url;
        $data = $url === '' ? null : $fetch($url);

        if ($data !== null) {
            $store($data, $this->reply);
        }
    }
}
