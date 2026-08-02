<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Enrollment Certificate - {{ $enrollment->student->schoolIdNumber }}</title>
    <style>
        body { font-family: 'DejaVu Sans', Arial, sans-serif; margin: 20px; font-size: 11px; }
        .header { text-align: center; margin-bottom: 20px; border-bottom: 2px solid #000; padding-bottom: 10px; }
        .logo { width: 70px; height: 70px; margin-bottom: 5px; }
        .school-name { font-size: 16px; font-weight: bold; text-transform: uppercase; }
        .school-address { font-size: 10px; margin: 1px 0; }
        .title { font-size: 14px; font-weight: bold; text-transform: uppercase; margin: 15px 0; text-decoration: underline; text-align: center; }
        .student-info { display: grid; grid-template-columns: repeat(2, 1fr); gap: 8px; margin-bottom: 15px; }
        .info-field { display: flex; }
        .info-label { width: 120px; font-weight: bold; font-size: 10px; }
        .info-value { flex: 1; border-bottom: 1px solid #000; padding-bottom: 2px; font-size: 11px; }
        .subjects-table { width: 100%; border-collapse: collapse; margin-top: 10px; font-size: 10px; }
        .subjects-table th, .subjects-table td { border: 1px solid #000; padding: 5px; text-align: center; }
        .subjects-table th { background-color: #f0f0f0; font-weight: bold; }
        .subjects-table td:first-child { text-align: center; width: 40px; }
        .subjects-table td:nth-child(2) { text-align: center; width: 80px; }
        .subjects-table td:nth-child(3) { text-align: left; padding-left: 5px; }
        .totals-row { font-weight: bold; background-color: #f9f9f9; }
        .footer-info { margin-top: 20px; display: grid; grid-template-columns: repeat(2, 1fr); gap: 10px; font-size: 10px; }
        .footer-field { display: flex; }
        .footer-label { width: 100px; font-weight: bold; }
        .footer-value { flex: 1; border-bottom: 1px solid #000; padding-bottom: 2px; }
        .stamp { border: 3px solid #000; padding: 15px; text-align: center; font-weight: bold; font-size: 14px; margin: 20px auto; width: 250px; background: #fff; }
        .copy-notice { text-align: center; font-size: 10px; margin-top: 10px; font-weight: bold; }
    </style>
</head>
<body>
    <div class="header">
        <img src="{{ asset('images/logo.png') }}" alt="School Logo" class="logo" onerror="this.style.display='none'">
        <div class="school-name">{{ config('settings.schoolName', 'SOUTHEAST ASIAN INSTITUTE OF TECHNOLOGY') }}</div>
        <div class="school-address">{{ config('settings.schoolAddress', '') }}</div>
        <div class="school-address">{{ config('settings.schoolPhone', '') }}</div>
    </div>

    <div class="title">Enrollment Certificate</div>

    <div class="student-info">
        <div class="info-field">
            <span class="info-label">Name:</span>
            <span class="info-value">{{ $enrollment->student->lastName }}, {{ $enrollment->student->firstName }} {{ $enrollment->student->middleName ? $enrollment->student->middleName[0].'.' : '' }} {{ $enrollment->student->suffix }}</span>
        </div>
        <div class="info-field">
            <span class="info-label">Course & Year:</span>
            <span class="info-value">{{ $enrollment->course->courseName }} - {{ $enrollment->yearLevel }}{{ $enrollment->major ? ' - '.$enrollment->major->majorName : '' }}</span>
        </div>
        <div class="info-field">
            <span class="info-label">Student ID:</span>
            <span class="info-value">{{ $enrollment->student->schoolIdNumber }}</span>
        </div>
        <div class="info-field">
            <span class="info-label">School Year:</span>
            <span class="info-value">{{ $enrollment->term->academicYear->yearLabel }}</span>
        </div>
        <div class="info-field">
            <span class="info-label">Semester:</span>
            <span class="info-value">{{ $enrollment->term->semester->value }}</span>
        </div>
        <div class="info-field">
            <span class="info-label">Type:</span>
            <span class="info-value">{{ $enrollment->enrollmentType->value === 'new' ? 'New' : 'Old' }} Student</span>
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
            <tr class="totals-row">
                <td colspan="3" style="text-align: right;">TOTAL UNITS</td>
                <td>{{ $totalLec }}</td>
                <td>{{ $totalLab }}</td>
                <td>{{ $totalLec + $totalLab }}</td>
            </tr>
        </tbody>
    </table>

    <div class="footer-info">
        <div class="footer-field">
            <span class="footer-label">Date Enrolled:</span>
            <span class="footer-value">{{ $enrollment->enrolledDate ? $enrollment->enrolledDate->format('F d, Y') : '________________' }}</span>
        </div>
        <div class="footer-field">
            <span class="footer-label">Evaluated by:</span>
            <span class="footer-value">{{ $enrollment->evaluatedBy ? $enrollment->evaluatedByUser->firstName.' '.$enrollment->evaluatedByUser->lastName : '________________' }}</span>
        </div>
        <div class="footer-field">
            <span class="footer-label">Processed by:</span>
            <span class="footer-value">{{ $enrollment->registrarProcessedBy ? $enrollment->registrarProcessedByUser->firstName.' '.$enrollment->registrarProcessedByUser->lastName : '________________' }}</span>
        </div>
    </div>

    <div class="stamp">
        SEAIT ENROLLED
    </div>

    <div class="copy-notice">STUDENT COPY</div>
</body>
</html>