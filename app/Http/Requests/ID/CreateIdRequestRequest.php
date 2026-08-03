<?php

namespace App\Http\Requests\ID;

use Illuminate\Foundation\Http\FormRequest;

class CreateIdRequestRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'requestReason' => 'required|in:newStudent,lost,renewal,shifted',
            'emergencyContactName' => 'required|string|max:255',
            'emergencyContactNumber' => 'required|string|max:20',
            'bloodType' => 'required|in:A+,A-,B+,B-,AB+,AB-,O+,O-',
            'cardPhotoPath' => 'nullable|string|max:500',
            'producedByVendor' => 'nullable|string|max:255',
        ];
    }
}
