<?php

namespace App\Http\Requests\Admin\Section;

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
            'ar_name' => [
                'required',
                'string',
                'max:255',
                'regex:/^[\p{Arabic}\s]+$/u',
            ],
            'en_name' => [
                'required',
                'string',
                'max:255',
                'regex:/^[A-Za-z\s]+$/',
            ],
        ];
    }

    public function messages(): array
    {
        return [
            // Arabic name
            'ar_name.required' => __('section.ar_name.required'),
            'ar_name.string' => __('section.ar_name.string'),
            'ar_name.max' => __('section.ar_name.max'),
            'ar_name.regex' => __('section.ar_name.regex'),

            // English name
            'en_name.required' => __('section.en_name.required'),
            'en_name.string' => __('section.en_name.string'),
            'en_name.max' => __('section.en_name.max'),
            'en_name.regex' => __('section.en_name.regex'),
        ];
    }
}
