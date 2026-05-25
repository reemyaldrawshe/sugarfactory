<?php

namespace App\Http\Requests\Warehouse\DemolishOrder;

use App\Http\Requests\BaseFormRequest;

class CreateDemolishOrderRequest extends BaseFormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'section_id' => 'required|exists:sections,id',
            'item_id' => 'required|exists:items,id',
            'shipment_id' => 'nullable|exists:shipments,id',
            'quantity' => 'required|numeric|min:0.01',
            'reason' => 'required|string|min:10|max:1000',
            'images' => 'nullable|array',
            'images.*' => 'image|mimes:jpeg,png,jpg,gif|max:2048'
        ];
    }
    public function messages()
    {
        return [
            'section.required' => __('demolish.section_required'),
            'item_id.required' => __('demolish.item_required'),
            'quantity.required' => __('demolish.quantity_required'),
            'quantity.min' => __('demolish.quantity_min'),
            'reason.required' => __('demolish.reason_required'),
            'reason.min' => __('demolish.reason_min')
        ];
    }
}
