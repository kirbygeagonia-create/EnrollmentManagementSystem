<?php

namespace App\Http\Requests\Admission;

use Illuminate\Foundation\Http\FormRequest;

class StoreApplicantRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'schoolIdNumber' => 'required|string|max:50|unique:students,schoolIdNumber',
            'lastName' => 'required|string|max:100',
            'firstName' => 'required|string|max:100',
            'middleName' => 'nullable|string|max:100',
            'suffix' => 'nullable|string|max:20',
            'gender' => 'required|in:male,female',
            'birthdate' => 'required|date',
            'birthplace' => 'required|string|max:255',
            'citizenship' => 'required|string|max:100',
            'religionId' => 'required|exists:religions,religionId',
            'civilStatus' => 'required|in:single,married,widowed,separated',
            'contactNumber' => 'required|string|max:20',
            'telephoneNumber' => 'nullable|string|max:20',
            'email' => 'required|email|max:255|unique:students,email',
            'username' => 'required|string|max:50|unique:students,username',
            'password' => 'required|string|min:8|confirmed',
            'courseId' => 'required|exists:courses,courseId',
            'termId' => 'required|exists:academicterms,termId',
            'applicantType' => 'required|in:firstYear,transferee,continuing,shifter',
            'addresses' => 'required|array|min:1',
            'addresses.*.addressType' => 'required|in:home,current,permanent',
            'addresses.*.houseBuildingNo' => 'nullable|string|max:100',
            'addresses.*.street' => 'nullable|string|max:255',
            'addresses.*.sitioPurok' => 'nullable|string|max:100',
            'addresses.*.barangay' => 'required|string|max:100',
            'addresses.*.cityMunicipality' => 'required|string|max:100',
            'addresses.*.district' => 'nullable|string|max:100',
            'addresses.*.province' => 'required|string|max:100',
            'addresses.*.region' => 'nullable|string|max:100',
            'addresses.*.zipCode' => 'nullable|string|max:20',
            'addresses.*.country' => 'required|string|max:100',
            'guardians' => 'required|array|min:1',
            'guardians.*.relationship' => 'required|in:mother,father,guardian,other',
            'guardians.*.fullName' => 'required|string|max:255',
            'guardians.*.contactNumber' => 'required|string|max:20',
            'guardians.*.email' => 'nullable|email|max:255',
            'guardians.*.isEmergencyContact' => 'boolean',
            'guardians.*.isAuthorizedToActOnBehalf' => 'boolean',
            'educationalBackgrounds' => 'nullable|array',
            'educationalBackgrounds.*.institutionName' => 'required_with:educationalBackgrounds|string|max:255',
            'educationalBackgrounds.*.institutionType' => 'required_with:educationalBackgrounds|in:elementary,secondary,seniorHigh,college,graduate',
            'educationalBackgrounds.*.cityMunicipality' => 'nullable|string|max:100',
            'educationalBackgrounds.*.province' => 'nullable|string|max:100',
            'educationalBackgrounds.*.levelCompleted' => 'required_with:educationalBackgrounds|in:elementary,secondary,seniorHigh,college,graduate',
            'educationalBackgrounds.*.strandTrack' => 'nullable|string|max:100',
            'educationalBackgrounds.*.yearCompleted' => 'nullable|date',
            'educationalBackgrounds.*.honorsCertifications' => 'nullable|string|max:500',
        ];
    }
}