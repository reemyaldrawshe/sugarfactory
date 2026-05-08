<?php


namespace App\Http\Requests\Production;

use Illuminate\Foundation\Http\FormRequest;

class CompleteProductionOrderRequest extends FormRequest
{
    public function authorize(): bool
    {
        return auth()->user()->can( 'production.order.finish');

    }

    public function rules(): array
    {
        return [
            'produced_quantity' => 'required|integer|min:1'
        ];
    }
}