<?php

namespace App\Http\Requests\Tester\Shipment;

use App\Http\Requests\BaseFormRequest;

class LabApproveRequest  extends BaseFormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            // يجب إرسال مصفوفة من العناصر
            'items' => ['required', 'array', 'min:1'],
            
            // تحقق من أن كل عنصر يحتوي على معرف العنصر وهو موجود في الداتا بيز
            'items.*.shipment_item_id' => ['required', 'integer', 'exists:shipment_items,id'],
            
            // تحقق من أن كل عنصر يحتوي على تاريخ انتهاء صالح
            'items.*.expiry_date' => ['required', 'date', 'after:today'],
        ];
        // return [
        //     'expiry_date' => ['date', 'after:today'],
        // ];
    }
}
