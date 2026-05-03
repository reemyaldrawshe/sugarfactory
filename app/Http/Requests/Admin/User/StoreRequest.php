<?php

namespace App\Http\Requests\Admin\User;

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
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'unique:users,email'],
            'gender' => ['required', 'string', 'in:male,female'],
            'lang' => ['nullable', 'string', 'in:ar,en'],
            'password' => ['required', 'string', 'min:8'],
            'roles' => ['nullable', 'array'],
            'roles.*' => ['string', 'exists:roles,name'],
        ];
    }

    public function messages(): array
    {
        return [
            'name.required' => __('user.name.required'),
            'name.string' => __('user.name.string'),
            'name.max' => __('user.name.max'),

            'email.required' => __('user.email.required'),
            'email.email' => __('user.email.email'),
            'email.unique' => __('user.email.unique'),

            'gender.required' => __('user.gender.required'),
            'gender.string' => __('user.gender.string'),
            'gender.in' => __('user.gender.in'),

            'lang.string' => __('user.lang.string'),
            'lang.in' => __('user.lang.in'),

            'password.required' => __('user.password.required'),
            'password.string' => __('user.password.string'),
            'password.min' => __('user.password.min'),

            'roles.array' => __('user.roles.array'),
            'roles.*.string' => __('user.roles.string'),
            'roles.*.exists' => __('user.roles.exists'),
        ];
    }
}

