<?php

namespace App\Http\Requests\Clinic;

use Illuminate\Foundation\Http\FormRequest;

class RecordClinicRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'heightCm' => 'required|numeric|min:0|max:300',
            'weightKg' => 'required|numeric|min:0|max:300',
            'bloodPressure' => 'required|string|max:20',
            'philhealthNumber' => 'nullable|string|max:50',
            'philhealthRegistered' => 'boolean',
            'assessmentNotes' => 'nullable|string',
            'findings' => 'nullable|string',
            'assessmentDate' => 'required|date',
        ];
    }
}