<?php

namespace App\Http\Requests\Admin\Unit;

use App\Http\Requests\BaseFormRequest;
use Illuminate\Validation\Rule;

class UpdateRequest extends BaseFormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $unitId = $this->route('unit'); // تأكد من مطابقة هذا الاسم للبارامتر في الراوت

        return [
            'name' => [
                'required',
                'string',
                'max:255',
                Rule::unique('units')->ignore($unitId),
            ],
        ];
    }

    public function messages(): array
    {
        return [
            'name.required' => __('unit.name.required'),
            'name.string' => __('unit.name.string'),
            'name.max' => __('unit.name.max'),
            'name.unique' => __('unit.name.unique'),
        ];
    }
}