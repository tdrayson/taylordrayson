<?php

namespace App\Http\Requests\Cp;

use App\Cp\ResourceRegistry;
use Illuminate\Foundation\Http\FormRequest;

class ResourceRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    /**
     * @return array<string, array<int, string>>
     */
    public function rules(): array
    {
        $resource = app(ResourceRegistry::class)->find((string) $this->route('resource'));

        abort_if($resource === null, 404);

        return $resource->rules();
    }
}
