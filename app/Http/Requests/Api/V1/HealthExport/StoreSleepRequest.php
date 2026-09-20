<?php

namespace App\Http\Requests\Api\V1\HealthExport;

class StoreSleepRequest extends HealthExportRequest
{
    /**
     * @return list<string>
     */
    public function metrics(): array
    {
        return ['sleep_analysis'];
    }
}
