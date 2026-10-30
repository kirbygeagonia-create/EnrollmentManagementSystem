// Resolve dotted key paths ('enrollment.student.schoolIdNumber') against the
// row; falls back to '—' so a wrong key renders a visible dash, never a
// silent blank cell (audit 2026-09-25).
const resolveKey = (row, key) =>
    key.split('.').reduce((acc, k) => (acc == null ? acc : acc[k]), row) ?? '—';

export default function DataTable({ columns, rows, children, emptyMessage = 'No records found', className = '', sort = null, onSort = null }) {
    const hasRows = rows && rows.length > 0;

    // A header is a control only when the handler both listed the key and asked for it to be sortable: the page
    // never offers an order the server would ignore, which is the shape §28.3 records as a dead filter send.
    const sortable = (col) => onSort && sort && col.sortKey && (sort.available ?? []).includes(col.sortKey);

    return (
        <div className={`overflow-x-auto ${className}`}>
            <table className="data-table data-table-striped">
                <thead>
                    <tr>
                        {columns.map((col) => (
                            <th
                                key={col.key}
                                className={col.className}
                                aria-sort={sortable(col) && sort.column === col.sortKey
                                    ? (sort.direction === 'asc' ? 'ascending' : 'descending')
                                    : (sortable(col) ? 'none' : undefined)}
                            >
                                {sortable(col) ? (
                                    <button
                                        type="button"
                                        onClick={() => onSort(col.sortKey)}
                                        className="inline-flex items-center gap-1 font-heading font-bold uppercase tracking-wider hover:text-slate-900 dark:hover:text-white"
                                        title={sort.column === col.sortKey
                                            ? (sort.direction === 'asc' ? `Sorted ${col.sortKey} ascending — click for descending` : `Sorted ${col.sortKey} descending — click for ascending`)
                                            : `Sort by ${col.label}`}
                                    >
                                        {col.label}
                                        <span aria-hidden="true" className="text-[10px] leading-none">
                                            {sort.column === col.sortKey ? (sort.direction === 'asc' ? '▲' : '▼') : '↕'}
                                        </span>
                                    </button>
                                ) : (
                                    col.label
                                )}
                            </th>
                        ))}
                        {children && <th className="w-[1%] whitespace-nowrap">Actions</th>}
                    </tr>
                </thead>
                <tbody>
                    {hasRows ? (
                        rows.map((row, rowIndex) => (
                            <tr key={rowIndex}>
                                {columns.map((col) => (
                                    <td key={col.key} className={col.className}>
                                        {col.render ? col.render(row, rowIndex) : resolveKey(row, col.key)}
                                    </td>
                                ))}
                                {children && (
                                    <td className="whitespace-nowrap">
                                        {children(row, rowIndex)}
                                    </td>
                                )}
                            </tr>
                        ))
                    ) : (
                        <tr>
                            <td colSpan={columns.length + (children ? 1 : 0)} className="empty-state">
                                <svg className="empty-state-icon" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path strokeLinecap="round" strokeLinejoin="round" strokeWidth="1.5" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
                                </svg>
                                <p className="empty-state-message">{emptyMessage}</p>
                            </td>
                        </tr>
                    )}
                </tbody>
            </table>
        </div>
    );
}