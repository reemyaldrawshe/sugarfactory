<?php

namespace App\Http\Requests\Production;

use Illuminate\Foundation\Http\FormRequest;

class WarehouseApproveProductionOrderRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // الصلاحيات من middleware
    }

    public function rules(): array
    {
        return [
            'notes' => ['nullable', 'string'],
        ];
    }
}