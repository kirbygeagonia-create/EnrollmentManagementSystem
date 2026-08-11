export default function PageHeader({ title, subtitle, actions, logo, logoAlt = 'Office logo', className = '' }) {
    return (
        <header className={`page-header ${className}`}>
            <div className="flex flex-col sm:flex-row sm:items-start sm:justify-between gap-4">
                <div className="flex items-start gap-4">
                    {logo && (
                        <div className="flex-shrink-0 h-14 w-14 rounded-card bg-white border border-brand-100 shadow-card overflow-hidden flex items-center justify-center p-1">
                            <img src={logo} alt={logoAlt} className="max-h-full max-w-full object-contain" />
                        </div>
                    )}
                    <div>
                        <h1 className="page-header-title">{title}</h1>
                        {subtitle && <p className="page-header-subtitle">{subtitle}</p>}
                    </div>
                </div>
                {actions && (
                    <div className="page-header-actions">
                        {actions}
                    </div>
                )}
            </div>
        </header>
    );
}
