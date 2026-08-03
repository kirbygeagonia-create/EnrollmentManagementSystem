export default function FilterBarField({ label, children, className = '' }) {
    return (
        <div className={`filter-bar-field ${className}`}>
            {label && <label className="form-label hidden sm:block">{label}</label>}
            <label className="form-label sm:hidden">{label}</label>
            {children}
        </div>
    );
}