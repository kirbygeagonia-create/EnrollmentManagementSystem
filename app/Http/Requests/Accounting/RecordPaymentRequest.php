<?php

namespace App\Http\Requests\Accounting;

use Illuminate\Foundation\Http\FormRequest;

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
            'paymentMode' => 'required|in:cash,check,online',
            'paymentDate' => 'required|date',
        ];
    }
}
