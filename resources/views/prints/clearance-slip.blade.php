<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Clearance Slip - {{ $clearance->student->schoolIdNumber }}</title>
    <style>
        body { font-family: 'DejaVu Sans', Arial, sans-serif; margin: 20px; font-size: 12px; }
        .header { text-align: center; margin-bottom: 20px; border-bottom: 2px solid #000; padding-bottom: 10px; }
        .logo { width: 80px; height: 80px; margin-bottom: 10px; }
        .school-name { font-size: 18px; font-weight: bold; text-transform: uppercase; }
        .school-address { font-size: 11px; margin: 2px 0; }
        .title { font-size: 16px; font-weight: bold; text-transform: uppercase; margin: 15px 0; text-decoration: underline; }
        .info-row { display: flex; margin: 8px 0; }
        .info-label { width: 200px; font-weight: bold; }
        .info-value { flex: 1; border-bottom: 1px solid #000; padding-bottom: 2px; }
        .requirements-table { width: 100%; border-collapse: collapse; margin-top: 20px; }
        .requirements-table th, .requirements-table td { border: 1px solid #000; padding: 8px; text-align: left; }
        .requirements-table th { background-color: #f0f0f0; font-weight: bold; }
        .signature-section { margin-top: 30px; display: flex; justify-content: space-between; }
        .signature-block { width: 30%; text-align: center; }
        .signature-line { border-top: 1px solid #000; margin-top: 40px; padding-top: 5px; }
        .footer { margin-top: 30px; text-align: center; font-size: 10px; }
        .stamp { border: 2px solid #000; padding: 10px; text-align: center; font-weight: bold; margin: 20px auto; width: 200px; }
    </style>
</head>
<body>
    <div class="header">
        <img src="{{ asset('images/logo.png') }}" alt="School Logo" class="logo" onerror="this.style.display='none'">
        <div class="school-name">{{ config('settings.schoolName', 'SOUTHEAST ASIAN INSTITUTE OF TECHNOLOGY') }}</div>
        <div class="school-address">{{ config('settings.schoolAddress', '') }}</div>
        <div class="school-address">{{ config('settings.schoolPhone', '') }}</div>
    </div>

    <div class="title">Clearance Slip</div>

    <div class="info-row">
        <span class="info-label">Semester:</span>
        <span class="info-value">{{ $clearance->clearancePeriod->term->semester->value }} Semester</span>
    </div>
    <div class="info-row">
        <span class="info-label">Academic Year:</span>
        <span class="info-value">{{ $clearance->clearancePeriod->term->academicYear->yearLabel }}</span>
    </div>
    <div class="info-row">
        <span class="info-label">Student Name:</span>
        <span class="info-value">{{ $clearance->student->lastName }}, {{ $clearance->student->firstName }} {{ $clearance->student->middleName ? $clearance->student->middleName[0].'.' : '' }} {{ $clearance->student->suffix }}</span>
    </div>
    <div class="info-row">
        <span class="info-label">Course & Year:</span>
        <span class="info-value">{{ $clearance->student->enrollments->first()?->course->courseName ?? 'N/A' }} - {{ $clearance->student->enrollments->first()?->yearLevel ?? 'N/A' }} Year</span>
    </div>
    <div class="info-row">
        <span class="info-label">Date to be Signed:</span>
        <span class="info-value">{{ $clearance->clearancePeriod->clearanceEndDate->format('F d, Y') }}</span>
    </div>

    <table class="requirements-table">
        <thead>
            <tr>
                <th style="width: 5%;">#</th>
                <th style="width: 40%;">Office / Requirement</th>
                <th style="width: 20%;">Status</th>
                <th style="width: 20%;">Approved By</th>
                <th style="width: 15%;">Date</th>
            </tr>
        </thead>
        <tbody>
            @foreach($clearance->approvals as $index => $approval)
            <tr>
                <td>{{ $index + 1 }}</td>
                <td>{{ $approval->requirement->office->officeName }}</td>
                <td>{{ ucfirst($approval->status->value) }}</td>
                <td>{{ $approval->approvedBy ? $approval->approvedByUser->firstName.' '.$approval->approvedByUser->lastName : '-' }}</td>
                <td>{{ $approval->approvalDate ? $approval->approvalDate->format('M d, Y') : '-' }}</td>
            </tr>
            @endforeach
        </tbody>
    </table>

    <div class="signature-section">
        <div class="signature-block">
            <div class="signature-line">Received by Registrar</div>
            <div class="signature-line">{{ $clearance->receivedBy ? $clearance->receivedByUser->firstName.' '.$clearance->receivedByUser->lastName : '________________________' }}</div>
            <div class="signature-line">{{ $clearance->receivedDate ? $clearance->receivedDate->format('M d, Y H:i') : '________________________' }}</div>
        </div>
    </div>

    <div class="stamp">
        SEAIT CLEARED
    </div>

    <div class="footer">
        This clearance slip is valid only for the semester and academic year indicated above. 
        Lost slip replacement fee: ₱{{ config('settings.clearanceReplacementFee', '100.00') }}.
    </div>
</body>
</html>