<?php

namespace App\Http\Requests\Warehouse\Shipment;

use App\Http\Requests\BaseFormRequest;

class CreatePurchaseRequest extends BaseFormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
           // 'supplier' => ['string', 'max:255'],
            'notes' => ['nullable', 'string'],
            'items' => ['required', 'array', 'min:1'],
            'items.*.item_id' => ['required', 'exists:items,id'],
            'items.*.quantity_required' => ['required', 'integer', 'min:1'],
        ];
    }
}
