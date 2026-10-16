<?php
$p = new PDO('mysql:host=127.0.0.1;dbname=ems', 'root', '');
echo "terms:\n";
foreach ($p->query('SELECT termId, semester, academicYearId FROM academicterms ORDER BY termId') as $r) {
    printf("  %s %-8s ay=%s\n", $r['termId'], $r['semester'], $r['academicYearId']);
}
echo "\nenrollments seated in a Summer term:\n";
foreach ($p->query('SELECT e.enrollmentId, s.schoolIdNumber, CONCAT(s.firstName," ",s.lastName) nm, c.courseCode, e.yearLevel, e.enrollmentStatus, e.studentType
   FROM enrollments e JOIN students s ON s.studentId=e.studentId JOIN courses c ON c.courseId=e.courseId
   JOIN academicterms at ON at.termId=e.termId WHERE at.semester="Summer" ORDER BY e.enrollmentId') as $r) {
    printf("  #%-4s %-12s %-20s %-7s Y%s %-9s %s\n", $r['enrollmentId'], $r['schoolIdNumber'], $r['nm'], $r['courseCode'], $r['yearLevel'], $r['enrollmentStatus'], $r['studentType']);
}
