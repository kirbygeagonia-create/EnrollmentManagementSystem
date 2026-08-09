<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Block & Schedule - {{ $block->blockName }}</title>
    <style>
        body { font-family: 'DejaVu Sans', Arial, sans-serif; margin: 15px; font-size: 9px; }
        .header { text-align: center; margin-bottom: 15px; border-bottom: 2px solid #000; padding-bottom: 10px; }
        .logo { width: 60px; height: 60px; margin-bottom: 5px; }
        .school-name { font-size: 14px; font-weight: bold; text-transform: uppercase; }
        .school-address { font-size: 9px; margin: 1px 0; }
        .title { font-size: 13px; font-weight: bold; text-transform: uppercase; text-decoration: underline; text-align: center; margin: 10px 0; }
        .block-info { display: grid; grid-template-columns: repeat(3, 1fr); gap: 10px; margin-bottom: 15px; font-size: 9px; }
        .info-field { display: flex; }
        .info-label { width: 80px; font-weight: bold; }
        .info-value { flex: 1; border-bottom: 1px solid #000; padding-bottom: 1px; }
        .schedule-table { width: 100%; border-collapse: collapse; font-size: 8px; }
        .schedule-table th, .schedule-table td { border: 1px solid #000; padding: 3px; text-align: center; }
        .schedule-table th { background-color: #f0f0f0; font-weight: bold; }
        .schedule-table td:first-child { text-align: center; width: 30px; }
        .schedule-table td:nth-child(2) { text-align: left; padding-left: 3px; width: 120px; }
        .schedule-table td:nth-child(3) { text-align: left; padding-left: 3px; }
        .day-header { background-color: #e0e0e0; font-weight: bold; }
        .footer { margin-top: 15px; text-align: center; font-size: 8px; }
    </style>
</head>
<body>
    <div class="header">
        <img src="{{ asset('images/logo.png') }}" alt="School Logo" class="logo" onerror="this.style.display='none'">
        <div class="school-name">{{ config('settings.schoolName', 'SOUTHEAST ASIAN INSTITUTE OF TECHNOLOGY') }}</div>
        <div class="school-address">{{ config('settings.schoolAddress', '') }}</div>
        <div class="school-address">{{ config('settings.schoolPhone', '') }}</div>
    </div>

    <div class="title">Block & Schedule</div>

    <div class="block-info">
        <div class="info-field">
            <span class="info-label">Block:</span>
            <span class="info-value">{{ $block->blockName }}</span>
        </div>
        <div class="info-field">
            <span class="info-label">Course:</span>
            <span class="info-value">{{ $block->course->courseName }}</span>
        </div>
        <div class="info-field">
            <span class="info-label">Year Level:</span>
            <span class="info-value">{{ $block->yearLevel }}</span>
        </div>
        <div class="info-field">
            <span class="info-label">Term:</span>
            <span class="info-value">{{ $block->term->semester->value }} Semester</span>
        </div>
        <div class="info-field">
            <span class="info-label">Academic Year:</span>
            <span class="info-value">{{ $block->term->academicYear->yearLabel }}</span>
        </div>
        <div class="info-field">
            <span class="info-label">Max Students:</span>
            <span class="info-value">{{ $block->maxStudents }}</span>
        </div>
    </div>

    <table class="schedule-table">
        <thead>
            <tr>
                <th style="width: 30px;">#</th>
                <th style="width: 120px;">Subject</th>
                <th style="width: 80px;">Day</th>
                <th style="width: 80px;">Time</th>
                <th style="width: 80px;">Room</th>
                <th style="width: 100px;">Instructor</th>
            </tr>
        </thead>
        <tbody>
            @php
                $counter = 0;
                $currentDay = '';
            @endphp
            @foreach($block->schedules->sortBy(fn($s) => $s->meetings->first()?->dayOfWeek->value ?? '') as $schedule)
                @foreach($schedule->meetings as $meeting)
                    @php
                        $counter++;
                    @endphp
                    <tr>
                        <td>{{ $counter }}</td>
                        <td style="text-align: left;">{{ $schedule->subject->subjectCode }} - {{ $schedule->subject->subjectName }}</td>
                        <td>{{ $meeting->dayOfWeek->value }}</td>
                        <td>{{ \Illuminate\Support\Carbon::parse($meeting->startTime)->format('H:i') }} - {{ \Illuminate\Support\Carbon::parse($meeting->endTime)->format('H:i') }}</td>
                        <td>{{ $schedule->room->roomName }} ({{ $schedule->room->building }})</td>
                        <td>{{ $schedule->instructor->firstName }} {{ $schedule->instructor->lastName }}</td>
                    </tr>
                @endforeach
            @endforeach
        </tbody>
    </table>

    <div class="footer">
        Generated on {{ now()->format('F d, Y H:i') }} | Block Capacity: {{ $block->enrolledSubjects->count() }}/{{ $block->maxStudents }}
    </div>
</body>
</html>