<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class ProvisionLicenseKeyRequest extends FormRequest
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
            'customer_email' => ['required','email'],
            'licenses' => ['required','array','min:1'],
            'licenses.*.product_code' => ['required','string'],
            'licenses.*.expires_at' => ['required','date'],
        ];
    }
}
