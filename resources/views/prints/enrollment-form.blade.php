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
        .standing-pending { font-size: 8px; font-style: italic; }
        .standing-option input { width: 15px; height: 15px; }
    </style>
</head>
<body>
    <div class="header">
        <img src="{{ \App\Support\PrintAssets::logoDataUri() }}" alt="School Logo" class="logo" onerror="this.style.display='none'">
        <div class="school-name">{{ config('settings.schoolName', 'SOUTH EAST ASIAN INSTITUTE OF TECHNOLOGY, INC.') }}</div>
        <div class="school-address">{{ config('settings.schoolAddress', '') }}</div>
        <div class="school-address">{{ config('settings.schoolPhone', '') }}</div>
    </div>

    <div class="title">Enrollment Form</div>

    <!--
        Identification block (P-2). The form used to print none of this, so a page that left the
        Registrar's desk could not be tied back to the file it belonged in: no student number, no
        course, no year level, no semester, no school year. The five labels are the school's own —
        Student ID, Course, Year Level, School Year, Semester are the words its Subject Load paper
        uses — and the values come from the same relations the Subject Load reads, which the desk's
        own eager-load list already carries (tests/Feature/Print/PrintEnrollmentFormPayloadTest.php
        pins that list, so this block costs no extra query). Year level comes from the record and
        prints "Year N/A" when the record cannot answer it — never a constant. The Document No. is
        the print-log number of the issuance that drew this sheet, so a page on a desk traces back
        to the print that produced it; it is absent only when a caller renders the form without
        recording an issuance.
    -->
    <div class="form-grid">
        <div class="field">
            <span class="label">Student ID:</span>
            <span class="value">{{ $enrollment->student->schoolIdNumber }}</span>
        </div>
        <div class="field">
            <span class="label">School Year:</span>
            <span class="value">{{ $enrollment->term?->academicYear?->yearLabel }}</span>
        </div>
        @isset($documentNumber)
        <div class="field">
            <span class="label">Document No.:</span>
            <span class="value">{{ $documentNumber }}</span>
        </div>
        @endisset
        <div class="field full-width">
            <span class="label">Course:</span>
            <span class="value">{{ $enrollment->course?->courseName }}{{ $enrollment->major ? ' - '.$enrollment->major->majorName : '' }}</span>
        </div>
        <div class="field">
            <span class="label">Year Level:</span>
            <span class="value">{{ \App\Support\YearLevel::format($enrollment->yearLevel) ?? 'Year N/A' }}</span>
        </div>
        <div class="field">
            <span class="label">Semester:</span>
            <span class="value">{{ $enrollment->term?->semester?->value }}</span>
        </div>
    </div>

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
        {{-- These two are the school's own counters, not derived here: intake zeroes both and one
             controller maintains them (§30.4 records that), which is why the Year Level above is read
             from the enrollment and these are read from the student. Ruled 2026-10-23 to stay on the
             paper, because the Registrar's demographic sheet asks for them. --}}
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
    @php
        // "Same as the home address" is true only when every line a courier reads matches, not when
        // the street alone agrees. Both enum comparisons must go through ->value: `=== 'home'` and
        // `where('addressType', 'current')` are each false against a backed enum object, and that
        // pair is what kept this box from ever printing (P-18).
        $postalTuple = fn ($a): array => array_map(
            static fn ($v): string => trim((string) $v),
            [$a->houseBuildingNo, $a->street, $a->sitioPurok, $a->barangay, $a->cityMunicipality, $a->zipCode],
        );
        $currentAddress = $enrollment->student->addresses
            ->first(fn ($a) => $a->addressType?->value === 'current');
    @endphp
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
        @if($address->addressType?->value === 'home')
        <div class="checkbox-row">
            <input type="checkbox" id="sameAsCurrentAddress" {{ $currentAddress !== null && $postalTuple($currentAddress) === $postalTuple($address) ? 'checked' : '' }}>
            <label for="sameAsCurrentAddress">Current address is the same as this home address</label>
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
            <span class="value">{{ \Illuminate\Support\Str::headline($enrollment->studentType->value) }}</span>
        </div>
        <div class="field">
            <span class="label">Academic Standing:</span>
            <span class="value">
                <div class="standing-checkboxes">
                    <div class="standing-option">
                        <input type="radio" name="academicStanding" value="regular" {{ $enrollment->academicStanding?->value === 'regular' ? 'checked' : '' }} disabled>
                        <label>Regular</label>
                    </div>
                    <div class="standing-option">
                        <input type="radio" name="academicStanding" value="irregular" {{ $enrollment->academicStanding?->value === 'irregular' ? 'checked' : '' }} disabled>
                        <label>Irregular</label>
                    </div>
                </div>
                {{-- Neither box checked is the honest rendering of a standing the
                     evaluating department has not decided yet — it is their call
                     (from the grades on file) and the Registrar's to finalize, so
                     this form must not imply a box was ticked by someone. --}}
                @if(! $enrollment->academicStanding)
                    <div class="standing-pending">To be determined by the Department Evaluation and confirmed by the Registrar.</div>
                @endif
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
                // Only confirmed subjects are the registered load of the term. Proposed and dropped
                // rows are workflow state — the department's intention and the Registrar's deletion —
                // so counting them here let this form state units the Subject Load, which filters the
                // same way, refuses to print. Measured on live ems 2026-10-23: 54 such rows across 18
                // enrollments, and every one of those 18 holds no confirmed row at all, so each
                // printed a full subject table for a load nobody confirmed (P-18).
                $confirmed = $enrollment->enrolledSubjects->filter(
                    fn ($es) => $es->status === \App\Enums\EnrolledSubjectStatus::Confirmed
                )->values();
                $totalLec = 0;
                $totalLab = 0;
                $counter = 0;
            @endphp
            @if($confirmed->isEmpty())
                <tr>
                    <td colspan="6" style="font-style: italic;">No confirmed subjects for this term.</td>
                </tr>
            @endif
            @foreach($confirmed as $es)
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