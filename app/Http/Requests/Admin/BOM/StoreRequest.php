<?php

namespace App\Http\Requests\Admin\BOM;

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
            'final_item_id' => 'required|exists:items,id',
            // الكمية المتوقع إنتاجها من هذه الخلطة كاملة
            'final_item_quantity' => 'required|integer|min:1', 

            'items' => 'required|array|min:1',
            'items.*.basic_item_id' => 'required|exists:items,id|different:final_item_id',
            'items.*.basic_item_quantity' => 'required|integer|min:1',
            // تحديد إذا كانت المادة أساسية أم لا
            'items.*.is_primary' => 'required|boolean', 
        ];
    }

    // إضافة تحقق مخصص للتأكد من وجود مادة أساسية واحدة فقط
    public function withValidator($validator)
    {
        $validator->after(function ($validator) {
            $items = $this->input('items', []);
            
            // حساب عدد المواد الأساسية في الطلب
            $primaryCount = collect($items)->filter(function ($item) {
                // نستخدم filter_var لأن البيانات قد تأتي كنص 'true' أو '1' من الـ FormData
                return filter_var($item['is_primary'] ?? false, FILTER_VALIDATE_BOOLEAN);
            })->count();

            if ($primaryCount === 0) {
                $validator->errors()->add('items', 'يجب تحديد مادة أساسية واحدة على الأقل في قائمة المواد.');
            } elseif ($primaryCount > 1) {
                $validator->errors()->add('items', 'لا يمكن اختيار أكثر من مادة أساسية واحدة للمنتج.');
            }
        });
    }
}
// namespace App\Http\Requests\Admin\BOM;

// use App\Http\Requests\BaseFormRequest;

// class StoreRequest extends BaseFormRequest
// {
//     public function authorize(): bool
//     {
//         return true;
//     }

//     public function rules(): array
//     {
//         return [
//             'final_item_id' => 'required|exists:items,id',

//             'items' => 'required|array|min:1',

//             'items.*.basic_item_id' => 'required|exists:items,id|different:final_item_id',
//             'items.*.basic_item_quantity' => 'required|integer|min:1',
//         ];
//     }
// }
