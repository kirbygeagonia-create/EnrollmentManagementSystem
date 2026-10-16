<?php
// One-off: point the three §29.2 cell edits at an anchor that is contiguous in the raw XML.
$f = 'storage/app/scratch/refresh-docx-pass54.php';
$s = file_get_contents($f);
$old = "['the only Enrollments::create in app/',";
$new = "['An enrollment row comes into existence only when admission approves',";
$n = substr_count($s, $old);
$s = str_replace($old, $new, $s);
file_put_contents($f, $s);
echo "swapped $n anchor line(s)\n";
