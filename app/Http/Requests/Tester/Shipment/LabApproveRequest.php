<?php

namespace App\Http\Requests\Tester\Shipment;

use App\Http\Requests\BaseFormRequest;

class LabApproveRequest  extends BaseFormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'expiry_date' => ['date', 'after:today'],
        ];
    }
}
