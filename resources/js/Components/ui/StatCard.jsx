export default function StatCard({ label, value, icon, iconBg = 'brand', trend, trendUp, compact = false, className = '' }) {
    const iconBgClasses = {
        brand: 'bg-slate-100 text-slate-700 ring-1 ring-slate-200',
        seait: 'bg-seait-100 text-seait-700 ring-1 ring-seait-200',
        success: 'bg-emerald-100 text-emerald-700 ring-1 ring-emerald-200',
        warning: 'bg-amber-100 text-amber-700 ring-1 ring-amber-200',
        danger: 'bg-rose-100 text-rose-700 ring-1 ring-rose-200',
        info: 'bg-blue-100 text-blue-700 ring-1 ring-blue-200',
        // 'accent' is SEAIT orange per tailwind.config (same token as seait) —
        // it used to render indigo, a color that appears nowhere else.
        accent: 'bg-seait-100 text-seait-700 ring-1 ring-seait-200',
    };

    // A missing number is not zero. `0` is an answer the server gave; an absent prop, an unanswered request or
    // a NaN from arithmetic on nothing is not, and on a desk the two are indistinguishable — so the tile states
    // that it has no number instead of inventing one.
    const absent = value === null || value === undefined || value === '' || (typeof value === 'number' && Number.isNaN(value));
    const shown = absent ? '—' : value;

    if (compact) {
        return (
            <div className={`stat-card-compact ${className}`}>
                <div className="flex-1 min-w-0 pl-1.5">
                    <p className="stat-card-label truncate">{label}</p>
                    <div className="flex items-baseline gap-2 mt-0.5">
                        <p className="text-xl sm:text-2xl font-extrabold text-slate-900 tracking-tight truncate" title={absent ? 'No number from the server' : undefined}>{shown}</p>
                        {trend && (
                            <span className={`text-[11px] font-bold ${trendUp ? 'text-emerald-600' : 'text-rose-600'}`}>
                                {trendUp ? '↑' : '↓'} {trend}
                            </span>
                        )}
                    </div>
                </div>
                {icon && (
                    <div className={`stat-card-icon ${iconBgClasses[iconBg] || iconBgClasses.brand} flex-shrink-0`}>
                        {icon}
                    </div>
                )}
            </div>
        );
    }

    return (
        <div className={`stat-card ${className}`}>
            <div className="flex items-start justify-between gap-4">
                <div className="flex-1 min-w-0">
                    <p className="stat-card-label">{label}</p>
                    <p className="stat-card-value truncate" title={absent ? 'No number from the server' : undefined}>{shown}</p>
                    {trend && (
                        <p className={`stat-card-trend ${trendUp ? 'stat-card-trend-up' : 'stat-card-trend-down'}`}>
                            {trendUp ? '▲' : '▼'} {trend}
                        </p>
                    )}
                </div>
                {icon && (
                    <div className={`stat-card-icon ${iconBgClasses[iconBg] || iconBgClasses.brand} flex-shrink-0`}>
                        {icon}
                    </div>
                )}
            </div>
        </div>
    );
}