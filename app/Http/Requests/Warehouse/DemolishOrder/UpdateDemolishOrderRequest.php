<?php

namespace App\Http\Requests\Warehouse\DemolishOrder;

use App\Http\Requests\BaseFormRequest;

class UpdateDemolishOrderRequest extends BaseFormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules()
    {
        return [
            'status'=>'sometimes|required|string',
            'section_id' => 'sometimes|required|exists:sections,id',
            'item_id' => 'sometimes|required|exists:items,id',
            'shipment_id' => 'nullable|exists:shipments,id',
            'quantity' => 'sometimes|required|numeric|min:0.01',
            'reason' => 'sometimes|required|string|min:10|max:1000',
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
