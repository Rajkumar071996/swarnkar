<?php

namespace App\Http\Requests;

use App\Rules\AadhaarNumber;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class CustomerRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            // The girvi ledger number is the shop's own numbering, so it is
            // unique within the store rather than across the network.
            'ledger_no' => [
                'nullable', 'string', 'max:32',
                Rule::unique('store_customer', 'ledger_no')
                    ->where(fn ($query) => $query->where('store_id', $this->user()->store_id))
                    ->ignore($this->route('customer')?->id, 'customer_id'),
            ],
            'post' => ['nullable', 'string', 'max:120'],
            'caste' => ['nullable', 'string', 'max:60'],
            'business_type' => ['nullable', 'in:agriculture,non_agriculture'],
            'full_name' => ['required', 'string', 'max:150'],
            'mobile' => ['required', 'string', 'regex:/^[6-9]\d{9}$/'],
            'pan' => ['nullable', 'string', 'regex:/^[A-Z]{5}[0-9]{4}[A-Z]$/'],
            'aadhaar' => ['nullable', 'string', new AadhaarNumber],
            'date_of_birth' => ['nullable', 'date', 'before:today'],
            'address_line' => ['nullable', 'string', 'max:255'],
            'city' => ['nullable', 'string', 'max:100'],
            'state' => ['nullable', 'string', 'max:100'],
            'pincode' => ['nullable', 'string', 'regex:/^\d{6}$/'],
            'local_reference' => ['nullable', 'string', 'max:100'],
            'signature' => ['nullable', 'string', 'max:300000'],
        ];
    }

    /**
     * Counter staff type numbers however the customer says them, so normalise
     * before validating rather than rejecting "+91 98765 43210".
     */
    protected function prepareForValidation(): void
    {
        $mobile = preg_replace('/\D/', '', (string) $this->input('mobile'));

        if (strlen($mobile) > 10) {
            $mobile = substr($mobile, -10);
        }

        $this->merge([
            'mobile' => $mobile,
            'pan' => strtoupper(preg_replace('/[^A-Za-z0-9]/', '', (string) $this->input('pan'))) ?: null,
            'aadhaar' => preg_replace('/\D/', '', (string) $this->input('aadhaar')) ?: null,
        ]);
    }

    public function messages(): array
    {
        return [
            'mobile.regex' => 'Enter a valid 10-digit Indian mobile number.',
            'pan.regex' => 'A PAN looks like ABCDE1234F.',
            'pincode.regex' => 'Enter a valid 6-digit PIN code.',
            'ledger_no.unique' => 'Another customer already holds that ledger number.',
        ];
    }
}
