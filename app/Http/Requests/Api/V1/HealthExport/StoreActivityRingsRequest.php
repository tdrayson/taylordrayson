<?php

namespace App\Http\Requests\Api\V1\HealthExport;

use App\Support\Health\ActivityRings;

class StoreActivityRingsRequest extends HealthExportRequest
{
    /**
     * @return list<string>
     */
    public function metrics(): array
    {
        return array_keys(ActivityRings::METRICS);
    }
}
