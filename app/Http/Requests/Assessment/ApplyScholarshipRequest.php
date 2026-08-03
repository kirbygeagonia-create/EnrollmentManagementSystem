<?php

namespace App\Http\Requests\Assessment;

use Illuminate\Foundation\Http\FormRequest;

class ApplyScholarshipRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'scholarshipTypeId' => 'required|exists:scholarshiptypes,scholarshipTypeId',
        ];
    }
}
