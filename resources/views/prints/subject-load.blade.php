<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Subject Load - {{ $enrollment->student->schoolIdNumber }}</title>
    <style>
        body { font-family: 'DejaVu Sans', Arial, sans-serif; margin: 20px; font-size: 11px; }
        .header { text-align: center; margin-bottom: 20px; border-bottom: 2px solid #000; padding-bottom: 10px; }
        .logo { width: 70px; height: 70px; margin-bottom: 5px; }
        .school-name { font-size: 16px; font-weight: bold; text-transform: uppercase; }
        .school-address { font-size: 10px; margin: 1px 0; }
        .title { font-size: 14px; font-weight: bold; text-transform: uppercase; margin: 15px 0; text-decoration: underline; text-align: center; }
        .subtitle { font-size: 11px; text-align: center; margin: -10px 0 15px 0; }
        .student-info { display: grid; grid-template-columns: repeat(2, 1fr); gap: 8px; margin-bottom: 15px; }
        .info-field { display: flex; }
        .info-label { width: 120px; font-weight: bold; font-size: 10px; }
        .info-value { flex: 1; border-bottom: 1px solid #000; padding-bottom: 2px; font-size: 11px; }
        .load-table { width: 100%; border-collapse: collapse; margin-top: 10px; font-size: 9px; }
        .load-table th, .load-table td { border: 1px solid #000; padding: 4px; text-align: center; }
        .load-table th { background-color: #f0f0f0; font-weight: bold; }
        .load-table td.subject-name { text-align: left; }
        .totals-row { font-weight: bold; background-color: #f9f9f9; }
        .empty { border: 1px solid #000; padding: 12px; text-align: center; font-style: italic; margin-top: 10px; }
        .signature-section { margin-top: 30px; display: flex; justify-content: space-between; }
        .signature-block { width: 30%; text-align: center; font-size: 10px; }
        .signature-line { border-top: 1px solid #000; margin-top: 35px; padding-top: 4px; }
        .footer { margin-top: 25px; text-align: center; font-size: 9px; }
    </style>
</head>
<body>
    <div class="header">
        <img src="{{ \App\Support\PrintAssets::logoDataUri() }}" alt="School Logo" class="logo" onerror="this.style.display='none'">
        <div class="school-name">{{ config('settings.schoolName', 'SOUTHEAST ASIAN INSTITUTE OF TECHNOLOGY') }}</div>
        <div class="school-address">{{ config('settings.schoolAddress', '') }}</div>
        <div class="school-address">{{ config('settings.schoolPhone', '') }}</div>
        <div class="office-title" style="font-size: 11px; font-weight: bold; margin-top: 4px;">OFFICE OF THE REGISTRAR</div>
    </div>

    <div class="title">Subject Load</div>
    <div class="subtitle">Confirmed subjects for {{ $enrollment->term->semester->value }} Semester, {{ $enrollment->term->academicYear->yearLabel }}</div>

    @php
        // Only confirmed subjects count toward a load — proposed and dropped rows
        // are workflow state, not the registered subjects of the term.
        $confirmed = $enrollment->enrolledSubjects->filter(
            fn ($es) => $es->status === \App\Enums\EnrolledSubjectStatus::Confirmed
        )->values();
        $totalLec = $confirmed->sum(fn ($es) => (int) $es->subject->lectureUnits);
        $totalLab = $confirmed->sum(fn ($es) => (int) $es->subject->labUnits);
    @endphp

    <div class="student-info">
        <div class="info-field">
            <span class="info-label">Name:</span>
            <span class="info-value">{{ $enrollment->student->lastName }}, {{ $enrollment->student->firstName }} {{ $enrollment->student->middleName ? $enrollment->student->middleName[0].'.' : '' }} {{ $enrollment->student->suffix }}</span>
        </div>
        <div class="info-field">
            <span class="info-label">Student ID:</span>
            <span class="info-value">{{ $enrollment->student->schoolIdNumber }}</span>
        </div>
        <div class="info-field">
            <span class="info-label">Course & Year:</span>
            <span class="info-value">{{ $enrollment->course->courseName }} - {{ $enrollment->yearLevel }}{{ $enrollment->major ? ' - '.$enrollment->major->majorName : '' }}</span>
        </div>
        <div class="info-field">
            <span class="info-label">Total Units:</span>
            <span class="info-value">{{ $totalLec + $totalLab }} (Lec: {{ $totalLec }}, Lab: {{ $totalLab }})</span>
        </div>
        <div class="info-field">
            <span class="info-label">Student Type:</span>
            <span class="info-value">{{ \Illuminate\Support\Str::headline($enrollment->studentType->value) }}</span>
        </div>
        <div class="info-field">
            <span class="info-label">Academic Standing:</span>
            <span class="info-value">{{ ucfirst($enrollment->academicStanding?->value ?? 'not yet decided') }}</span>
        </div>
    </div>

    @if($confirmed->isEmpty())
        <div class="empty">No confirmed subjects for this term.</div>
    @else
    <table class="load-table">
        <thead>
            <tr>
                <th style="width: 4%;">No.</th>
                <th style="width: 10%;">Subject Code</th>
                <th style="width: 26%;">Description</th>
                <th style="width: 6%;">Lec</th>
                <th style="width: 6%;">Lab</th>
                <th style="width: 6%;">Total</th>
                <th style="width: 22%;">Schedule</th>
                <th style="width: 9%;">Room</th>
                <th style="width: 11%;">Instructor</th>
            </tr>
        </thead>
        <tbody>
            @foreach($confirmed as $index => $es)
            <tr>
                <td>{{ $index + 1 }}</td>
                <td>{{ $es->subject->subjectCode }}</td>
                <td class="subject-name">{{ $es->subject->subjectName }}</td>
                <td>{{ $es->subject->lectureUnits }}</td>
                <td>{{ $es->subject->labUnits }}</td>
                <td>{{ $es->subject->lectureUnits + $es->subject->labUnits }}</td>
                <td>
                    @if($es->schedule?->meetings?->count())
                        @foreach($es->schedule->meetings as $meeting)
                            {{ $meeting->dayOfWeek->value }} {{ \Illuminate\Support\Carbon::parse($meeting->startTime)->format('H:i') }}-{{ \Illuminate\Support\Carbon::parse($meeting->endTime)->format('H:i') }}@if(!$loop->last)<br>@endif
                        @endforeach
                    @endif
                </td>
                <td>{{ $es->schedule?->room?->roomName }}</td>
                <td>{{ $es->schedule?->instructor ? $es->schedule->instructor->firstName.' '.$es->schedule->instructor->lastName : '' }}</td>
            </tr>
            @endforeach
            <tr class="totals-row">
                <td colspan="3" style="text-align: right;">TOTAL UNITS</td>
                <td>{{ $totalLec }}</td>
                <td>{{ $totalLab }}</td>
                <td>{{ $totalLec + $totalLab }}</td>
                <td colspan="3"></td>
            </tr>
        </tbody>
    </table>
    @endif

    <div class="signature-section">
        <div class="signature-block">
            <div class="signature-line">Student</div>
        </div>
        <div class="signature-block">
            <div class="signature-line">Class Adviser</div>
        </div>
        <div class="signature-block">
            <div class="signature-line">Registrar</div>
        </div>
    </div>

    <div class="footer">
        Subject load as recorded by the Office of the Registrar for {{ $enrollment->term->semester->value }} Semester,
        {{ $enrollment->term->academicYear->yearLabel }}. Valid only with the Registrar's signature.
    </div>
</body>
</html>
