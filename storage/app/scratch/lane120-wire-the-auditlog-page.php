<?php

/**
 * Wire the audit-log screen's own half of sorting (§28.3 G-40, the thirteenth table).
 *
 * The controller already applies and echoes the order; a page that ignores it would show a list the handler
 * narrowed while its headers offer nothing and its arrows sit at rest. Five changes, each asserted to match
 * EXACTLY once — a needle that matches twice plants in whichever comes first and the screen then reads correct
 * while being wrong, which is the defect this file's own notes call the needle-list problem.
 *
 * Nothing here is a guess about line numbers: every needle is literal source text read from the file first, and
 * the script refuses without writing when any count is not 1.
 *
 * Usage: php storage/app/scratch/lane120-wire-the-auditlog-page.php [--apply]
 */
$root = dirname(__DIR__, 3);
$page = $root.'/resources/js/Pages/Admin/UserManagement/AuditLogs.jsx';
$apply = in_array('--apply', $argv, true);

if (! is_file($page)) {
    fwrite(STDERR, "REFUSED: {$page} is not there.\n");

    exit(1);
}

$src = (string) file_get_contents($page);
$edits = [
    [
        'name' => 'barrel import gains the two shared sort helpers',
        'from' => "Modal, StatCard } from '@/Components/ui';",
        'to' => "Modal, StatCard, nextSortDirection, sortParams } from '@/Components/ui';",
    ],
    [
        'name' => 'props gain the sort state the handler echoes',
        'from' => "export default function AuditLogs({ logs, stats = {}, entityTables = [], filters = {} }) {",
        'to' => "export default function AuditLogs({ logs, stats = {}, entityTables = [], filters = {}, sort = null }) {",
    ],
    [
        'name' => 'sort state seeded from the echo, not from a local guess',
        'from' => "    const [viewingLog, setViewingLog] = useState(null);",
        'to' => "    const [viewingLog, setViewingLog] = useState(null);\n    // Seeded from what the server reported, so a filter submit keeps the order the desk is looking at\n    // instead of silently reverting to newest-first while the list stays narrowed.\n    const [sortBy, setSortBy] = useState(filters.sort || '');\n    const [sortDir, setSortDir] = useState(filters.direction || '');",
    ],
    [
        'name' => 'Timestamp header offered',
        'from' => "        { key: 'timestamp', label: 'Timestamp', render: (row) => row.createdAt ? (",
        'to' => "        { key: 'timestamp', label: 'Timestamp', sortKey: 'createdAt', render: (row) => row.createdAt ? (",
    ],
    [
        'name' => 'User header offered through the left hop',
        'from' => "        { key: 'user', label: 'User', render: (row) => row.user ? (",
        'to' => "        { key: 'user', label: 'User', sortKey: 'user', render: (row) => row.user ? (",
    ],
    [
        'name' => 'Action header offered',
        'from' => "        { key: 'action', label: 'Action', render: (row) => (",
        'to' => "        { key: 'action', label: 'Action', sortKey: 'action', render: (row) => (",
    ],
    [
        'name' => 'Entity header offered',
        'from' => "        { key: 'entityTable', label: 'Entity', render: (row) => (",
        'to' => "        { key: 'entityTable', label: 'Entity', sortKey: 'entityTable', render: (row) => (",
    ],
    [
        'name' => 'Entity ID header offered',
        'from' => "        { key: 'entityId', label: 'Entity ID', className: 'font-mono text-xs text-brand-500' },",
        'to' => "        { key: 'entityId', label: 'Entity ID', sortKey: 'entityId', className: 'font-mono text-xs text-brand-500' },",
    ],
    [
        'name' => 'filter submit carries the order it found',
        'from' => "            adminOverride: adminOverride || undefined,\n        }, {",
        'to' => "            adminOverride: adminOverride || undefined,\n            // The order travels with the filters, or a submit that reads as narrowing the list\n            // quietly re-sorts it back to the default underneath the desk.\n            ...sortParams('createdAt', sortBy, sortDir),\n        }, {",
    ],
    [
        'name' => 'handleSort added beside handleFilter',
        'from' => "    // The Entity pick-list used to be assembled from `logs.data`,",
        'to' => "    const handleSort = (key) => {\n        const direction = nextSortDirection(key === sortBy, sortDir);\n        setSortBy(key);\n        setSortDir(direction);\n        router.get(route('admin.users.audit-logs'), {\n            action: action || undefined,\n            entityTable: entityTable || undefined,\n            dateFrom: dateFrom || undefined,\n            dateTo: dateTo || undefined,\n            adminOverride: adminOverride || undefined,\n            ...sortParams('createdAt', key, direction),\n        }, { preserveState: true, preserveScroll: true });\n    };\n\n    // The Entity pick-list used to be assembled from `logs.data`,",
    ],
];

$bad = 0;
foreach ($edits as $e) {
    $n = substr_count($src, $e['from']);
    if ($n !== 1) {
        printf("  BAD  %-46s needle occurs %d time(s), wanted 1 — nothing written\n", $e['name'], $n);
        $bad++;
    }
}
if ($bad > 0) {
    fwrite(STDERR, "REFUSED: {$bad} needle(s) not unique. The page has moved; re-read it before patching.\n");

    exit(1);
}

foreach ($edits as $e) {
    $src = str_replace($e['from'], $e['to'], $src);
    printf("  %-8s %s\n", $apply ? 'wrote' : 'would write', $e['name']);
}

// Description deliberately gets no sortKey: it is composed from the JSON at read time, and a header that offers
// an order the handler cannot answer in SQL is the defect lane 46 owns. Asserted here so the omission is a
// decision on the record rather than something a later reader "forgot".
if (! preg_match("/\{ key: 'description', label: 'Description', render:/", $src)) {
    fwrite(STDERR, "REFUSED: the description column no longer reads the shape this patch expects — re-read the page.\n");

    exit(1);
}

$DataTable = substr_count($src, '<DataTable');
if ($DataTable !== 1) {
    fwrite(STDERR, "REFUSED: found {$DataTable} <DataTable occurrences, wanted exactly 1 to attach the sort props to.\n");

    exit(1);
}
$src = str_replace(
    "                        <DataTable",
    "                        <DataTable\n                            sort={sort}\n                            onSort={handleSort}",
    $src
);
printf("  %-8s DataTable receives sort and onSort\n", $apply ? 'wrote' : 'would write');

if (! $apply) {
    echo "\nDRY RUN — nothing written. Re-run with --apply.\n";

    exit(0);
}

$written = @file_put_contents($page, $src);
if ($written === false) {
    fwrite(STDERR, "REFUSED: the write failed (a lock, most likely). Nothing was changed.\n");

    exit(1);
}
echo 'applied to '.$page."\n";
