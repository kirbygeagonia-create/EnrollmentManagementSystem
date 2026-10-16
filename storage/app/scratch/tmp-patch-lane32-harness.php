<?php

// Patch the lane-32 proof harness: add the parenthetical-label cases and fix the stale verdict regex.

$dir = 'storage/app/scratch';
$f = $dir.'/mutate-lane32-sweep.php';
$c = file_get_contents($f);

$pairs = [
    // 1. two new mutants, inserted before the array's closing bracket
    "    'single-line-range-moved' => [",
    "    // The rule added after pass 120: a name may reach FORWARD through a parenthesis it opens —\n"
        ."    // `confirmClearance() records … ( :958-999 , permission … )`. Move that range and the sweep must\n"
        ."    // still see the drift, which it could not before the rule existed.\n"
        ."    'parenthetical-label-moved' => [\n"
        ."        'last clause ( :958-999',\n"
        ."        'last clause ( :30-40',\n"
        ."        ['confirmClearance', 'EvaluationController.php:30-40'],\n"
        ."    ],\n"
        ."    'single-line-range-moved' => [",
    ],
    // 2. the verdict line the tool prints changed shape in pass 118's lane
    "'(\\d+) citation range\\(s\\) examined, (\\d+) sentence-citation pairs flagged'",
    "'(\\d+) citation range\\(s\\) checked against a named construct, (\\d+) pair\\(s\\) flagged'",
    // 3. control must ALSO prove the untouched document does not flag confirmClearance
    "if (str_contains(\$l, 'stepsFor') && str_contains(implode(' ', array_slice(explode(\"\\n\", \$controlOut), \$i, 4)), 'WorkflowService.php:182-215')) {\n        \$stillClean = false;\n    }",
    "if (str_contains(\$l, 'stepsFor') && str_contains(implode(' ', array_slice(explode(\"\\n\", \$controlOut), \$i, 4)), 'WorkflowService.php:182-215')) {\n        \$stillClean = false;\n    }\n    if (str_contains(\$l, 'confirmClearance')) {\n        \$stillClean = false;\n        echo \"   (control flags confirmClearance — the parenthetical rule is not holding)\\n\";\n    }",
];

foreach ($pairs as [$from, $to]) {
    $n = substr_count($c, $from);
    if ($n !== 1) {
        fwrite(STDERR, "ANCHOR MISS (count {$n}): ".substr($from, 0, 60)."\n");
        exit(1);
    }
    $c = str_replace($from, $to, $c);
}

file_put_contents($f, $c);
echo "harness patched: 3 edits\n";
