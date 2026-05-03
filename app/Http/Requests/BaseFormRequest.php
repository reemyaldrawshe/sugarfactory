<?php

namespace App\Http\Requests;

use App\Http\Responses\Response;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Http\Exceptions\HttpResponseException;

class BaseFormRequest extends FormRequest
{
    protected function failedValidation(Validator $validator)
    {
        $errors = $validator->errors()->toArray();

        $requestClass = get_class($this); // Get the specific request class

        // Log activity with input first, then errors
        activity("Validation Failed: {$requestClass}")
            ->withProperties([
                'request' => $requestClass,
                'input' => $this->all(), // Log input first
                'errors' => $validator->errors()->toArray(), // Then errors
            ])
            ->log("Validation failed in {$requestClass}");


        throw new HttpResponseException(
            Response::Validation(['error_messages' => $errors], __('validation.failed'))
        );
    }
}

