<?php

namespace App\Http\Requests\Production\Inventory;

use Illuminate\Foundation\Http\FormRequest;

class StoreInventoryRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // login ما بدو صلاحية
    }

    public function rules(): array
    {
        return [
            'items' => ['required', 'array', 'min:1'],

            'items.*.item_id' => ['required', 'exists:items,id'],

            'items.*.actual_quantity' => [
                'required',
                'numeric',
                'min:0'
            ],
            'name' => ['required','string'],

            'status' => ['nullable', 'in:pending,approved'],
        ];
    }
}
