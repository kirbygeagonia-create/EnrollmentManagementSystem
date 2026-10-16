<?php
$p = new PDO('mysql:host=127.0.0.1;dbname=ems', 'root', '');
echo "what the Evaluation desk will actually look for (enrollment course + year level + term semester):\n";
foreach ($p->query("SELECT c.courseCode, e.yearLevel, at.semester, COUNT(*) enrollments, MIN(cs.curriculumId) cid
   FROM enrollments e JOIN courses c ON c.courseId=e.courseId JOIN academicterms at ON at.termId=e.termId
   LEFT JOIN curriculums cu ON cu.courseId=c.courseId
   LEFT JOIN curriculumsubjects cs ON cs.curriculumId=cu.curriculumId AND cs.yearLevel=e.yearLevel AND cs.semesterOffered=at.semester
   GROUP BY 1,2,3 ORDER BY 1,2") as $r) {
    printf("  %-9s Y%-2s %-5s enrollments=%-3s subjects offered at that level/term=%s\n", $r['courseCode'], $r['yearLevel'], $r['semester'], $r['enrollments'], $r['cid'] ? 'yes' : 'NONE');
}
echo "\nsubjects available per curriculum+level+term (top of list):\n";
foreach ($p->query("SELECT c.courseCode, cs.yearLevel, cs.semesterOffered, COUNT(*) n, GROUP_CONCAT(s.subjectCode ORDER BY s.subjectCode) codes
   FROM curriculumsubjects cs JOIN curriculums cu ON cu.curriculumId=cs.curriculumId JOIN courses c ON c.courseId=cu.courseId
   JOIN subjects s ON s.subjectId=cs.subjectId GROUP BY 1,2,3 HAVING n >= 2 ORDER BY 1,2,3 LIMIT 12") as $r) {
    printf("  %-9s Y%-2s %-5s n=%-3s %s\n", $r['courseCode'], $r['yearLevel'], $r['semesterOffered'], $r['n'], $r['codes']);
}
