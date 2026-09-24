// Item 13 — card-style radio group for small binary/ternary choices:
// every option stays visible and one tap records the choice — never a
// dropdown for pass/fail-style selections (pattern established in
// Exam/Create.jsx's evaluation-result radio pair).
const toneClasses = {
    success: 'border-success-500 bg-success-50 text-success-700 ring-2 ring-success-500/20',
    danger: 'border-danger-500 bg-danger-50 text-danger-700 ring-2 ring-danger-500/20',
};

export default function RadioCards({ name, label, value, onChange, options = [], error, columns = 2, className = '' }) {
    return (
        <div>
            <div
                className={`grid gap-3 ${columns === 3 ? 'grid-cols-3' : 'grid-cols-2'} ${className}`}
                role="radiogroup"
                aria-label={label || name}
            >
                {options.map((opt) => {
                    const selected = String(value) === String(opt.value);
                    return (
                        <label
                            key={opt.value}
                            className={`flex cursor-pointer items-center justify-center gap-2 rounded-xl border px-4 py-3 text-sm font-semibold transition ${
                                selected
                                    ? (opt.tone ? toneClasses[opt.tone] : 'border-brand-500 bg-brand-50 text-brand-700 ring-2 ring-brand-500/20')
                                    : 'border-slate-200 bg-white text-slate-500 hover:border-slate-300'
                            }`}
                        >
                            <input
                                type="radio"
                                name={name}
                                value={opt.value}
                                checked={selected}
                                onChange={() => onChange(opt.value)}
                                className="sr-only"
                            />
                            {opt.label}
                        </label>
                    );
                })}
            </div>
            {error && <p className="form-error mt-1.5">{error}</p>}
        </div>
    );
}
