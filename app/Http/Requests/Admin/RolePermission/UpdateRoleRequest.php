<?php

namespace App\Http\Requests\Admin\RolePermission;

use App\Http\Requests\BaseFormRequest;
use Illuminate\Validation\Rule;

class UpdateRoleRequest extends BaseFormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $roleId = $this->route('role')->id ?? null;
        
        return [
            'name' => ['nullable', 'string', 'max:255', Rule::unique('roles', 'name')->ignore($roleId)],
            'guard_name' => ['nullable', 'string', 'max:255', 'in:web,api'],
            'permissions' => ['nullable', 'array'],
            'permissions.*' => ['string', 'exists:permissions,name'],
        ];
    }

    public function messages(): array
    {
        return [
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

