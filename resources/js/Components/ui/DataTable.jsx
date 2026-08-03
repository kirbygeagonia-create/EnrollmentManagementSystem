export default function DataTable({ columns, rows, children, emptyMessage = 'No records found', className = '' }) {
    const hasRows = rows && rows.length > 0;

    return (
        <div className={`overflow-x-auto ${className}`}>
            <table className="data-table data-table-striped">
                <thead>
                    <tr>
                        {columns.map((col) => (
                            <th key={col.key} className={col.className}>
                                {col.label}
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
                                        {col.render ? col.render(row, rowIndex) : row[col.key]}
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