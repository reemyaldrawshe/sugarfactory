<?php

namespace App\Http\Requests\Warehouse\Item;

use App\Http\Requests\BaseFormRequest;

class UpdateRequest extends BaseFormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'name' => 'string|max:255',
            'section_id' => 'exists:sections,id',
            'unit_id' => 'exists:units,id',
            'is_raw_material' => 'boolean',
            'image' => 'nullable|image|mimes:jpg,jpeg,png|max:2048',
            'minimum_quantity' => 'integer|min:1',
        ];
    }
}
