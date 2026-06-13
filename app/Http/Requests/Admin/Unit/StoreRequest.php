<?php

namespace App\Http\Requests\Admin\Unit;

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
            'name' => [
                'required',
                'string',
                'max:255',
            ],
        ];
    }

    public function messages(): array
    {
        return [
            'name.required' => __('unit.name.required'),
            'name.string' => __('unit.name.string'),
            'name.max' => __('unit.name.max'),
        ];
    }
}