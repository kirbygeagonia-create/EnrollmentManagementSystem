<?php

namespace App\Support;

/**
 * A year level is spelled the way the school's own papers spell it: an ordinal, "2nd Year".
 *
 * This is the only place the spelling lives on the PHP side. The desk pages reach the same words through
 * formatYearLevel in resources/js/Components/ui/statusLabel.js, and YearLevelVocabularyTest pins the two
 * against each other — six pages once printed four vocabularies for one number, and a paper that disagrees
 * with the picker that recorded the level is the same defect wearing a different name.
 */
class YearLevel
{
    /** "2nd Year", or null when the record cannot answer — each paper states its own absence. */
    public static function format(?int $level): ?string
    {
        return $level === null || $level < 1 ? null : self::ordinal($level).' Year';
    }

    public static function ordinal(int $level): string
    {
        // 11th–13th are the English exceptions; nothing else in a school's range reaches them.
        if ($level % 100 >= 11 && $level % 100 <= 13) {
            return $level.'th';
        }

        return $level.(match ($level % 10) {
            1 => 'st',
            2 => 'nd',
            3 => 'rd',
            default => 'th',
        });
    }
}
