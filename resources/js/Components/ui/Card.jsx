export default function Card({ title, subtitle, actions, children, className = '' }) {
    return (
        <div className={`card ${className}`}>
            {(title || actions) && (
                <div className="card-header flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3">
                    <div>
                        {title && <h3 className="card-title">{title}</h3>}
                        {subtitle && <p className="card-subtitle">{subtitle}</p>}
                    </div>
                    {actions && <div className="flex items-center gap-2">{actions}</div>}
                </div>
            )}
            <div className="card-body">
                {children}
            </div>
        </div>
    );
}