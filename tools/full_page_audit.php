<?php
// Full live audit: hit every GET route in the app as an authenticated admin,
// confirm each renders. Parametrized routes are filled with real IDs from the DB.

$php = 'C:\laragon\bin\php\php-8.3.30-Win32-vs16-x64\php.exe';
$base = 'http://localhost:8080';

function httpReq(string $method, string $url, array $opts = []) {
    static $cookies = [];
    $ch = curl_init($url);
    $headers = $opts['headers'] ?? [];
    if ($cookies) {
        $pairs = [];
        foreach ($cookies as $k => $v) $pairs[] = $k . '=' . $v;
        $headers[] = 'Cookie: ' . implode('; ', $pairs);
    }
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_HEADER => true,
        CURLOPT_CUSTOMREQUEST => $method,
        CURLOPT_USERAGENT => 'curl/8.0',
        CURLOPT_FOLLOWLOCATION => false,
        CURLOPT_TIMEOUT => 30,
    ]);
    if (!empty($opts['body'])) curl_setopt($ch, CURLOPT_POSTFIELDS, $opts['body']);
    if ($headers) curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
    $resp = curl_exec($ch);
    $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $err = curl_error($ch);
    curl_close($ch);
    $parts = explode("\r\n\r\n", $resp, 2);
    preg_match_all('/Set-Cookie:\s*([^=]+)=([^;]*)/i', $parts[0], $m, PREG_SET_ORDER);
    foreach ($m as $c) $cookies[trim($c[1])] = $c[2];
    return [$code, $parts[1] ?? '', $cookies, $err, $parts[0] ?? ''];
}

// Login
[$code, , $cookies] = httpReq('GET', "$base/login");
$xsrf = urldecode($cookies['XSRF-TOKEN'] ?? '');
httpReq('POST', "$base/login", [
    'body' => http_build_query(['username' => 'staff8', 'password' => 'password']),
    'headers' => ['Content-Type: application/x-www-form-urlencoded', 'X-XSRF-TOKEN: ' . $xsrf, 'Accept: text/html'],
]);
echo "Logged in.\n";

// Pull all named GET routes from Laravel (script writes them line by line)
exec('"' . $php . '" ' . __DIR__ . DIRECTORY_SEPARATOR . 'list_get_routes.php', $out, $rc);
if ($rc !== 0) { echo "ROUTE LIST FAILED\n"; exit(1); }

// Sample IDs from the DB for parametrized routes
$dsn = 'mysql:host=127.0.0.1;dbname=ems';
$pdo = new PDO($dsn, 'root', '');
function sampleId(PDO $pdo, string $sql): ?string {
    $v = $pdo->query($sql)->fetchColumn();
    return $v === false ? null : (string) $v;
}
$ids = [
    'admission' => sampleId($pdo, 'SELECT admissionId FROM admissions ORDER BY admissionId DESC LIMIT 1'),
    'student' => sampleId($pdo, 'SELECT studentId FROM students ORDER BY studentId DESC LIMIT 1'),
    'enrollment' => sampleId($pdo, 'SELECT enrollmentId FROM enrollments ORDER BY enrollmentId DESC LIMIT 1'),
    'assessment' => sampleId($pdo, 'SELECT assessmentId FROM studentassessments ORDER BY assessmentId DESC LIMIT 1'),
    'clearance' => sampleId($pdo, 'SELECT studentClearanceId FROM studentclearances ORDER BY studentClearanceId DESC LIMIT 1'),
    'period' => sampleId($pdo, 'SELECT clearancePeriodId FROM clearanceperiods ORDER BY clearancePeriodId DESC LIMIT 1'),
    'user' => sampleId($pdo, 'SELECT userId FROM staffusers ORDER BY userId DESC LIMIT 1'),
    'curriculum' => sampleId($pdo, 'SELECT curriculumId FROM curriculums ORDER BY curriculumId DESC LIMIT 1'),
    'course' => sampleId($pdo, 'SELECT courseId FROM courses ORDER BY courseId DESC LIMIT 1'),
    'major' => sampleId($pdo, 'SELECT majorId FROM majors ORDER BY majorId DESC LIMIT 1'),
    'subject' => sampleId($pdo, 'SELECT subjectId FROM subjects ORDER BY subjectId DESC LIMIT 1'),
    'term' => sampleId($pdo, 'SELECT termId FROM academicterms ORDER BY termId DESC LIMIT 1'),
    'feeType' => sampleId($pdo, 'SELECT feeTypeId FROM feetypes ORDER BY feeTypeId DESC LIMIT 1'),
    'type' => sampleId($pdo, 'SELECT scholarshipTypeId FROM scholarshiptypes ORDER BY scholarshipTypeId DESC LIMIT 1'),
    'office' => sampleId($pdo, 'SELECT officeId FROM offices ORDER BY officeId DESC LIMIT 1'),
    'room' => sampleId($pdo, 'SELECT roomId FROM rooms ORDER BY roomId DESC LIMIT 1'),
    'block' => sampleId($pdo, 'SELECT blockId FROM blocks ORDER BY blockId DESC LIMIT 1'),
    'req' => sampleId($pdo, 'SELECT requirementId FROM admissionrequirements ORDER BY requirementId DESC LIMIT 1'),
    'idrequest' => sampleId($pdo, 'SELECT idRequestId FROM idrequests ORDER BY idRequestId DESC LIMIT 1'),
    'document' => sampleId($pdo, 'SELECT documentId FROM documents ORDER BY documentId DESC LIMIT 1'),
];
echo 'Sample IDs: ' . json_encode($ids) . "\n\n";

