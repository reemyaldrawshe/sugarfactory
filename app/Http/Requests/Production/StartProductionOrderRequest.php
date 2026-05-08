<?php

namespace App\Http\Requests\Production;

use Illuminate\Foundation\Http\FormRequest;

class StartProductionOrderRequest extends FormRequest
{
    public function authorize(): bool
    {
        return auth()->user()->can('production.order.start');
    }

    public function rules(): array
    {
        return [
            // حالياً ما في input
        ];
    }
}