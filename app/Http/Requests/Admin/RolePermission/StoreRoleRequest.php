<?php

namespace App\Http\Requests\Admin\RolePermission;

use App\Http\Requests\BaseFormRequest;
use Illuminate\Validation\Rule;

class StoreRoleRequest extends BaseFormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255', 'unique:roles,name'],
            'guard_name' => ['nullable', 'string', 'max:255', 'in:web,api'],
            'permissions' => ['nullable', 'array'],
            'permissions.*' => ['string', 'exists:permissions,name'],
        ];
    }

    public function messages(): array
    {
        return [
            'name.required' => __('role_permission.name.required'),
            'name.string' => __('role_permission.name.string'),
            'name.max' => __('role_permission.name.max'),
            'name.unique' => __('role_permission.name.unique'),

            'guard_name.string' => __('role_permission.guard_name.string'),
            'guard_name.max' => __('role_permission.guard_name.max'),
            'guard_name.in' => __('role_permission.guard_name.in'),

            'permissions.array' => __('role_permission.permissions.array'),
            'permissions.*.string' => __('role_permission.permissions.string'),
            'permissions.*.exists' => __('role_permission.permissions.exists'),
        ];
    }
}

