<?php

namespace App\Http\Requests\Production;

use Illuminate\Foundation\Http\FormRequest;

class AllProductionOrdersRequest extends FormRequest
{
    public function authorize(): bool
    {
        return auth()->user()->can(
            'production.order.view'
        );
    }

    public function rules(): array
    {
        return [];
    }
}