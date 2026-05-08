<?php

namespace App\Http\Requests\Production;

use Illuminate\Foundation\Http\FormRequest;

class PauseProductionOrderRequest extends FormRequest
{
    public function authorize(): bool
    {
        return auth()->user()->can('production.order.pause');
    }

    public function rules(): array
    {
        return [
            'produced_quantity' => ['nullable', 'numeric', 'min:1'],
        ];
    }
}