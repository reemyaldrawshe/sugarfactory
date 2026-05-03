<?php

namespace App\Http\Requests\Tester\Shipment;

use App\Http\Requests\BaseFormRequest;

class LabRejectRequest  extends BaseFormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'reason' => ['required', 'string', 'min:10'],
        ];
    }
}
