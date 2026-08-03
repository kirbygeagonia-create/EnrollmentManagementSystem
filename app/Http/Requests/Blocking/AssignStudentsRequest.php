<?php

namespace App\Http\Requests\Blocking;

use Illuminate\Foundation\Http\FormRequest;

class AssignStudentsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'enrollmentIds' => 'required|array',
            'enrollmentIds.*' => 'exists:enrollments,enrollmentId',
            'scheduleId' => 'required|exists:schedules,scheduleId',
        ];
    }
}
