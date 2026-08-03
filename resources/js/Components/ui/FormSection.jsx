import InputError from '@/Components/InputError';

export default function FormSection({ label, error, hint, children, className = '', required = false }) {
    return (
        <div className={className}>
            <label className="form-label">
                {label}
                {required && <span className="text-danger-500 ml-1" aria-hidden="true">*</span>}
            </label>
            {children}
            {error && <InputError message={error} />}
            {hint && !error && <p className="form-hint">{hint}</p>}
        </div>
    );
}