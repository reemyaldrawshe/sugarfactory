<?php

namespace App\Http\Requests\Tester\Shipment;

use App\Http\Requests\BaseFormRequest;

class LabResultRequest  extends BaseFormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'shipment_id' => ['required', 'exists:shipments,id'],
            'item_id' => ['required', 'exists:items,id'],
            'lab_test_file' => ['required', 'file'],
            'note' => ['nullable', 'string'],
        ];
    }
}
