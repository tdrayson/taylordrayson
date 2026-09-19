<?php

namespace Tests\Fixtures;

use App\Models\Concerns\HasLookupCsv;
use App\Support\LookupCsv;
use Illuminate\Database\Eloquent\Model;

/**
 * Stands in for Airport and Airline so the cached, with-data path can be
 * exercised under test, where those two deliberately load no rows.
 */
class LookupFixture extends Model
{
    use HasLookupCsv;

    public $timestamps = false;

    protected $guarded = [];

    /** @var array<string, string> */
    protected $schema = [
        'code' => 'string',
        'name' => 'string',
    ];

    /**
     * Unlike the real lookups this reads its CSV in every environment, since
     * the whole point is to build the table Sushi would build in production.
     */
    public function getRows(): array
    {
        return LookupCsv::from($this->lookupPath());
    }

    protected function sushiShouldCache(): bool
    {
        return true;
    }

    protected function lookupPath(): string
    {
        return __DIR__.'/lookup.csv';
    }

    protected function lookupKey(): string
    {
        return 'code';
    }
}
