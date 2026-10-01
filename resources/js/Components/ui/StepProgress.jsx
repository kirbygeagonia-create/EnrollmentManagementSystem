export default function StepProgress({ steps, className = '', onStepClick }) {
    return (
        <div className={`step-progress ${className}`} role="list" aria-label="Enrollment workflow progress">
            {steps.map((step, index) => {
                const label = (
                    <span className={`step-progress-label ${step.status}`}>{step.label}</span>
                );
                return (
                    <div key={step.label} className="step-progress-item flex-1" style={{ '--step-index': index }}>
                        <div className={`step-progress-circle ${step.status}`}>
                            {step.status === 'completed' && (
                                <svg className="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path strokeLinecap="round" strokeLinejoin="round" strokeWidth="3" d="M5 13l4 4L19 7" />
                                </svg>
                            )}
                            {step.status === 'current' && (
                                <span className="text-xs font-bold">•</span>
                            )}
                            {step.status === 'returned' && (
                                <span className="text-xs font-bold">!</span>
                            )}
                            {step.status === 'notApplicable' && (
                                <span className="text-xs font-bold">—</span>
                            )}
                            {(step.status === 'pending' || step.status === 'skipped') && (
                                <span className="text-xs font-bold">{index + 1}</span>
                            )}
                        </div>
                        {onStepClick ? (
                            <button
                                type="button"
                                onClick={() => onStepClick(step, index)}
                                className={`step-progress-label ${step.status} cursor-pointer hover:text-brand-900 focus-visible:outline-2 focus-visible:outline-seait-500 rounded`}
                            >
                                {step.label}
                            </button>
                        ) : label}
                    </div>
                );
            })}
        </div>
    );
}
