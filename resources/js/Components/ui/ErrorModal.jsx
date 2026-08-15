import Modal from './Modal';

/**
 * ErrorModal — a modern, branded error display for server-side errors,
 * validation failures, network drops, and authorization rejections.
 *
 * Props:
 *   show        - boolean controlling visibility
 *   onClose     - close callback
 *   status      - HTTP status code (e.g. 403, 422, 500)
 *   title       - optional custom title
 *   message     - optional custom message
 *   errors      - optional object of validation errors { field: [messages] }
 */

const STATUS_CONFIG = {
    400: { icon: WarningIcon, title: 'Bad Request', color: 'amber', message: 'The request was malformed or contained invalid data.' },
    403: { icon: LockIcon, title: 'Access Denied', color: 'red', message: 'You do not have permission to perform this action. Contact your administrator.' },
    404: { icon: SearchIcon, title: 'Not Found', color: 'slate', message: 'The requested resource could not be found.' },
    409: { icon: ConflictIcon, title: 'Conflict', color: 'amber', message: 'This action conflicts with the current state of the resource.' },
    419: { icon: ClockIcon, title: 'Session Expired', color: 'amber', message: 'Your session has expired. Please refresh the page and try again.' },
    422: { icon: FormIcon, title: 'Validation Error', color: 'amber', message: 'Please correct the highlighted fields and try again.' },
    429: { icon: ClockIcon, title: 'Too Many Requests', color: 'amber', message: 'You are making requests too quickly. Please wait and try again.' },
    500: { icon: ServerIcon, title: 'Server Error', color: 'red', message: 'An unexpected server error occurred. Our team has been notified.' },
    503: { icon: ServerIcon, title: 'Service Unavailable', color: 'slate', message: 'The system is temporarily unavailable for maintenance. Please try again later.' },
};

const COLOR_MAP = {
    red: { bg: 'bg-red-50', ring: 'ring-red-100', icon: 'text-red-500', border: 'border-red-200', text: 'text-red-800' },
    amber: { bg: 'bg-amber-50', ring: 'ring-amber-100', icon: 'text-amber-500', border: 'border-amber-200', text: 'text-amber-800' },
    slate: { bg: 'bg-slate-50', ring: 'ring-slate-100', icon: 'text-slate-500', border: 'border-slate-200', text: 'text-slate-800' },
};

function WarningIcon({ className }) {
    return (
        <svg className={className} fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path strokeLinecap="round" strokeLinejoin="round" strokeWidth="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-2.5L13.732 4c-.77-.833-1.964-.833-2.732 0L4.082 16.5c-.77.833.192 2.5 1.732 2.5z" />
        </svg>
    );
}

function LockIcon({ className }) {
    return (
        <svg className={className} fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path strokeLinecap="round" strokeLinejoin="round" strokeWidth="2" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z" />
        </svg>
    );
}

function SearchIcon({ className }) {
    return (
        <svg className={className} fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path strokeLinecap="round" strokeLinejoin="round" strokeWidth="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z" />
        </svg>
    );
}

function ConflictIcon({ className }) {
    return (
        <svg className={className} fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path strokeLinecap="round" strokeLinejoin="round" strokeWidth="2" d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
        </svg>
    );
}

function ClockIcon({ className }) {
    return (
        <svg className={className} fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path strokeLinecap="round" strokeLinejoin="round" strokeWidth="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z" />
        </svg>
    );
}

function FormIcon({ className }) {
    return (
        <svg className={className} fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path strokeLinecap="round" strokeLinejoin="round" strokeWidth="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
        </svg>
    );
}

function ServerIcon({ className }) {
    return (
        <svg className={className} fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path strokeLinecap="round" strokeLinejoin="round" strokeWidth="2" d="M5 12h14M5 12a2 2 0 01-2-2V6a2 2 0 012-2h14a2 2 0 012 2v4a2 2 0 01-2 2M5 12a2 2 0 00-2 2v4a2 2 0 002 2h14a2 2 0 002-2v-4a2 2 0 00-2-2m-2-4h.01M17 16h.01" />
        </svg>
    );
}

export default function ErrorModal({ show = false, onClose, status = 500, title, message, errors }) {
    const config = STATUS_CONFIG[status] || STATUS_CONFIG[500];
    const colors = COLOR_MAP[config.color] || COLOR_MAP.red;
    const IconComponent = config.icon;

    const displayTitle = title || config.title;
    const displayMessage = message || config.message;

    const validationErrors = errors ? Object.entries(errors) : [];

    return (
        <Modal show={show} onClose={onClose} size="sm" closeable>
            <div className="p-6">
                {/* Icon */}
                <div className={`mx-auto w-14 h-14 rounded-2xl ${colors.bg} ring-4 ${colors.ring} flex items-center justify-center mb-5`}>
                    <IconComponent className={`w-7 h-7 ${colors.icon}`} />
                </div>

                {/* Title & Message */}
                <div className="text-center mb-5">
                    <h3 className="text-lg font-bold text-slate-900 mb-1.5">{displayTitle}</h3>
                    <p className="text-sm text-slate-500 leading-relaxed">{displayMessage}</p>
                </div>

                {/* Validation Errors List */}
                {validationErrors.length > 0 && (
                    <div className={`${colors.bg} border ${colors.border} rounded-xl p-4 mb-5 max-h-48 overflow-y-auto`}>
                        <ul className="space-y-1.5">
                            {validationErrors.map(([field, msgs]) => (
                                <li key={field} className="flex items-start gap-2">
                                    <svg className={`w-4 h-4 mt-0.5 flex-shrink-0 ${colors.icon}`} fill="currentColor" viewBox="0 0 20 20">
                                        <path fillRule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zM8.707 7.293a1 1 0 00-1.414 1.414L8.586 10l-1.293 1.293a1 1 0 101.414 1.414L10 11.414l1.293 1.293a1 1 0 001.414-1.414L11.414 10l1.293-1.293a1 1 0 00-1.414-1.414L10 8.586 8.707 7.293z" clipRule="evenodd" />
                                    </svg>
                                    <span className={`text-sm ${colors.text}`}>
                                        {Array.isArray(msgs) ? msgs.join(', ') : msgs}
                                    </span>
                                </li>
                            ))}
                        </ul>
                    </div>
                )}

                {/* Status Badge */}
                <div className="flex items-center justify-center mb-5">
                    <span className={`inline-flex items-center gap-1.5 px-3 py-1 rounded-full text-xs font-semibold ${colors.bg} ${colors.text}`}>
                        <span className={`w-1.5 h-1.5 rounded-full ${config.color === 'red' ? 'bg-red-500' : config.color === 'amber' ? 'bg-amber-500' : 'bg-slate-500'}`} />
                        HTTP {status}
                    </span>
                </div>

                {/* Actions */}
                <div className="flex justify-center gap-3">
                    {status === 419 ? (
                        <button
                            onClick={() => window.location.reload()}
                            className="btn-primary"
                        >
                            Refresh Page
                        </button>
                    ) : (
                        <button onClick={onClose} className="btn-primary">
                            Dismiss
                        </button>
                    )}
                </div>
            </div>
        </Modal>
    );
}
