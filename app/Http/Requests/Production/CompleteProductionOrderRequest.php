<?php


namespace App\Http\Requests\Production;

use Illuminate\Foundation\Http\FormRequest;

class CompleteProductionOrderRequest extends FormRequest
{
    public function authorize(): bool
    {
        return auth()->user()->can('production.order.finish');
    }

    public function rules(): array
    {
        return [
            // 💡 التعديل: تغيير integer إلى numeric واستبدال min:1 بـ min:0.0000001 مع دعم Decimal
            'produced_quantity' => ['required', 'numeric', 'gt:0', 'regex:/^\d+(\.\d+)?$/'],
            'expiry_date'       => ['required', 'date', 'after_or_equal:today'],
        ];
    }
    
    // يمكنك إضافة رسائل مخصصة للخطأ إن أردت
    public function messages(): array
    {
        return [
            'expiry_date.required' => 'تاريخ صلاحية الدفعة مطلوب.',
            'expiry_date.after_or_equal' => 'تاريخ الصلاحية يجب أن يكون اليوم أو تاريخاً مستقبلياً.',
        ];
    }
}

