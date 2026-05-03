<?php

namespace App\Http\Requests\Warehouse\Item;

use App\Http\Requests\BaseFormRequest;

class IndexRequest extends BaseFormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'search' => 'string|max:255',
            'section_id' => 'exists:sections,id',
            'unit_id' => 'exists:units,id',
            'is_raw_material' => 'boolean',
        ];
    }
}
