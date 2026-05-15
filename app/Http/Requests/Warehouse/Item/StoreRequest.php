<?php

namespace App\Http\Requests\Warehouse\Item;

use App\Http\Requests\BaseFormRequest;

class StoreRequest extends BaseFormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'name' => 'required|string|max:255',
            'section_id' => 'required|exists:sections,id',
            'unit_id' => 'required|exists:units,id',
            'is_raw_material' => 'required|boolean',
            'image' => 'nullable|image|mimes:jpg,jpeg,png|max:2048',
            'minimum_quantity' => 'integer|min:1',
            'quantity_received' => 'nullable|integer|min:0',
'price' => 'nullable|numeric|min:0',
'expiry_date' => 'nullable|date',
        ];
    }
}
