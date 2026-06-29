<?php

namespace App\Http\Requests\Sales\Shipment;

use App\Http\Requests\BaseFormRequest;

// class UpdatePurchaseRequest extends BaseFormRequest
// {
//     public function authorize(): bool
//     {
//         return true;
//     }

//     public function rules(): array
//     {
//         return [
//             'shipment_id' => ['required', 'exists:shipments,id'],
//             'items' => ['required', 'array'],
//             'items.*.item_id' => ['required', 'exists:items,id'],
//             'items.*.quantity_received' => ['required', 'integer', 'min:0'],
//             'items.*.price' => ['required', 'numeric', 'min:0'],
//             'items.*.invoice_image' => ['nullable', 'image'],
//             'items.*.expiry_date' => ['nullable', 'date'],
//             'items.*.note' => ['nullable', 'string'],
//         ];
//     }
// }

class UpdatePurchaseRequest extends BaseFormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'shipment_id'               => ['required', 'exists:shipments,id'],
            'supplier'        => ['required', 'string', 'max:255'],
            'supplier_number' => ['required', 'string', 'max:50'],
            // تحقق العناصر (الأسعار والكميات لكل منتج)
            'items'                     => ['required', 'array'],
            'items.*.item_id'           => ['required', 'exists:shipment_items,id'],
            'items.*.quantity_received' => ['required', 'integer', 'min:0'],
            'items.*.price'             => ['required', 'numeric', 'min:0'], // السعر لكل منتج سيبقى هنا
            'items.*.expiry_date'       => ['nullable', 'date'],
            'items.*.note'              => ['nullable', 'string'],
            
            // 💡 التعديل الجديد: مصفوفة صور الفاتورة للطلب كاملاً
            'invoice_images'            => ['nullable', 'array'],
            'invoice_images.*'          => ['image', 'mimes:jpeg,png,jpg', 'max:2048'], // تحقق من نوع وحجم كل صورة
        ];
    }
}