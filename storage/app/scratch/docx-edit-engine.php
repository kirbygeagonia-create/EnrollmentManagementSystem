<?php

/**
 * The docx edit engine — one implementation of "resolve every target, then write".
 *
 * Two edit kinds:
 *   text — a needle living inside one <w:t> run, replaced in place (keeps inline styling).
 *          Optionally a third element: an anchor that must occur exactly once, in which case
 *          the needle is resolved as its FIRST occurrence after that anchor — which is how a
 *          figure that repeats verbatim across sixty dictionary entries is still addressed
 *          uniquely, by position rather than by a longer quote.
 *   cell — a whole table cell, located by (a row anchor, a prefix unique among that row's
 *          cells). The anchor must occur exactly once unless the edit states how many rows
 *          carry it, in which case every one of them is rewritten.
 *
 * Every offset handed to the writer is ABSOLUTE in the document. The bug this file exists
 * to prevent: preg_match_all() over a row substring yields row-relative offsets, and
 * substr_replace() with a negative number counts from the END of the string — which wrote
 * fifteen register cells into the wrong places in a document that still parsed and still
 * looked balanced. So the guard below insists on three things before writing: the
 * containers do not move, the document parses, and every stale needle is GONE while every
 * replacement is present.
 */
function docxApply(string $src, string $tmpBackup, array $textEdits, array $cellEdits, bool $apply, array $paraEdits = [], array $inserts = []): void
{
    $zip = new ZipArchive;
    if ($zip->open($src) !== true) {
        fwrite(STDERR, "cannot open docx\n");
        exit(1);
    }
    $xml = $zip->getFromName('word/document.xml');
    $zip->close();

    $original = $xml;
    $replacements = [];
    $failures = 0;

    foreach ($textEdits as $i => $edit) {
        [$from, $to] = $edit;
        // A text edit lands INSIDE a <w:t> element with no escaping, which is how a needle that already
        // carries `&amp;` or `&gt;` can be addressed — and it is also how a caller can smuggle markup into
        // prose. Proven necessary: a mutant that appended `</w:p></w:body><w:p>` to a replacement passed the
        // container and parse guards, because closing and reopening the same elements leaves both counts
        // unchanged and the result still well-formed. So markup in a replacement is refused outright; a pass
        // that genuinely means to write elements uses the para/cell/insert kinds, which build them itself.
        if (str_contains($to, '<') || str_contains($to, '>')) {
            echo sprintf("FAIL text %d: replacement carries markup [%s]\n", $i, substr($to, 0, 60));
            $failures++;

            continue;
        }
        // A replacement identical to its needle is not a correction, it is a pass that reports eight targets
        // while changing nothing. Found by mutation, not by reading: the stale-needle check below deliberately
        // stands down when the replacement keeps the sentence it is correcting, which is exactly the case a
        // no-op hides in.
        if ($from === $to) {
            echo sprintf("FAIL text %d: replacement is identical to the needle — nothing would change [%s]\n", $i, substr($from, 0, 60));
            $failures++;

            continue;
        }
        $after = $edit[2] ?? null;
        // Slot 3 is the ANCHOR and slot 4 is the expected count. A caller who writes `[from, to, 2]` meaning "there
        // are two copies" instead hands the positional resolver the string "2", which it then searches for literally —
        // and because document.xml is full of the character 2, it lands on one occurrence and reports
        // "1 target resolved uniquely" as if that were the whole job. Measured 2026-10-26 against a sentence the
        // document holds twice: slot 3 = 2 resolved one target, slot 4 = 2 resolved both. So refuse the shape loudly
        // rather than half-applying a pass in silence.
        if ($after !== null && ! is_string($after)) {
            echo sprintf('FAIL text %d: slot 3 is the anchor and must be a string or null; got %s. '
                ."To correct N copies pass [from, to, null, %d] — slot 4 is the count.\n", $i,
                get_debug_type($after), (int) $after);
            $failures++;

            continue;
        }
        // An identical sentence that appears in two different tables (§26 and §28.5 ask the
        // same institutional question) can be corrected in both at once — but only when the
        // caller states how many copies it expects to find.
        $expect = $edit[3] ?? 1;
        if ($after === null && $expect > 1) {
            $n = substr_count($xml, $from);
            if ($n !== $expect) {
                echo sprintf("FAIL text %d: %d occurrences, expected %d [%s]\n", $i, $n, $expect, substr($from, 0, 50));
                $failures++;

                continue;
            }
            $scan = 0;
            while (($at2 = strpos($xml, $from, $scan)) !== false) {
                printf("ok   text %3d  @%d  %s → %s\n", $i, $at2, substr($from, 0, 44), substr($to, 0, 44));
                $replacements[] = ['at' => $at2, 'len' => strlen($from), 'to' => $to, 'old' => str_contains($to, $from) ? '' : $from];
                $scan = $at2 + 1;
            }

            continue;
        }
        if ($after !== null) {
            // Positional. A figure like "Rows in the demo database: 16." repeats verbatim
            // across the dictionary, so it is addressed by the paragraph that owns it. The
            // owning anchor is the LAST anchor occurrence with no further anchor before the
            // needle — a cited path (app/Models/Enrollments.php) can appear twice in the
            // document, and "first anchor, then first needle" would land in the wrong entry.
            $positions = [];
            $scan = 0;
            while (($a = strpos($xml, $after, $scan)) !== false) {
                $positions[] = $a;
                $scan = $a + 1;
            }
            $at = false;
            foreach (array_reverse($positions) as $p) {
                $candidate = strpos($xml, $from, $p + strlen($after));
                $next = null;
                foreach ($positions as $q) {
                    if ($q > $p && ($next === null || $q < $next)) {
                        $next = $q;
                    }
                }
                if ($candidate !== false && ($next === null || $candidate < $next)) {
                    $at = $candidate;

                    break;
                }
            }
            if ($at === false) {
                echo sprintf("FAIL text %d: no needle owned by the anchor [%s] / [%s]\n", $i, substr($after, 0, 40), substr($from, 0, 30));
                $failures++;

                continue;
            }
        } else {
            $n = substr_count($xml, $from);
            if ($n !== 1) {
                echo sprintf("FAIL text %d: %d occurrences [%s]\n", $i, $n, substr($from, 0, 60));
                $failures++;

                continue;
            }
            $at = strpos($xml, $from);
        }

        // A needle that is deliberately part of its own replacement (the enum cells grow a
        // value rather than lose one) has nothing to verify afterwards.
        printf("ok   text %3d  @%d  %s → %s\n", $i, $at, substr($from, 0, 44), substr($to, 0, 44));
        $replacements[] = ['at' => $at, 'len' => strlen($from), 'to' => $to, 'old' => str_contains($to, $from) ? '' : $from];
    }

    // A cell edit names a row by an anchor and a cell by a prefix of its flattened text.
    // By default that anchor must be unique; when the caller states how many rows carry it
    // (§28.5 asks a question that §25.11 or §26.7 repeats verbatim), EVERY row holding the
    // anchor is rewritten — which is the only correct reading, because correcting one copy
    // would leave the other asserting the superseded state.
    foreach ($cellEdits as $i => $edit) {
        [$rowNeedle, $cellPrefix, $to] = $edit;
        $expect = $edit[3] ?? 1;

        $anchors = [];
        $scan = 0;
        while (($a = strpos($xml, $rowNeedle, $scan)) !== false) {
            $anchors[] = $a;
            $scan = $a + 1;
        }
        if (count($anchors) !== $expect) {
            echo sprintf("FAIL cell %d: row anchor seen %d time(s), expected %d [%s]\n", $i, count($anchors), $expect, substr($rowNeedle, 0, 50));
            $failures++;

            continue;
        }

        foreach ($anchors as $rowPos) {
            $start = strrpos(substr($xml, 0, $rowPos), '<w:tr');
            $end = strpos($xml, '</w:tr>', $rowPos) + 7;
            if ($start === false || strpos(substr($xml, $start, $rowPos - $start), '</w:tr>') !== false) {
                echo sprintf("FAIL cell %d: row anchor is not inside one row\n", $i);
                $failures++;

                continue;
            }

            $row = substr($xml, $start, $end - $start);
            preg_match_all('/<w:tc>.*?<\/w:tc>/s', $row, $cm, PREG_OFFSET_CAPTURE);

            $hits = [];
            foreach ($cm[0] as $j => $cell) {
                preg_match_all('/<w:t(?: [^>]*)?>(.*?)<\/w:t>/s', $cell[0], $tm);
                $flat = html_entity_decode(implode('', $tm[1]), ENT_QUOTES | ENT_XML1);
                if (strncmp(ltrim($flat), $cellPrefix, min(strlen($cellPrefix), 60)) === 0) {
                    $hits[] = $j;
                }
            }

            if (count($hits) !== 1) {
                echo sprintf("FAIL cell %d: prefix matched %d cells [%s]\n", $i, count($hits), substr($cellPrefix, 0, 45));
                $failures++;

                continue;
            }

            $cell = $cm[0][$hits[0]][0];
            if (substr_count($cell, '<w:p>') !== 1) {
                echo sprintf("FAIL cell %d: target cell holds %d paragraphs\n", $i, substr_count($cell, '<w:p>'));
                $failures++;

                continue;
            }

            preg_match_all('/<w:t(?: [^>]*)?>(.*?)<\/w:t>/s', $cell, $tm);
            $current = html_entity_decode(implode('', $tm[1]), ENT_QUOTES | ENT_XML1);
            $probe = mb_substr(preg_replace('/\s+/', ' ', $current), 0, 60);

            printf(
                "ok   cell %3d  @%-8d cell %d  was: %s\n                             now: %s\n",
                $i,
                $cm[0][$hits[0]][1] + $start,
                $hits[0],
                preg_replace('/\s+/', ' ', substr($current, 0, 60)),
                substr($to, 0, 60)
            );

            // Absolute, on purpose. $cm offsets are relative to $row.
            $replacements[] = [
                'at' => $cm[0][$hits[0]][1] + $start,
                'len' => strlen($cell),
                'to' => $to,
                'cell' => true,
                'old' => str_contains($to, $probe) ? '' : $probe,
            ];
        }
    }

    // A paragraph edit rewrites the whole <w:p> that owns the anchor. This is what a table
    // cell needs when the clause to correct sits in the MIDDLE of the cell (so no prefix
    // matches it) but the anchor for it is unique in the document.
    foreach ($paraEdits as $i => [$anchor, $to]) {
        $n = substr_count($xml, $anchor);
        if ($n !== 1) {
            echo sprintf("FAIL para %d: anchor seen %d times [%s]\n", $i, $n, substr($anchor, 0, 50));
            $failures++;

            continue;
        }
        $p = strpos($xml, $anchor);
        $start = strrpos(substr($xml, 0, $p), '<w:p>');
        $startAttr = strrpos(substr($xml, 0, $p), '<w:p ');
        if ($startAttr !== false && $startAttr > $start) {
            $start = $startAttr;
        }
        $end = strpos($xml, '</w:p>', $p) + 6;
        if ($start === false || strpos(substr($xml, $start, $p - $start), '</w:p>') !== false) {
            echo sprintf("FAIL para %d: anchor is not inside one paragraph\n", $i);
            $failures++;

            continue;
        }

        $body = substr($xml, $start, $end - $start);
        preg_match('/<w:r\b.*?<\/w:r>/s', $body, $rm);
        preg_match('/<w:rPr>.*?<\/w:rPr>/s', $rm[0] ?? '', $pr);
        $new = preg_replace('/<w:r\b.*?<\/w:r>/s', '', $body);
        $new = str_replace('</w:p>', '<w:r>'.($pr[0] ?? '').'<w:t xml:space="preserve">'
            .str_replace(['&', '<', '>'], ['&amp;', '&lt;', '&gt;'], $to).'</w:t></w:r></w:p>', $new);

        printf("ok   para %3d  @%d  was: %s\n                        now: %s\n", $i, $start, substr(preg_replace('/\s+/', ' ', strip_tags($body)), 0, 56), substr($to, 0, 56));
        $probe = mb_substr(preg_replace('/\s+/', ' ', strip_tags($body)), 0, 60);
        // Same rule the cell branch uses: when the replacement keeps the sentence it is
        // correcting as its own opening — "Requirements block. One row per ..." rewritten from
        // its middle — the old-text probe could only ever find the new text, so the edit is
        // verified by the new-text check at its offset instead.
        $replacements[] = ['at' => $start, 'len' => strlen($body), 'to' => $new, 'raw' => true, 'old' => str_contains($to, $probe) ? '' : $probe];
    }

    // An insert puts brand-new elements next to the paragraph that names the place. The
    // container guard below lets <w:p> move by exactly what the inserted XML contributes and
    // nothing else, so a stray or dropped element still refuses the write.
    $expectedParaDelta = 0;
    foreach ($inserts as $i => $ins) {
        [$anchor, $block, $where] = [$ins['anchor'], $ins['xml'], $ins['pos'] ?? 'after'];
        if (substr_count($xml, $anchor) !== 1) {
            echo sprintf("FAIL insert %d: anchor seen %d times [%s]\n", $i, substr_count($xml, $anchor), substr($anchor, 0, 50));
            $failures++;

            continue;
        }
        $p = strpos($xml, $anchor);
        $start = max(strrpos(substr($xml, 0, $p), '<w:p>'), strrpos(substr($xml, 0, $p), '<w:p '));
        $end = strpos($xml, '</w:p>', $p) + 6;
        $at = $where === 'before' ? $start : $end;
        $opens = substr_count($block, '<w:p>');
        $closes = substr_count($block, '</w:p>');
        if ($opens !== $closes) {
            echo sprintf("FAIL insert %d: the block itself is unbalanced (%d open, %d close)\n", $i, $opens, $closes);
            $failures++;

            continue;
        }
        $expectedParaDelta += $opens;
        printf("ok   insert %3d  @%d  %s  adding %d paragraph(s)\n", $i, $at, $where, $opens);
        $replacements[] = ['at' => $at, 'len' => 0, 'to' => $block, 'raw' => true, 'old' => ''];
    }

    if ($failures > 0) {
        echo "\n{$failures} target(s) unresolved — nothing written\n";
        exit(1);
    }

    echo "\n".count($replacements)." targets resolved uniquely\n";

    if (! $apply) {
        echo "DRY RUN — pass --apply to write\n";

        return;
    }

    copy($src, $tmpBackup);

    usort($replacements, fn ($a, $b) => $b['at'] <=> $a['at']);

    $escaped = fn (string $s) => str_replace(['&', '<', '>'], ['&amp;', '&lt;', '&gt;'], $s);

    foreach ($replacements as $k => $r) {
        if (! empty($r['raw'])) {
            // Already-built XML (a paragraph rewrite assembled its own run); escaping it here
            // is what turned real tags into &lt;w:p&gt; and dropped the paragraph count.
            continue;
        }
        if (! empty($r['cell'])) {
            $cell = substr($xml, $r['at'], $r['len']);
            preg_match('/<w:r\b.*?<\/w:r>/s', $cell, $rm);
            preg_match('/<w:rPr>.*?<\/w:rPr>/s', $rm[0] ?? '', $pr);
            $body = preg_replace('/<w:r\b.*?<\/w:r>/s', '', $cell);
            $body = str_replace('</w:p>', '<w:r>'.($pr[0] ?? '').'<w:t xml:space="preserve">'
                .$escaped($r['to']).'</w:t></w:r></w:p>', $body);
            $replacements[$k]['to'] = $body;
        } else {
            $replacements[$k]['to'] = $escaped($r['to']);
        }
    }

    $trace = [];
    foreach ($replacements as $k => $r) {
        $before = substr_count($xml, '<w:p>');

        // Verified at this moment on purpose: writes run from the end backwards, so this
        // offset is still exact now, and a global search afterwards would say nothing about
        // this cell (a short old value like "Developer" survives somewhere in 5.6M chars).
        // The window must cover the whole new cell — tcPr carries 300-800 bytes of markup
        // before the first character of text.
        $xml = substr_replace($xml, $r['to'], $r['at'], $r['len']);
        $window = substr($xml, $r['at'], strlen($r['to']) + $r['len'] + 600);
        // A one-character old value ("5" in a count cell) proves nothing checked against a
        // window: the neighbouring cells hold digits too. Short values are verified by the
        // new-text probe below instead.
        if (mb_strlen($r['old']) >= 12 && $r['old'] !== '' && str_contains($window, $r['old'])) {
            fwrite(STDERR, "REFUSED WRITE: the old text is still in place at offset {$r['at']} [".mb_substr($r['old'], 0, 60)."]\n");
            exit(1);
        }
        $probe = mb_substr(strip_tags($r['to']), 0, 40);
        if ($r['len'] === 0) {
            // An insert is verified byte-for-byte at its offset — strip_tags would join the
            // paragraphs it adds and no such string exists in XML.
            if (substr($xml, $r['at'], strlen($r['to'])) !== $r['to']) {
                fwrite(STDERR, "REFUSED WRITE: the inserted block is not byte-identical at offset {$r['at']}\n");
                exit(1);
            }

            continue;
        }
        if ($probe !== '' && ! str_contains($window, $probe)) {
            fwrite(STDERR, "REFUSED WRITE: the new text is not at offset {$r['at']} [{$probe}]\n");
            exit(1);
        }

        $after = substr_count($xml, '<w:p>');
        // An insert is expected to add paragraphs; an edit that replaces a span is not.
        if ($r['len'] > 0 && $before !== $after) {
            $trace[] = sprintf('  edit %d at %d moved <w:p> by %d: %s', $k, $r['at'], $after - $before, mb_substr(strip_tags($r['to']), 0, 60));
        }
    }
    if ($trace !== []) {
        fwrite(STDERR, "REFUSED WRITE: structure moved inside a single edit\n".implode("\n", $trace)."\n");
        exit(1);
    }
    echo "guard  every replacement sits where its target was, structure unchanged\n";
    echo "guard  every replacement sits where its target was\n";

    // ---- guard: containers unmoved, tags balanced, XML parses, stale gone, new present
    $containers = ['<w:body>' => 0, '</w:body>' => 0, '<w:tbl>' => 0, '</w:tbl>' => 0, '<w:tr>' => 0, '</w:tr>' => 0, '<w:tc>' => 0, '</w:tc>' => 0, '<w:p>' => $expectedParaDelta, '</w:p>' => $expectedParaDelta];
    foreach ($containers as $tag => $delta) {
        $b = preg_match_all('/'.preg_quote($tag, '/').'/', $original);
        $a = preg_match_all('/'.preg_quote($tag, '/').'/', $xml);
        if ($a - $b !== $delta) {
            fwrite(STDERR, "REFUSED WRITE: {$tag} moved by ".($a - $b).', expected '.$delta."\n");
            exit(1);
        }
    }
    echo 'guard  containers unmoved except the '.($expectedParaDelta * 2)." inserted paragraph tags\n";

    foreach ([['<w:r ', '<w:r>', '</w:r>'], ['<w:t ', '<w:t>', '</w:t>'], ['<w:p ', '<w:p>', '</w:p>']] as [$o1, $o2, $c]) {
        $open = preg_match_all('/'.preg_quote($o1, '/').'/', $xml) + preg_match_all('/'.preg_quote($o2, '/').'/', $xml);
        $close = preg_match_all('/'.preg_quote($c, '/').'/', $xml);
        if ($open !== $close) {
            fwrite(STDERR, "REFUSED WRITE: {$c} unbalanced ({$open} open vs {$close} close)\n");
            exit(1);
        }
    }
    echo "guard  run, text and paragraph tags balance\n";

    if (simplexml_load_string($xml) === false) {
        fwrite(STDERR, "REFUSED WRITE: document.xml no longer parses\n");
        exit(1);
    }
    echo "guard  document.xml parses\n";

    $tmp = sys_get_temp_dir().'/ems-docx-'.uniqid().'.docx';
    copy($src, $tmp);
    $z2 = new ZipArchive;
    $z2->open($tmp);
    $z2->addFromString('word/document.xml', $xml);
    $z2->close();
    if (! @rename($tmp, $src)) {
        // Word holding the file open, or OneDrive syncing it, makes this fail — and a script
        // that printed WROTE anyway once reported a pass on a document it had not touched.
        fwrite(STDERR, "\nCOULD NOT REPLACE THE FILE — the edited copy is at {$tmp}\n");
        fwrite(STDERR, "Close whatever has the docx open (Word, a sync client) and re-run with --apply.\n");
        exit(1);
    }

    // Verify after the write: read the part back from disk, not from memory.
    $z3 = new ZipArchive;
    if ($z3->open($src) !== true) {
        fwrite(STDERR, "REFUSE: the file cannot be re-opened after the write\n");
        exit(1);
    }
    $back = $z3->getFromName('word/document.xml');
    $z3->close();
    foreach ($replacements as $r) {
        if ($r['len'] > 0 && ! str_contains($back, $r['to'])) {
            fwrite(STDERR, "REFUSE: a replacement is absent from the file on disk — the write did not land\n");
            exit(1);
        }
    }
    if ($back !== $xml) {
        fwrite(STDERR, "REFUSE: document.xml on disk differs from what was written\n");
        exit(1);
    }
    echo "guard  the file on disk holds the edited document.xml byte for byte\n";

    echo "WROTE {$src}\n";
}
