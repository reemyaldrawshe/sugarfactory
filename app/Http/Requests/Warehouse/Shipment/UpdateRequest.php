<?php

namespace App\Http\Requests\Warehouse\Shipment;

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
            'shipment_id' => ['required', 'exists:shipments,id'],
            'items' => ['required', 'array'],

            'items.*.item_id' => ['required', 'exists:items,id'],
            'items.*.quantity_received' => ['required', 'integer'],
            'items.*.price' => ['required', 'numeric'],
            'items.*.invoice_image' => ['nullable', 'string'],
        ];
    }
}
