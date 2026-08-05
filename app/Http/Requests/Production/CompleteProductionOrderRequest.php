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
            'produced_quantity' => 'required|integer|min:1',
            'expiry_date'       => 'required|date|after_or_equal:today', // 👈 إضافة شرط تاريخ الصلاحية
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

