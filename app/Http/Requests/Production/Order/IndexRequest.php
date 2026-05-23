<?php

namespace App\Http\Requests\Production\Order;

use Illuminate\Foundation\Http\FormRequest;

class IndexRequest extends FormRequest
{
    public function authorize(): bool
    {
        return auth()->user()->can(
            'production.order.view'
        );
    }

    public function rules(): array
    {
        return [
            'status' => ['string', 'in:pending,approved_by_manager,rejected_by_manager,materials_reserved,sent_to_production,in_production,paused,completed',]
        ];
    }
}
