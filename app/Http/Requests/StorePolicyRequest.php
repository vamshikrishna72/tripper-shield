<?php

namespace App\Http\Requests;

use App\Enums\Destination;
use App\Enums\PlanCode;
use App\Enums\SumInsured;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Validation\Rules\Enum;

class StorePolicyRequest extends FormRequest
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
            'client_request_id' => ['required', 'string', 'max:128'],
            'agent_id' => ['required', 'string', 'max:128'],
            'customer_name' => ['required', 'string', 'min:2', 'max:255'],
            'date_of_birth' => ['required', 'date_format:Y-m-d', 'before:today'],
            'destination' => ['required', 'string', new Enum(Destination::class)],
            'trip_start' => ['required', 'date_format:Y-m-d'],
            'trip_end' => ['required', 'date_format:Y-m-d', 'after_or_equal:trip_start'],
            'plan_code' => ['required', 'string', new Enum(PlanCode::class)],
            'sum_insured' => ['required', 'integer', 'in:100000,300000,500000'],
        ];
    }

    /**
     * Custom messages for validation errors.
     *
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'trip_end.after_or_equal' => 'trip_end must be on or after trip_start.',
            'plan_code.in' => 'Supported plan codes are BASIC, PLUS, and PREMIUM.',
            'destination.in' => 'Supported destinations are DOMESTIC and INTERNATIONAL.',
            'sum_insured.in' => 'Supported coverage amounts are 100000, 300000, and 500000.',
        ];
    }

    /**
     * Handle a failed validation attempt with consistent JSON format.
     */
    protected function failedValidation(Validator $validator)
    {
        throw new HttpResponseException(response()->json([
            'success' => false,
            'message' => 'Validation failed for policy request intake.',
            'errors' => $validator->errors(),
        ], 422));
    }
}
