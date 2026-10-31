<?php

namespace Tests\Support;

use App\Models\Students;
use PHPUnit\Framework\Assert;

/**
 * Lane 108 / §28.3 G-52: a desk screen that paints only a student's NAME may receive only that name.
 *
 * Prompt §10's data-minimisation rule, asserted on the wire rather than in the source. The source-side guard is
 * `DeskStudentLoadColumnsTest` (a bare `'student'` literal fails it) and the queue-side guards are the seven sort
 * classes; what neither reaches is the payload of a record screen — an `accounting.show` or a `printCertificate`.
 *
 * Two claims, and both are needed:
 *   1. the nested student carries EXACTLY the identity columns. Equality, not absence: `assertArrayNotHasKey('birthdate')`
 *      also passes when the load stops carrying every column, which is the same test answering green over a broken
 *      screen — and it passes when the field the desk paints went out with the ones the leak was made of;
 *   2. the name the desk actually reads is present and equal to the row's own value, so a narrowed list that
 *      silently emptied a cell cannot pass.
 *
 * `$withSuffix` is not a stylistic choice: `lane108-census-whole-student-loads.php` reads each page's whole import
 * graph, and the screens that print "Jr." are given the six-column list by the rewriter. Asking five there would drop
 * the suffix from a real document, so the caller states which set its own screen earns.
 *
 * A static call rather than a trait because six desk fixtures use it and a trait would need an import plus a `use`
 * line inside each class body — two edits per file for a helper whose behaviour is one comparison.
 */
final class DeskStudentPayload
{
    /** The identity set a narrowed load answers when the page paints no name suffix. */
    private const IDENTITY = ['firstName', 'lastName', 'middleName', 'schoolIdNumber', 'studentId'];

    public static function assertNameOnly(Students $student, ?array $node, string $where, bool $withSuffix = false): void
    {
        Assert::assertNotNull($node, "no student node reached {$where} — the payload was read wrong, so this case"
            .' measured nothing about what the desk receives');

        $expected = self::IDENTITY;
        if ($withSuffix) {
            $expected[] = 'suffix';
            sort($expected);
        }

        $actual = array_keys($node);
        sort($actual);

        Assert::assertSame(
            $expected,
            $actual,
            "the student shipped to {$where} is not the identity set — this desk reads a name and received"
            ."\n  expected: ".implode(', ', $expected)."\n  actual:   ".implode(', ', $actual)
        );

        // `passwordHash` is proven absent by the equality above, which is stronger than Students::$hidden: the model
        // hid it on a whole load, and G-52's leak was a column riding on the row being listed, not on a model.
        Assert::assertSame($student->lastName, $node['lastName'] ?? null, "{$where} lost the surname the desk paints");
        Assert::assertSame($student->firstName, $node['firstName'] ?? null, "{$where} lost the given name the desk paints");
        Assert::assertSame($student->schoolIdNumber, $node['schoolIdNumber'] ?? null, "{$where} lost the school number");

        if ($withSuffix) {
            Assert::assertArrayHasKey('suffix', $node, "{$where} paints the name suffix and the load stopped carrying it");
        }
    }
}
