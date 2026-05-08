<?php
namespace App\Http\Requests\Production;

use Illuminate\Foundation\Http\FormRequest;

class SendMaterialsToProductionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return auth()->user()->can('production.order.warehouse.approve');
    }

    public function rules(): array
    {
        return [
            'notes' => 'nullable|string',
        ];
    }
}