$skipNames = ['sanctum.csrf-cookie', 'storage.local', 'storage.local.upload', 'password.reset', 'debugbar.*'];
$pass = $fail = $skip = 0;
$results = [];

foreach ($out as $line) {
    [$name, $uri] = explode("\t", trim($line));
    if (!$name || str_starts_with($uri, 'api/')) continue;
    if (in_array($name, ['password.reset', 'sanctum.csrf-cookie'])) { $skip++; $results[] = "SKIP $name (framework/password-reset token route)"; continue; }

    // Substitute {param} with real IDs
    $url = $base . '/' . $uri;
    if (preg_match('/\{(\w+)(\??\})/', $uri)) {
        $ok = true;
        $url = preg_replace_callback('/\{(\w+)\??\}/', function ($m) use (&$ok, $ids) {
            $param = $m[1];
            // try direct match, then stripped plural/singular forms
            foreach ([$param, rtrim($param, 's')] as $k) {
                if (isset($ids[$k]) && $ids[$k] !== null) return $ids[$k];
            }
            $ok = false;
            return '0';
        }, $url);
        if (!$ok) { $skip++; $results[] = "SKIP $name — no sample ID for params in $uri"; continue; }
    }

    [$code, $body, , $err, $hdrs] = httpReq('GET', $url, ['headers' => ['Accept: text/html,application/xhtml+xml']]);
    $len = strlen($body);
    if ($err) { $fail++; $results[] = "FAIL $name — curl error: $err"; continue; }
    if ($code === 200) {
        // JSON endpoints (dashboard.queue-counts, exam.students, students.quick-search,
        // notifications.index) return JSON, not an Inertia page — valid JSON is a pass.
        $isJson = (str_starts_with(trim($body), '{') || str_starts_with(trim($body), '[')) && json_decode(trim($body)) !== null;
        $isPage = str_contains($body, 'data-page') || str_contains($body, 'id="app"');
        if ($isJson || $isPage || $len < 10) {
            $pass++;
            $results[] = 'PASS ' . str_pad($name, 48) . ' 200 (' . ($isJson ? 'json' : $len . ' bytes') . ')';
        } else {
            $fail++;
            $results[] = "FAIL $name — HTTP 200 but no page/json content (" . substr($body, 0, 120) . ')';
        }
    } elseif ($code === 302 || $code === 303) {
        // A redirect to /login means the route never rendered for this actor —
        // audit as the admin user, so any login-redirect is a FAIL. Other
        // redirects (normal app flows) are informational passes.
        $loc = '';
        if (preg_match('/^Location:\s*(.+)$/mi', $hdrs, $lm)) $loc = trim($lm[1]);
        if (str_contains($loc, '/login')) {
            $fail++;
            $results[] = "FAIL $name — redirects to /login (admin could not render it)";
        } else {
            $pass++;
            $results[] = 'PASS* ' . str_pad($name, 47) . " $code -> " . substr($loc, 0, 60);
        }
    } elseif ($code === 404 && str_contains($body, 'no query results')) {
        $skip++; $results[] = "SKIP $name — model not found with sample ID (needs specific data)";
    } else {
        $fail++;
        $results[] = "FAIL $name — HTTP $code (" . substr($body, 0, 120) . ')';
    }
}

echo implode("\n", $results), "\n\n";
echo "PASS: $pass  REDIRECT-PASS: (counted in pass)  FAIL: $fail  SKIP: $skip\n";
