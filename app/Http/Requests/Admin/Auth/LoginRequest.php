<?php

namespace App\Http\Requests\Admin\Auth;

use App\Http\Requests\BaseFormRequest;

class LoginRequest extends BaseFormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'email' => 'required|email|exists:users,email',
            'password' => 'required|string',
        ];
    }

    public function messages(): array
    {
        return [
            'email.required' => __('auth.email.required'),
            'email.exists' => __('auth.email.exists'),
            'email.email' => __('auth.email.email'),

            'password.required' => __('auth.password.required'),
            'password.string' => __('auth.password.string'),
            'password.min' => __('auth.password.min'),
        ];
    }
}
