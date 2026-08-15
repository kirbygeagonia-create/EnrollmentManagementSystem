export default function PageHeader({
    title,
    subtitle,
    actions,
    logo,
    logoAlt = 'Office logo',
    phaseBadge,
    officeBadge,
    className = '',
}) {
    return (
        <header className={`page-header ${className}`}>
            <div className="flex flex-col sm:flex-row sm:items-start sm:justify-between gap-4">
                <div className="flex items-start gap-4">
                    {logo && (
                        <div className="flex-shrink-0 h-16 w-16 rounded-2xl bg-white border border-brand-200/80 shadow-md ring-1 ring-black/5 overflow-hidden flex items-center justify-center p-1.5 transition-transform duration-200 hover:scale-105">
                            <img src={logo} alt={logoAlt} className="max-h-full max-w-full object-contain" />
                        </div>
                    )}
                    <div className="space-y-1">
                        <div className="flex flex-wrap items-center gap-2">
                            <h1 className="page-header-title text-xl font-bold tracking-tight text-brand-900">{title}</h1>
                            {phaseBadge && (
                                <span className="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-semibold bg-seait-100 text-seait-800 border border-seait-200">
                                    {phaseBadge}
                                </span>
                            )}
                            {officeBadge && (
                                <span className="inline-flex items-center px-2 py-0.5 rounded-md text-[11px] font-medium bg-brand-100 text-brand-700">
                                    {officeBadge}
                                </span>
                            )}
                        </div>
                        {subtitle && <p className="page-header-subtitle text-sm text-brand-600 font-normal">{subtitle}</p>}
                    </div>
                </div>
                {actions && (
                    <div className="page-header-actions flex items-center gap-2 flex-shrink-0">
                        {actions}
                    </div>
                )}
            </div>
        </header>
    );
}
