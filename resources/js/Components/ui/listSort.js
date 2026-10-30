/**
 * The two questions every sortable desk table asks the same way.
 *
 * `App\Support\ListSort` is the server's single definition of an order; this is its mirror on the screen. A page
 * that spelled its own toggle would drift from the first one that wanted a three-state sort, and a header that
 * sends a direction the handler will not echo is the control-resets-while-the-list-stays-narrowed defect §28.3
 * already owns for filters — so both halves live here and every wired page calls them.
 */

/**
 * The direction one more click on this header asks for.
 *
 * Clicking the column already ordered flips it; clicking any other column starts at ascending, because a desk
 * reaching for a name, a date or an amount reads it top to bottom first.
 */
export function nextSortDirection(isActiveColumn, currentDirection) {
    return isActiveColumn && currentDirection === 'asc' ? 'desc' : 'asc';
}

/**
 * The two query keys a list should carry for its order.
 *
 * A table's own default is sent as nothing at all, so a plain link to the screen stays a short URL and the handler
 * is never handed a key it would have to recognise as its fallback.
 */
export function sortParams(defaultKey, sortBy, sortDirection) {
    const isDefault = !sortBy || sortBy === defaultKey;

    return {
        sort: isDefault ? undefined : sortBy,
        direction: isDefault ? undefined : sortDirection,
    };
}
