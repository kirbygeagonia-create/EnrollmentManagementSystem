export default function Select({ value, onChange, options = [], placeholder, error, className = '', disabled = false, required = false, name, id }) {
    return (
        <select
            name={name}
            id={id}
            value={value}
            onChange={(e) => onChange(e.target.value)}
            disabled={disabled}
            required={required}
            className={`form-select ${error ? 'form-input-error' : ''} ${className}`}
        >
            {placeholder && (
                <option value="" disabled>
                    {placeholder}
                </option>
            )}
            {options.map((opt) => (
                <option key={opt.value} value={opt.value}>
                    {opt.label}
                </option>
            ))}
        </select>
    );
}