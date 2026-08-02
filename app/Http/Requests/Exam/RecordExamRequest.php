<?php

namespace App\Http\Requests\Exam;

use Illuminate\Foundation\Http\FormRequest;

class RecordExamRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'studentId' => 'required|exists:students,studentId',
            'courseId' => 'required|exists:courses,courseId',
            'termId' => 'required|exists:academicterms,termId',
            'examResult' => 'required|in:pass,fail',
            'examDate' => 'required|date',
        ];
    }
}