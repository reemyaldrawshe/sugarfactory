<?php

namespace App\Http\Requests\Production;

use Illuminate\Foundation\Http\FormRequest;

class MaterialRequestsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return auth()->user()->can('production.material.requests.view');
    }

    public function rules(): array
    {
        return [];
    }
}