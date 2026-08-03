import PrimaryButton from '@/Components/PrimaryButton';

export default function EmptyState({ title = 'No data found', message = 'Get started by creating a new record.', actionLabel, onAction, icon, className = '' }) {
    return (
        <div className={`empty-state ${className}`}>
            {icon || (
                <svg className="empty-state-icon" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path strokeLinecap="round" strokeLinejoin="round" strokeWidth="1.5" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
                </svg>
            )}
            <h3 className="empty-state-title">{title}</h3>
            <p className="empty-state-message">{message}</p>
            {actionLabel && onAction && (
                <PrimaryButton onClick={onAction} className="mt-4">
                    {actionLabel}
                </PrimaryButton>
            )}
        </div>
    );
}