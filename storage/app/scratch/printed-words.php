<?php

/**
 * One definition of "how many words does this printed answer carry".
 *
 * The writer that breaks a long answer into paragraphs and the reader that judges the paper's longest answer were
 * counting different things: the writer used `str_word_count`, which splits an identifier or a path into several
 * (`lane154-probe-duplicate-keys.php` is five words to it and one token to a reader), so an answer made of
 * identifiers and file names read as SHORT to whoever enforced the cap and LONG to whoever measured it. The cap was
 * therefore unenforceable on precisely the enumerated answers that prompted it.
 *
 * The unit is now what a person's eye counts: whitespace-separated tokens. Both `lane203-fill-guide-form.php` and
 * `lane228-read-analysis-boxes.php` require this file rather than carrying their own rule, because two answers to
 * "how long is this answer" is the same defect this repository has refused for year levels, sort keys and
 * "is this narrowed".
 */
if (! function_exists('printed_words')) {
    function printed_words(string $text): int
    {
        $parts = preg_split('/\s+/u', trim($text), -1, PREG_SPLIT_NO_EMPTY);

        return is_array($parts) ? count($parts) : 0;
    }
}
