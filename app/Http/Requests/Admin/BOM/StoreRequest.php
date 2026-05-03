<?php

namespace App\Http\Requests\Admin\BOM;

use App\Http\Requests\BaseFormRequest;

class StoreRequest extends BaseFormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'final_item_id' => 'required|exists:items,id',

            'items' => 'required|array|min:1',

            'items.*.basic_item_id' => 'required|exists:items,id|different:final_item_id',
            'items.*.basic_item_quantity' => 'required|integer|min:1',
        ];
    }
}
