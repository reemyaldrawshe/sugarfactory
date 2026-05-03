<?php

namespace App\Http\Requests\Warehouse;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class StoreShipmentRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
         return auth()->user()->can('item.store');
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
   public function rules(): array
{
    return [
        'supplier' => 'nullable|string',

        'received_at' => 'required|date_format:Y-m-d',

        'items' => 'required|array|min:1',

        'items.*.item_id' => 'required|exists:items,id',
        'items.*.quantity' => 'required|integer|min:1',

        // ❗ السعر لازم أكبر من 0
        'items.*.price' => 'required|numeric|gt:0',

        // ❗ الصلاحية لازم تكون بعد تاريخ الاستلام
        'items.*.expiry_date' => 'nullable|date_format:Y-m-d|after:received_at',

        'items.*.note' => 'nullable|string',
    ];
}
}
