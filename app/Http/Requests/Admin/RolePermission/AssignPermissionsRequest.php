<?php

namespace App\Http\Requests\Admin\RolePermission;

use App\Http\Requests\BaseFormRequest;

class AssignPermissionsRequest extends BaseFormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'permissions' => ['required', 'array', 'min:1'],
            'permissions.*' => ['string', 'exists:permissions,name'],
        ];
    }

    public function messages(): array
    {
        return [
            'permissions.required' => __('role_permission.permissions.required'),
            'permissions.array' => __('role_permission.permissions.array'),
            'permissions.min' => __('role_permission.permissions.min'),
            'permissions.*.string' => __('role_permission.permissions.string'),
            'permissions.*.exists' => __('role_permission.permissions.exists'),
        ];
    }
}

