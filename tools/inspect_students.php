<?php
$pdo = new PDO('mysql:host=127.0.0.1;dbname=ems', 'root', '');
echo "--- STUDENTS ---\n";
$stmt = $pdo->query("SELECT studentId, schoolIdNumber, firstName, lastName, username, status FROM students");
while ($r = $stmt->fetch(PDO::FETCH_ASSOC)) {
    echo "ID: {$r['studentId']} | {$r['schoolIdNumber']} | {$r['firstName']} {$r['lastName']} | {$r['username']} | {$r['status']}\n";
}

echo "\n--- ADMISSIONS ---\n";
$stmt = $pdo->query("SELECT admissionId, studentId, courseId, applicantType, admissionStatus FROM admissions");
while ($r = $stmt->fetch(PDO::FETCH_ASSOC)) {
    echo "Admission #{$r['admissionId']} | Student #{$r['studentId']} | Course #{$r['courseId']} | {$r['applicantType']} | Status: {$r['admissionStatus']}\n";
}

echo "\n--- ENROLLMENTS ---\n";
$stmt = $pdo->query("SELECT enrollmentId, studentId, courseId, yearLevel, studentType, enrollmentStatus FROM enrollments");
while ($r = $stmt->fetch(PDO::FETCH_ASSOC)) {
    echo "Enrollment #{$r['enrollmentId']} | Student #{$r['studentId']} | Course #{$r['courseId']} | Year: {$r['yearLevel']} | Type: {$r['studentType']} | Status: {$r['enrollmentStatus']}\n";
}

echo "\n--- ASSESSMENTS ---\n";
$stmt = $pdo->query("SELECT assessmentId, enrollmentId, totalAssessedAmount, remainingBalance, assessmentDate FROM studentassessments");
while ($r = $stmt->fetch(PDO::FETCH_ASSOC)) {
    echo "Assessment #{$r['assessmentId']} | Enrollment #{$r['enrollmentId']} | Total: {$r['totalAssessedAmount']} | Balance: {$r['remainingBalance']}\n";
}

echo "\n--- CLEARANCES ---\n";
$stmt = $pdo->query("SELECT clearanceId, studentId, overallStatus, receivedDate FROM studentclearances");
while ($r = $stmt->fetch(PDO::FETCH_ASSOC)) {
    echo "Clearance #{$r['clearanceId']} | Student #{$r['studentId']} | Overall: {$r['overallStatus']} | Received: {$r['receivedDate']}\n";
}
