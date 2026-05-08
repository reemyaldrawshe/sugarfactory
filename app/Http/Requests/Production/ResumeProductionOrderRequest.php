<?php
namespace App\Http\Requests\Production;

use Illuminate\Foundation\Http\FormRequest;

class ResumeProductionOrderRequest extends FormRequest
{
    public function authorize(): bool
    {
        return auth()->user()->can('production.order.resume');
    }

    public function rules(): array
    {
        return [];
    }
}