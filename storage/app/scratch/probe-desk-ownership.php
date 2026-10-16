<?php
/**
 * Which office does the workflow data assign to each box, and is the demo tool's desk map what the app agrees?
 *
 * The tool keeps `office id => staff user id` as literals. A step's own officeId is the answer to who signs
 * it, so this prints the vocabulary from workflowsteps and, for each desk account the seeder makes, the role
 * it actually holds — which is how a map that says "office4_head — Department Evaluation" is checked against
 * the machine rather than against a comment.
 */
$database = $argv[1] ?? 'ems';
$pdo = new PDO('mysql:host=127.0.0.1;port=3306;dbname='.$database, 'root', '');

echo "database: {$database}\n\nworkflow template: which office owns which box (workflowsteps has no step name,\n";
echo "so the box is identified by its order and the office the row carries):\n";
$rows = $pdo->query(
    'SELECT s.workflowId, s.stepOrder, s.officeId, o.officeName, COUNT(*) AS instances,
            SUM(s.stepStatus = \'completed\') AS completed
       FROM workflowsteps s LEFT JOIN offices o ON o.officeId = s.officeId
      GROUP BY s.workflowId, s.stepOrder, s.officeId, o.officeName
      ORDER BY s.workflowId, s.stepOrder'
)->fetchAll(PDO::FETCH_ASSOC);
foreach ($rows as $r) {
    printf(
        "  workflow %-4s step %-3s office %-4s %-24s %s instance(s), %s completed\n",
        $r['workflowId'],
        $r['stepOrder'],
        $r['officeId'],
        $r['officeName'] ?? '(no office)',
        $r['instances'],
        $r['completed']
    );
}

echo "\ndesk accounts the seeder creates, with the roles actually attached:\n";
$acc = $pdo->query(
    "SELECT su.userId, su.username, su.officeId, o.officeName,
            GROUP_CONCAT(r.name ORDER BY r.name) AS roles
       FROM staffusers su
       LEFT JOIN offices o ON o.officeId = su.officeId
       LEFT JOIN staff_roles sr ON sr.model_id = su.userId AND sr.model_type LIKE '%Staffusers%'
       LEFT JOIN roles r ON r.id = sr.roleId
      WHERE su.username LIKE 'office%_head' OR su.username IN ('staff8','dean_academic')
      GROUP BY su.userId, su.username, su.officeId, o.officeName
      ORDER BY su.userId"
)->fetchAll(PDO::FETCH_ASSOC);
foreach ($acc as $a) {
    printf("  userId %-6s %-16s office %-3s %-22s roles: %s\n", $a['userId'], $a['username'], $a['officeId'], $a['officeName'] ?? '-', $a['roles'] ?? '(none)');
}

echo "\nwhat the demo rows currently credit as the evaluator:\n";
$ev = $pdo->query(
    'SELECT e.evaluatedBy, su.username, su.officeId, o.officeName, COUNT(*) AS n
       FROM enrollments e
       LEFT JOIN staffusers su ON su.userId = e.evaluatedBy
       LEFT JOIN offices o ON o.officeId = su.officeId
      WHERE e.evaluatedBy IS NOT NULL
      GROUP BY e.evaluatedBy, su.username, su.officeId, o.officeName
      ORDER BY n DESC'
)->fetchAll(PDO::FETCH_ASSOC);
foreach ($ev as $r) {
    printf("  evaluatedBy %-6s %-16s office %-3s %-22s %s enrollment(s)\n", $r['evaluatedBy'], $r['username'] ?? '(no such user)', $r['officeId'] ?? '-', $r['officeName'] ?? '-', $r['n']);
}
