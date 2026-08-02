<?php

namespace App\Http\Requests\Clearance;

use Illuminate\Foundation\Http\FormRequest;

class GenerateSlipRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'studentId' => 'required|exists:students,studentId',
            'clearancePeriodId' => 'required|exists:clearanceperiods,clearancePeriodId',
        ];
    }
}