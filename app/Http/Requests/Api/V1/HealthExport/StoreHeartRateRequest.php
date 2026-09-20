<?php

namespace App\Http\Requests\Api\V1\HealthExport;

class StoreHeartRateRequest extends HealthExportRequest
{
    /**
     * @return list<string>
     */
    public function metrics(): array
    {
        return ['heart_rate'];
    }
}
