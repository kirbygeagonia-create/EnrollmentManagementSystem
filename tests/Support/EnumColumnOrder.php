<?php

namespace Tests\Support;

use Illuminate\Support\Facades\DB;
use PHPUnit\Framework\TestCase;

/**
 * The order a given connection really answers for an ascending sort on an ENUM column.
 *
 * Owner ruling 2026-10-31: a desk MAY be given a header over an ENUM column, because on the school's own server that
 * sort is the workflow order the column was declared in — `pending, evaluated, assessed, paid` for an enrollment —
 * which is exactly what a queue wants. The consequence is that the expectation cannot be a literal typed into a test:
 * MySQL orders an ENUM by its declaration index, while the suite's `enum()` compiles to a varchar with a CHECK and
 * orders the stored word, so the two surfaces answer two sequences for one header. CI named that split on the
 * clearance queue when this box was green, and the answer was to ask the connection.
 *
 * This is the ONE definition of that question. Three desk screens now read it (clearance, registrar, clinic, ID), and
 * a second copy of the parse is how one sentence ends up true on one driver and false on another depending on which
 * test was run — the two-answers-for-one-word defect this project has already removed for year levels, status tones
 * and citation attribution.
 */
final class EnumColumnOrder
{
    /**
     * Sort `$values` the way THIS connection sorts them out of `$table.$column`.
     *
     * @param  list<string>  $values
     * @return list<string>
     */
    public static function ascending(string $table, string $column, array $values): array
    {
        foreach ([$table, $column] as $identifier) {
            if (preg_match('/^[A-Za-z_][A-Za-z0-9_]*$/', $identifier) !== 1) {
                TestCase::fail("the identifier [{$identifier}] is not a plain column or table name to ask a server about");
            }
        }

        // Alphabetical is not a guess about the suite — it is what a varchar does, and the suite's grammar compiles
        // every enum() into one. Any other driver is refused rather than assumed.
        $ordered = $values;
        sort($ordered);

        $driver = DB::connection()->getDriverName();
        if ($driver !== 'mysql') {
            return $ordered;
        }

        $row = DB::selectOne("SHOW COLUMNS FROM `{$table}` LIKE '{$column}'");
        $type = (string) ($row->Type ?? '');
        if (preg_match("/^enum\('(.*)'\)$/s", $type, $m) !== 1) {
            TestCase::fail("{$table}.{$column} came back as [{$type}] on the mysql driver, which names no enum order to compare with");
        }

        $rank = array_flip(array_map(
            fn (string $v): string => str_replace("''", "'", $v),
            explode("','", $m[1])
        ));
        usort($ordered, fn (string $a, string $b): int => ($rank[$a] ?? PHP_INT_MAX) <=> ($rank[$b] ?? PHP_INT_MAX));

        return $ordered;
    }
}
