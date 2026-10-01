<?php

namespace App\Http\Requests\Accounting;

use App\Enums\PaymentMode;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class RecordPaymentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'orNumber' => 'required|string|max:50|unique:payments,orNumber',
            'amount' => 'required|numeric|min:0.01',
            'paymentMode' => ['required', Rule::enum(PaymentMode::class)],
            'paymentDate' => 'required|date',
        ];
    }
}
