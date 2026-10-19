<?php

// Adds the entity-escaping rule to AGENTS.md's docx-editing section. Lane 52 learned it the hard way: pass 153
// handed the engine already-escaped text and the engine escaped it again, so the document would have printed
// the literal string "Course &amp; Year". A patcher file, because the sentence itself carries ampersands.

$p = dirname(__DIR__, 3).'/AGENTS.md';
$s = (string) file_get_contents($p);

$old = "and a rewrite at the same byte corrupt the document); re-measure every `File.php:NN` citation an";

$new = "and a rewrite at the same byte corrupt the document); **a `text` edit through `docx-edit-engine.php` takes plain "
    ."characters, never entities** — the engine escapes `&` on the way in and refuses a replacement carrying a raw "
    ."`<` or `>`, so `&amp;` in a replacement becomes `&amp;amp;` and Word prints the literal `&amp;` (measured against "
    ."the pre-edit backup: pass 153 did exactly this to two runs and pass 154 undid it), and a code span containing an "
    ."arrow cannot travel through a text edit at all — write those with their own guards, as `refresh-docx-pass154.php` "
    ."does; read the result back through `html_entity_decode()`, not through the dump, which shows raw entities; "
    ."re-measure every `File.php:NN` citation an";

$n = substr_count($s, $old);

if ($n !== 1) {
    fwrite(STDERR, "REFUSE: the anchor occurs $n time(s) — nothing written\n");

    exit(1);
}

file_put_contents($p, str_replace($old, $new, $s));
echo "added the escaping rule to AGENTS.md\n";
