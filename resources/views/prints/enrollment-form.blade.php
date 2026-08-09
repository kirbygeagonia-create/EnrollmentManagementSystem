<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Enrollment Form - {{ $enrollment->student->schoolIdNumber }}</title>
    <style>
        body { font-family: 'DejaVu Sans', Arial, sans-serif; margin: 15px; font-size: 10px; }
        .header { text-align: center; margin-bottom: 15px; border-bottom: 2px solid #000; padding-bottom: 10px; }
        .logo { width: 60px; height: 60px; margin-bottom: 5px; }
        .school-name { font-size: 14px; font-weight: bold; text-transform: uppercase; }
        .school-address { font-size: 9px; margin: 1px 0; }
        .title { font-size: 13px; font-weight: bold; text-transform: uppercase; text-decoration: underline; text-align: center; margin: 10px 0; }
        .section-title { font-size: 11px; font-weight: bold; text-transform: uppercase; border-bottom: 1px solid #000; padding-bottom: 3px; margin: 15px 0 8px; }
        .form-grid { display: grid; grid-template-columns: repeat(2, 1fr); gap: 8px; margin-bottom: 10px; }
        .field { display: flex; }
        .label { width: 130px; font-weight: bold; font-size: 9px; }
        .value { flex: 1; border-bottom: 1px solid #000; padding-bottom: 2px; min-height: 18px; }
        .full-width { grid-column: span 2; }
        .address-section { border: 1px solid #000; padding: 10px; margin: 10px 0; }
        .address-title { font-weight: bold; text-transform: uppercase; margin-bottom: 8px; }
        .checkbox-row { display: flex; align-items: center; gap: 10px; margin: 5px 0; }
        .checkbox-row input { width: 15px; height: 15px; }
        .subjects-table { width: 100%; border-collapse: collapse; margin-top: 10px; font-size: 9px; }
        .subjects-table th, .subjects-table td { border: 1px solid #000; padding: 4px; text-align: center; }
        .subjects-table th { background-color: #f0f0f0; font-weight: bold; }
        .subjects-table td:first-child { width: 30px; }
        .subjects-table td:nth-child(2) { width: 80px; }
        .subjects-table td:nth-child(3) { text-align: left; padding-left: 3px; }
        .signature-section { margin-top: 20px; display: grid; grid-template-columns: repeat(2, 1fr); gap: 20px; }
        .sig-block { text-align: center; }
        .sig-line { border-top: 1px solid #000; margin-top: 40px; padding-top: 3px; font-size: 9px; }
        .standing-checkboxes { display: flex; gap: 30px; margin: 10px 0; }
        .standing-option { display: flex; align-items: center; gap: 5px; }
        .standing-option input { width: 15px; height: 15px; }
    </style>
</head>
<body>
    <div class="header">
        <img src="{{ asset('images/logo.png') }}" alt="School Logo" class="logo" onerror="this.style.display='none'">
        <div class="school-name">{{ config('settings.schoolName', 'SOUTHEAST ASIAN INSTITUTE OF TECHNOLOGY') }}</div>
        <div class="school-address">{{ config('settings.schoolAddress', '') }}</div>
        <div class="school-address">{{ config('settings.schoolPhone', '') }}</div>
    </div>

    <div class="title">Enrollment Form</div>

    <!-- Part 1: Demographic Profile -->
    <div class="section-title">Part 1: Demographic Profile</div>

    <div class="form-grid">
        <div class="field">
            <span class="label">Last Name:</span>
            <span class="value">{{ $enrollment->student->lastName }}</span>
        </div>
        <div class="field">
            <span class="label">First Name:</span>
            <span class="value">{{ $enrollment->student->firstName }}</span>
        </div>
        <div class="field">
            <span class="label">Middle Name:</span>
            <span class="value">{{ $enrollment->student->middleName }}</span>
        </div>
        <div class="field">
            <span class="label">Suffix:</span>
            <span class="value">{{ $enrollment->student->suffix }}</span>
        </div>
        <div class="field">
            <span class="label">Sex/Gender:</span>
            <span class="value">{{ ucfirst($enrollment->student->gender?->value ?? '') }}</span>
        </div>
        <div class="field">
            <span class="label">Date of Birth:</span>
            <span class="value">{{ $enrollment->student->birthdate?->format('F d, Y') ?? '' }}</span>
        </div>
        <div class="field">
            <span class="label">Place of Birth:</span>
            <span class="value">{{ $enrollment->student->birthplace }}</span>
        </div>
        <div class="field">
            <span class="label">Religion:</span>
            <span class="value">{{ $enrollment->student->religion?->religionName ?? '' }}</span>
        </div>
        <div class="field">
            <span class="label">Citizenship:</span>
            <span class="value">{{ $enrollment->student->citizenship }}</span>
        </div>
        <div class="field">
            <span class="label">Civil Status:</span>
            <span class="value">{{ ucfirst($enrollment->student->civilStatus?->value ?? '') }}</span>
        </div>
        <div class="field">
            <span class="label">Contact Number:</span>
            <span class="value">{{ $enrollment->student->contactNumber }}</span>
        </div>
        <div class="field">
            <span class="label">Telephone Number:</span>
            <span class="value">{{ $enrollment->student->telephoneNumber }}</span>
        </div>
        <div class="field">
            <span class="label">Email:</span>
            <span class="value">{{ $enrollment->student->email }}</span>
        </div>
        <div class="field">
            <span class="label">Semesters Completed:</span>
            <span class="value">{{ $enrollment->student->semestersCompleted }}</span>
        </div>
        <div class="field">
            <span class="label">Years in Institution:</span>
            <span class="value">{{ $enrollment->student->yearsInInstitution }}</span>
        </div>
    </div>

    <!-- Addresses -->
    <div class="section-title">Addresses</div>
    @foreach($enrollment->student->addresses as $address)
    <div class="address-section">
        <div class="address-title">{{ ucfirst($address->addressType?->value ?? '') }} Address</div>
        <div class="form-grid">
            <div class="field">
                <span class="label">House/Building No:</span>
                <span class="value">{{ $address->houseBuildingNo }}</span>
            </div>
            <div class="field">
                <span class="label">Street:</span>
                <span class="value">{{ $address->street }}</span>
            </div>
            <div class="field">
                <span class="label">Sitio/Purok:</span>
                <span class="value">{{ $address->sitioPurok }}</span>
            </div>
            <div class="field">
                <span class="label">Barangay:</span>
                <span class="value">{{ $address->barangay }}</span>
            </div>
            <div class="field">
                <span class="label">City/Municipality:</span>
                <span class="value">{{ $address->cityMunicipality }}</span>
            </div>
            <div class="field">
                <span class="label">District:</span>
                <span class="value">{{ $address->district }}</span>
            </div>
            <div class="field">
                <span class="label">Province:</span>
                <span class="value">{{ $address->province }}</span>
            </div>
            <div class="field">
                <span class="label">Region:</span>
                <span class="value">{{ $address->region }}</span>
            </div>
            <div class="field">
                <span class="label">Zip Code:</span>
                <span class="value">{{ $address->zipCode }}</span>
            </div>
            <div class="field">
                <span class="label">Country:</span>
                <span class="value">{{ $address->country }}</span>
            </div>
        </div>
        @if($address->addressType === 'home')
        <div class="checkbox-row">
            <input type="checkbox" id="sameAsAbove" {{ $enrollment->student->addresses->where('addressType', 'current')->first()?->street === $address->street ? 'checked' : '' }}>
            <label for="sameAsAbove">Same as above (Current Address)</label>
        </div>
        @endif
    </div>
    @endforeach

    <!-- Guardians -->
    <div class="section-title">Guardian Information</div>
    @foreach($enrollment->student->guardians as $guardian)
    <div class="form-grid">
        <div class="field">
            <span class="label">Relationship:</span>
            <span class="value">{{ ucfirst($guardian->relationship?->value ?? '') }}</span>
        </div>
        <div class="field">
            <span class="label">Full Name:</span>
            <span class="value">{{ $guardian->fullName }}</span>
        </div>
        <div class="field">
            <span class="label">Contact Number:</span>
            <span class="value">{{ $guardian->contactNumber }}</span>
        </div>
        <div class="field">
            <span class="label">Email:</span>
            <span class="value">{{ $guardian->email }}</span>
        </div>
        <div class="field">
            <span class="label">Emergency Contact:</span>
            <span class="value">{{ $guardian->isEmergencyContact ? 'Yes' : 'No' }}</span>
        </div>
        <div class="field">
            <span class="label">Authorized to Act:</span>
            <span class="value">{{ $guardian->isAuthorizedToActOnBehalf ? 'Yes' : 'No' }}</span>
        </div>
    </div>
    @endforeach

    <!-- Part 2: Subject Load -->
    <div class="section-title">Part 2: Subject Load</div>

    <div class="form-grid">
        <div class="field">
            <span class="label">Student Type:</span>
            <span class="value">{{ ucfirst($enrollment->studentType->value) }}</span>
        </div>
        <div class="field">
            <span class="label">Academic Standing:</span>
            <span class="value">
                <div class="standing-checkboxes">
                    <div class="standing-option">
                        <input type="radio" name="academicStanding" value="regular" {{ $enrollment->academicStanding->value === 'regular' ? 'checked' : '' }} disabled>
                        <label>Regular</label>
                    </div>
                    <div class="standing-option">
                        <input type="radio" name="academicStanding" value="irregular" {{ $enrollment->academicStanding->value === 'irregular' ? 'checked' : '' }} disabled>
                        <label>Irregular</label>
                    </div>
                </div>
            </span>
        </div>
        <div class="field">
            <span class="label">Form Issue Date:</span>
            <span class="value">{{ $enrollment->formIssuedDate?->format('F d, Y') ?? '' }}</span>
        </div>
    </div>

    <table class="subjects-table">
        <thead>
            <tr>
                <th>No.</th>
                <th>Subject Code</th>
                <th>Description</th>
                <th>Lec Units</th>
                <th>Lab Units</th>
                <th>Total</th>
            </tr>
        </thead>
        <tbody>
            @php
                $totalLec = 0;
                $totalLab = 0;
                $counter = 0;
            @endphp
            @foreach($enrollment->enrolledSubjects as $es)
                @php
                    $counter++;
                    $totalLec += $es->subject->lectureUnits;
                    $totalLab += $es->subject->labUnits;
                @endphp
                <tr>
                    <td>{{ $counter }}</td>
                    <td>{{ $es->subject->subjectCode }}</td>
                    <td style="text-align: left;">{{ $es->subject->subjectName }}</td>
                    <td>{{ $es->subject->lectureUnits }}</td>
                    <td>{{ $es->subject->labUnits }}</td>
                    <td>{{ $es->subject->lectureUnits + $es->subject->labUnits }}</td>
                </tr>
            @endforeach
            <tr style="font-weight: bold;">
                <td colspan="3" style="text-align: right;">TOTAL</td>
                <td>{{ $totalLec }}</td>
                <td>{{ $totalLab }}</td>
                <td>{{ $totalLec + $totalLab }}</td>
            </tr>
        </tbody>
    </table>

    <!-- Signatures -->
    <div class="section-title">Signatures</div>
    <div class="signature-section">
        <div class="sig-block">
            <div class="sig-line">Evaluator</div>
            <div class="sig-line">{{ $enrollment->evaluatedBy ? $enrollment->evaluatedByUser->firstName.' '.$enrollment->evaluatedByUser->lastName : '________________________' }}</div>
        </div>
        <div class="sig-block">
            <div class="sig-line">Dean / Program Head</div>
            <div class="sig-line">________________________</div>
        </div>
        <div class="sig-block">
            <div class="sig-line">Registrar</div>
            <div class="sig-line">________________________</div>
        </div>
        <div class="sig-block">
            <div class="sig-line">Student Signature</div>
            <div class="sig-line">________________________</div>
            <div class="sig-line">Date: {{ $enrollment->formSignedDate ? $enrollment->formSignedDate->format('F d, Y') : '________________' }}</div>
        </div>
    </div>
</body>
</html>