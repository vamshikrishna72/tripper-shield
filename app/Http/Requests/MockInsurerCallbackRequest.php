<?php

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Exceptions\HttpResponseException;

class MockInsurerCallbackRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'event_id' => ['required', 'string', 'max:128'],
            'event_type' => ['required', 'string', 'in:POLICY_ISSUED,POLICY_REJECTED'],
            'provider_reference' => ['required', 'string', 'max:128'],
            'policy_number' => ['nullable', 'string', 'max:128'],
            'idempotency_key' => ['nullable', 'string', 'max:128'],
            'policy_request_id' => ['nullable', 'string', 'max:128'],
        ];
    }

    /**
     * Handle a failed validation attempt with consistent JSON format.
     */
    protected function failedValidation(Validator $validator)
    {
        throw new HttpResponseException(response()->json([
            'success' => false,
            'message' => 'Validation failed for provider callback event.',
            'errors' => $validator->errors(),
        ], 422));
    }
}
