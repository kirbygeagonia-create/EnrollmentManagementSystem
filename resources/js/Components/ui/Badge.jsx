export default function Badge({ tone = 'neutral', children, className = '' }) {
    const toneClasses = {
        success: 'badge-success',
        warning: 'badge-warning',
        danger: 'badge-danger',
        info: 'badge-info',
        neutral: 'badge-neutral',
        pending: 'badge-pending',
        approved: 'badge-approved',
        rejected: 'badge-rejected',
        waived: 'badge-waived',
        incomplete: 'badge-incomplete',
        paid: 'badge-paid',
        partial: 'badge-partial',
        enrolled: 'badge-enrolled',
        dropped: 'badge-dropped',
        evaluated: 'badge-evaluated',
        assessed: 'badge-assessed',
    };

    return (
        <span className={`${toneClasses[tone] || toneClasses.neutral} ${className}`}>
            {children}
        </span>
    );
}