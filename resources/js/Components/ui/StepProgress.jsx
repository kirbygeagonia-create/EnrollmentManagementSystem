export default function StepProgress({ steps, className = '' }) {
    return (
        <div className={`step-progress ${className}`} role="list" aria-label="Enrollment workflow progress">
            {steps.map((step, index) => (
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
                        {step.status === 'pending' && (
                            <span className="text-xs font-bold">{index + 1}</span>
                        )}
                    </div>
                    <span className={`step-progress-label ${step.status}`}>{step.label}</span>
                </div>
            ))}
        </div>
    );
}