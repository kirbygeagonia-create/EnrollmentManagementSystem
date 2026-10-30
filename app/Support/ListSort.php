<?php

namespace App\Support;

use Illuminate\Database\Eloquent\Builder;

/**
 * The one definition of "sort this list" for a desk table.
 *
 * Every desk list the prompt §14 asks about paginates on the SERVER, so a client-side sort would order only the
 * twenty rows already on screen and present that as an ordered list — the page-describes-the-whole-set defect that
 * §28.3 records for summary tiles, reached from the other end. Sorting therefore happens in the query, and the page
 * only asks for it.
 *
 * The column names come from an allow-list held by the handler, never from the request: a user-supplied identifier
 * cannot be bound as a parameter, so quoting it into `orderBy()` would be the injection, and this shape is also what
 * lets the screen mark a header sortable only where the handler would actually obey it. The direction is reduced to
 * `asc` or `desc` rather than passed through.
 *
 * A relation column is sorted by joining it under a private alias, because the row's own eager-loaded relation is a
 * separate query and the join exists only to order the page. The select list stays the model's own (`table.*`), so
 * an aliased join adds no ambiguous column to the payload. One hop is written `join`/`foreign`/`local`; a column two
 * tables away — a fee sheet ordered by the student's surname, which sits on `students` and is reached through
 * `enrollments` — is written `joins` as a list of the same triples, and is the same mechanism with one more hop.
 */
final class ListSort
{
    /**
     * Apply the requested order to the query and answer what was actually applied.
     *
     * The returned array is what the handler echoes back to the page, so the control that painted the list can show
     * which column is ordered and in which direction after a page change or a filter. A sort the server applied but
     * never reported is a control that resets while the list stays narrowed — the other half of the filter contract
     * §28.3 owns.
     *
     * The two request values are read BY THE HANDLER and passed in, not pulled from the request inside this
     * helper. `lane46-filter-contract.php` judges whether an action obeys the controls its page sent, and a key
     * consumed one call deeper is invisible to it — a checker that cannot see the binding would either flag a
     * working screen or, worse, clear one that stopped listening. Passing them explicitly keeps the contract on
     * the line where the route binds it, which is where a reader looks.
     *
     * @param  array<string, array{column: string, join?: string, foreign?: string, local?: string}>  $allowed
     * @param  string|null  $stable  a unique column (usually the row's own key) appended to the order so that rows
     *                               sharing a sort value keep one sequence across pages — without it, two rows tied
     *                               on e.g. Year Level can arrive in either order on page 1 and page 2, which reads
     *                               to a desk as a record that duplicated or vanished between pages
     * @return array{column: string, direction: string}
     */
    public static function apply(Builder $query, ?string $asked, ?string $askedDirection, array $allowed, string $fallback, ?string $stable = null): array
    {
        $column = array_key_exists((string) $asked, $allowed) ? (string) $asked : $fallback;
        $direction = strtolower((string) $askedDirection) === 'asc' ? 'asc' : 'desc';

        $spec = $allowed[$column];
        $joins = self::joins($spec);
        $orderColumn = $spec['column'];

        if ($joins !== []) {
            foreach ($joins as $n => $join) {
                $alias = 'srt'.$n;
                $query->join(
                    $join['join'].' as '.$alias,
                    $alias.'.'.$join['foreign'],
                    '=',
                    $join['local']
                );
            }
            // A handler that names a column of the table it reached last does not have to spell the alias: the
            // chain's own end is where that column lives. An already-qualified name is obeyed as written, which is
            // how a sort reached through an earlier hop — a year level on the enrollment a fee sheet belongs to —
            // stays expressible without a second rule.
            if (! str_contains($orderColumn, '.')) {
                $orderColumn = 'srt'.(count($joins) - 1).'.'.$orderColumn;
            }
        }

        $query->orderBy($orderColumn, $direction);

        if ($stable !== null && $stable !== $orderColumn) {
            $query->orderBy($stable);
        }

        return ['column' => $column, 'direction' => $direction];
    }

    /**
     * The hops a column needs, in order.
     *
     * One hop is written `join`/`foreign`/`local`; more than one is written `joins` as a list of the same triples,
     * each one's `local` naming either a column of the table the query started from or an earlier hop's alias
     * (`srt0`, `srt1`, …). Both spellings are the same mechanism — a list of one — because two ways to express a
     * join is how a screen ends up ordering by a join its handler never wrote.
     *
     * @param  array<string, mixed>  $spec
     * @return list<array{join: string, foreign: string, local: string}>
     */
    private static function joins(array $spec): array
    {
        if (isset($spec['joins'])) {
            return array_values($spec['joins']);
        }

        return isset($spec['join'])
            ? [['join' => $spec['join'], 'foreign' => $spec['foreign'], 'local' => $spec['local']]]
            : [];
    }

    /**
     * The sort state as a page should receive it: what was applied, and what the handler would obey.
     *
     * `applied` and `available` travel together on purpose. A page that guessed its own sortable set would drift from
     * the handler the first time either changed, which is how a screen ends up offering work the save refuses — the
     * same rule that retired the desk actions painted from a status word instead of the policy.
     *
     * @param  array<string, mixed>  $allowed
     * @return array{column: string, direction: string, available: list<string>}
     */
    public static function present(array $applied, array $allowed): array
    {
        return $applied + ['available' => array_keys($allowed)];
    }
}
