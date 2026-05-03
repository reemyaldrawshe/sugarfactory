<?php

namespace App\Http\Requests\Admin\User;

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
        $userId = $this->route('user')->id ?? null;

        return [
            'name' => ['nullable', 'string', 'max:255'],
            'email' => ['nullable', 'email', Rule::unique('users', 'email')->ignore($userId)],
            'gender' => ['nullable', 'string', 'in:male,female'],
            'lang' => ['nullable', 'string', 'in:ar,en'],
            'password' => ['nullable', 'string', 'min:8'],
            'roles' => ['nullable', 'array'],
            'roles.*' => ['string', 'exists:roles,name'],
        ];
    }

    public function messages(): array
    {
        return [
            'name.string' => __('user.name.string'),
            'name.max' => __('user.name.max'),

            'email.email' => __('user.email.email'),
            'email.unique' => __('user.email.unique'),

            'gender.string' => __('user.gender.string'),
            'gender.in' => __('user.gender.in'),

            'lang.string' => __('user.lang.string'),
            'lang.in' => __('user.lang.in'),

            'password.string' => __('user.password.string'),
            'password.min' => __('user.password.min'),

            'roles.array' => __('user.roles.array'),
            'roles.*.string' => __('user.roles.string'),
            'roles.*.exists' => __('user.roles.exists'),
        ];
    }
}

