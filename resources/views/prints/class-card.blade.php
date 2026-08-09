<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Class Card - {{ $enrollment->student->schoolIdNumber }} - {{ $subject->subjectCode }}</title>
    <style>
        body { font-family: 'DejaVu Sans', Arial, sans-serif; margin: 15px; font-size: 10px; }
        .header { text-align: center; margin-bottom: 15px; border-bottom: 2px solid #000; padding-bottom: 10px; }
        .logo { width: 60px; height: 60px; margin-bottom: 5px; }
        .school-name { font-size: 14px; font-weight: bold; text-transform: uppercase; }
        .school-address { font-size: 9px; margin: 1px 0; }
        .office-title { font-size: 11px; font-weight: bold; margin: 5px 0; }
        .card-title { font-size: 13px; font-weight: bold; text-transform: uppercase; text-decoration: underline; text-align: center; margin: 10px 0; }
        .semester-year { text-align: center; font-size: 10px; margin-bottom: 10px; }
        .student-block { display: grid; grid-template-columns: repeat(2, 1fr); gap: 5px; margin-bottom: 10px; }
        .block-field { display: flex; }
        .block-label { width: 80px; font-weight: bold; font-size: 9px; }
        .block-value { flex: 1; border-bottom: 1px solid #000; padding-bottom: 1px; font-size: 10px; }
        .subject-info { border: 1px solid #000; padding: 8px; margin-bottom: 10px; }
        .subject-row { display: flex; margin: 3px 0; }
        .subject-label { width: 100px; font-weight: bold; font-size: 9px; }
        .subject-value { flex: 1; border-bottom: 1px solid #000; padding-bottom: 1px; font-size: 10px; }
        .boxes-grid { display: grid; grid-template-columns: repeat(2, 1fr); gap: 10px; margin-top: 15px; }
        .box { border: 1px solid #000; padding: 5px; min-height: 60px; }
        .box-title { font-weight: bold; font-size: 9px; text-transform: uppercase; margin-bottom: 5px; border-bottom: 1px solid #000; padding-bottom: 2px; }
        .box-content { font-size: 9px; }
        .footer { margin-top: 15px; display: flex; justify-content: space-between; font-size: 9px; }
        .footer-field { display: flex; flex-direction: column; }
        .footer-label { font-weight: bold; margin-bottom: 2px; }
        .footer-value { border-bottom: 1px solid #000; padding-bottom: 2px; min-width: 150px; }
    </style>
</head>
<body>
    <div class="header">
        <img src="{{ asset('images/logo.png') }}" alt="School Logo" class="logo" onerror="this.style.display='none'">
        <div class="school-name">{{ config('settings.schoolName', 'SOUTHEAST ASIAN INSTITUTE OF TECHNOLOGY') }}</div>
        <div class="school-address">{{ config('settings.schoolAddress', '') }}</div>
        <div class="school-address">{{ config('settings.schoolPhone', '') }}</div>
        <div class="office-title">OFFICE OF THE REGISTRAR</div>
        <div class="card-title">Class Card</div>
        <div class="semester-year">{{ $enrollment->term->semester->value }} Semester, {{ $enrollment->term->academicYear->yearLabel }}</div>
    </div>

    <div class="student-block">
        <div class="block-field">
            <span class="block-label">Last Name:</span>
            <span class="block-value">{{ $enrollment->student->lastName }}</span>
        </div>
        <div class="block-field">
            <span class="block-label">First Name:</span>
            <span class="block-value">{{ $enrollment->student->firstName }}</span>
        </div>
        <div class="block-field">
            <span class="block-label">Middle Name:</span>
            <span class="block-value">{{ $enrollment->student->middleName }}</span>
        </div>
        <div class="block-field">
            <span class="block-label">Course & Year:</span>
            <span class="block-value">{{ $enrollment->course->courseName }} - {{ $enrollment->yearLevel }}{{ $enrollment->major ? ' - '.$enrollment->major->majorName : '' }}</span>
        </div>
    </div>

    <div class="subject-info">
        <div class="subject-row">
            <span class="subject-label">Subject Code:</span>
            <span class="subject-value">{{ $subject->subjectCode }}</span>
        </div>
        <div class="subject-row">
            <span class="subject-label">Description:</span>
            <span class="subject-value">{{ $subject->subjectName }}</span>
        </div>
        <div class="subject-row">
            <span class="subject-label">Units:</span>
            <span class="subject-value">{{ $subject->lectureUnits + $subject->labUnits }} (Lec: {{ $subject->lectureUnits }}, Lab: {{ $subject->labUnits }})</span>
        </div>
    </div>

    <div class="boxes-grid">
        <div class="box">
            <div class="box-title">Set</div>
            <div class="box-content">{{ $schedule?->block?->blockName ?? '' }}</div>
        </div>
        <div class="box">
            <div class="box-title">Time</div>
            <div class="box-content">
                @if($schedule && $schedule->meetings->count())
                    @foreach($schedule->meetings as $meeting)
                        {{ $meeting->dayOfWeek->value }} {{ \Illuminate\Support\Carbon::parse($meeting->startTime)->format('H:i') }}-{{ \Illuminate\Support\Carbon::parse($meeting->endTime)->format('H:i') }}<br>
                    @endforeach
                @endif
            </div>
        </div>
        <div class="box">
            <div class="box-title">Day</div>
            <div class="box-content">
                @if($schedule && $schedule->meetings->count())
                    @foreach($schedule->meetings as $meeting)
                        {{ $meeting->dayOfWeek->value }}<br>
                    @endforeach
                @endif
            </div>
        </div>
        <div class="box">
            <div class="box-title">Grade</div>
            <div class="box-content"></div>
        </div>
        <div class="box">
            <div class="box-title">Name & Signature of Instructor</div>
            <div class="box-content">{{ $schedule?->instructor?->firstName }} {{ $schedule?->instructor?->lastName }}</div>
        </div>
        <div class="box">
            <div class="box-title">Date</div>
            <div class="box-content"></div>
        </div>
    </div>

    <div class="footer">
        <div class="footer-field">
            <span class="footer-label">Issued by:</span>
            <span class="footer-value">{{ $enrollment->registrarProcessedBy ? $enrollment->registrarProcessedByUser->firstName.' '.$enrollment->registrarProcessedByUser->lastName : '________________' }}</span>
        </div>
        <div class="footer-field">
            <span class="footer-label">Date:</span>
            <span class="footer-value">{{ now()->format('F d, Y') }}</span>
        </div>
    </div>
</body>
</html>