<?php

namespace App\Http\Requests\Admin\User;

use App\Http\Requests\BaseFormRequest;

class IndexRequest extends BaseFormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'search' => ['nullable', 'string', 'max:255'],
            'gender' => ['nullable', 'string', 'in:male,female'],
            'role' => ['nullable', 'string'],
            'lang' => ['nullable', 'string', 'in:ar,en'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:100'],
        ];
    }

    public function messages(): array
    {
        return [
            'search.string' => __('user.search.string'),
            'search.max' => __('user.search.max'),

            'gender.string' => __('user.gender.string'),
            'gender.in' => __('user.gender.in'),

            'role.string' => __('user.role.string'),

            'lang.string' => __('user.lang.string'),
            'lang.in' => __('user.lang.in'),

            'per_page.integer' => __('user.per_page.integer'),
            'per_page.min' => __('user.per_page.min'),
            'per_page.max' => __('user.per_page.max'),
        ];
    }
}

