<?php

namespace App\Http\Requests\Sales\Shipment;

use App\Http\Requests\BaseFormRequest;

class UpdatePurchaseRequest extends BaseFormRequest
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
            'items.*.quantity_received' => ['required', 'integer', 'min:0'],
            'items.*.price' => ['required', 'numeric', 'min:0'],
            'items.*.invoice_image' => ['nullable', 'image'],
            'items.*.expiry_date' => ['nullable', 'date'],
            'items.*.note' => ['nullable', 'string'],
        ];
    }
}
