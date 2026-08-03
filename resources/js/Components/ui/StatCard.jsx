export default function StatCard({ label, value, icon, iconBg = 'brand', trend, trendUp, className = '' }) {
    const iconBgClasses = {
        brand: 'bg-brand-100 text-brand-700',
        success: 'bg-success-100 text-success-700',
        warning: 'bg-warning-100 text-warning-700',
        danger: 'bg-danger-100 text-danger-700',
        info: 'bg-info-100 text-info-700',
        accent: 'bg-accent-100 text-accent-700',
    };

    return (
        <div className={`stat-card ${className}`}>
            <div className="flex items-start justify-between gap-4">
                <div className="flex-1 min-w-0">
                    <p className="stat-card-label">{label}</p>
                    <p className="stat-card-value truncate">{value}</p>
                    {trend && (
                        <p className={`stat-card-trend ${trendUp ? 'stat-card-trend-up' : 'stat-card-trend-down'}`}>
                            {trendUp ? '▲' : '▼'} {trend}
                        </p>
                    )}
                </div>
                {icon && (
                    <div className={`stat-card-icon ${iconBgClasses[iconBg]} flex-shrink-0`}>
                        {icon}
                    </div>
                )}
            </div>
        </div>
    );
}