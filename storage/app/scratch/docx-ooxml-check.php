<?php
/**
 * Does the edited docx still satisfy Word's markup-compatibility rules, not just XML's?
 *
 * An XML parser cannot see an OOXML violation: if a prefix listed in mc:Ignorable is no longer
 * declared on the root, the document parses and Word refuses it. So this checks the namespace
 * contract directly, compares the part list and the root start tag against a known-good copy,
 * and proves the checks are not vacuous by mutating a copy of the file.
 *
 * Usage: php docx-ooxml-check.php <file.docx> [reference.docx]
 */

$target = $argv[1] ?? null;
$reference = $argv[2] ?? null;
if ($target === null) {
    fwrite(STDERR, "usage: php docx-ooxml-check.php <file.docx> [reference.docx]\n");
    exit(2);
}

function parts(string $file): array
{
    $z = new ZipArchive();
    if ($z->open($file) !== true) {
        fwrite(STDERR, "cannot open zip: {$file}\n");
        exit(1);
    }
    $out = [];
    for ($i = 0; $i < $z->numFiles; $i++) {
        $name = $z->getNameIndex($i);
        $out[$name] = $z->getFromName($name);
    }
    $z->close();

    return $out;
}

/** Every prefix a document actually uses, and every prefix its root declares. */
function namespaceContract(string $xml): array
{
    preg_match('/<w:document\b[^>]*>/s', $xml, $root);
    $declared = [];
    if (! empty($root[0])) {
        preg_match_all('/xmlns:([a-zA-Z0-9]+)="[^"]*"/', $root[0], $dm, PREG_SET_ORDER);
        foreach ($dm as $d) {
            $declared[$d[1]] = true;
        }
    }
    $ignorable = [];
    if (preg_match('/mc:Ignorable="([^"]*)"/', $xml, $im)) {
        foreach (preg_split('/\s+/', trim($im[1])) as $p) {
            if ($p !== '') {
                $ignorable[$p] = true;
            }
        }
    }
    // Prefixes appearing on elements or attributes anywhere in the body. `xml` and `xmlns` are
    // reserved by the XML specification and always in scope, so they are not this document's
    // declarations to make.
    $reserved = ['xml' => true, 'xmlns' => true];
    $used = [];
    preg_match_all('/<(\/?)([a-zA-Z][\w]*(?::[\w]+))\b/', $xml, $em);
    foreach ($em[2] as $name) {
        if (str_contains($name, ':')) {
            $used[strstr($name, ':', true)] = true;
        }
    }
    preg_match_all('/\s([a-zA-Z][\w]*(?::[\w]+)=)/', $xml, $am);
    foreach ($am[1] as $name) {
        if (str_contains($name, ':')) {
            $used[strstr($name, ':', true)] = true;
        }
    }
    $used = array_diff_key($used, $reserved);

    return [
        'declared' => array_keys($declared),
        'used' => array_keys($used),
        'ignorable' => array_keys($ignorable),
        'root' => $root[0] ?? '',
        'serializerPrefixes' => array_values(array_filter(array_keys($used), fn ($p) => preg_match('/^ns\d+$/', $p))),
    ];
}

$fails = 0;
$check = function (string $label, bool $ok, string $detail = '') use (&$fails): void {
    printf("%s  %s%s\n", $ok ? 'ok   ' : 'FAIL ', $label, $detail === '' ? '' : " — {$detail}");
    if (! $ok) {
        $fails++;
    }
};

$parts = parts($target);
$doc = $parts['word/document.xml'] ?? null;
$check('word/document.xml exists in the zip', $doc !== null);
if ($doc === null) {
    exit(1);
}

$c = namespaceContract($doc);

// 1. the markup-compatibility contract
$missingIgnorable = array_values(array_diff($c['ignorable'], $c['declared']));
$check('every mc:Ignorable prefix is declared on the root',
    $missingIgnorable === [],
    'missing: '.implode(', ', $missingIgnorable));

$undeclaredUsed = array_values(array_diff($c['used'], $c['declared']));
$check('every prefix used by an element or attribute is declared',
    $undeclaredUsed === [],
    'undeclared: '.implode(', ', $undeclaredUsed));

$check('no serializer-hoisted nsN: prefixes appeared',
    $c['serializerPrefixes'] === [],
    'found: '.implode(', ', $c['serializerPrefixes']));

// 2. the parts Word needs must still be there
foreach (['[Content_Types].xml', '_rels/.rels', 'word/_rels/document.xml.rels', 'word/styles.xml'] as $needed) {
    $check("part present: {$needed}", isset($parts[$needed]));
}

// 3. every relationship target that should resolve does resolve
preg_match_all('/<w:hyperlink r:link="([^"]+)"/', $doc, $links);
preg_match_all('/Id="([^"]+)"/', $parts['word/_rels/document.xml.rels'] ?? '', $ids);
$dangling = array_values(array_diff($links[1], $ids[1]));
$check('every r:link in the body has a relationship entry', $dangling === [], 'dangling: '.implode(', ', $dangling));

// 4. against a known-good copy: same parts, same root tag, same declaration set
if ($reference !== null) {
    $refParts = parts($reference);
    $missing = array_values(array_diff(array_keys($refParts), array_keys($parts)));
    $added = array_values(array_diff(array_keys($parts), array_keys($refParts)));
    $check('no part disappeared since the reference copy', $missing === [], 'gone: '.implode(', ', $missing));
    $check('no unexpected part was added', $added === [], 'added: '.implode(', ', $added));

    $rc = namespaceContract($refParts['word/document.xml']);
    $mine = $c['declared'];
    $theirs = $rc['declared'];
    sort($mine);
    sort($theirs);
    $check('the root declares exactly the same prefixes as the reference',
        $mine === $theirs,
        'diff: '.implode(', ', array_merge(array_diff($mine, $theirs), array_diff($theirs, $mine))));
    $check('the root start tag is byte-identical to the reference', $c['root'] === $rc['root']);

    foreach (['word/styles.xml', 'word/settings.xml', '[Content_Types].xml'] as $p) {
        if (isset($refParts[$p], $parts[$p])) {
            $check("{$p} unchanged", $refParts[$p] === $parts[$p], strlen($refParts[$p]).' vs '.strlen($parts[$p]).' bytes');
        }
    }
}

printf("\n%s\n", $fails === 0 ? 'CLEAN — the OOXML contract holds, not merely the XML one' : "{$fails} problem(s) found");

exit($fails === 0 ? 0 : 1);
