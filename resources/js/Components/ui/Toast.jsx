import { usePage } from '@inertiajs/react';
import { useEffect, useRef, useState } from 'react';

const variants = {
    success: {
        label: 'Success',
        accent: 'bg-success-500',
        iconWrap: 'bg-success-100 text-success-700',
        border: 'border-success-200',
        icon: (
            <svg className="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                <path strokeLinecap="round" strokeLinejoin="round" strokeWidth="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z" />
            </svg>
        ),
    },
    warning: {
        label: 'Notice',
        accent: 'bg-warning-500',
        iconWrap: 'bg-warning-100 text-warning-700',
        border: 'border-warning-200',
        icon: (
            <svg className="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                <path strokeLinecap="round" strokeLinejoin="round" strokeWidth="2" d="M12 9v4m0 4h.01M10.29 3.86L1.82 18a2 2 0 001.71 3h16.94a2 2 0 001.71-3L13.71 3.86a2 2 0 00-3.42 0z" />
            </svg>
        ),
    },
    error: {
        label: 'Error',
        accent: 'bg-danger-500',
        iconWrap: 'bg-danger-100 text-danger-700',
        border: 'border-danger-200',
        icon: (
            <svg className="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                <path strokeLinecap="round" strokeLinejoin="round" strokeWidth="2" d="M10 14l2-2m0 0l2-2m-2 2l-2-2m2 2l2 2m7-2a9 9 0 11-18 0 9 9 0 0118 0z" />
            </svg>
        ),
    },
};

export default function Toast({ duration = 4500 }) {
    const { flash } = usePage().props;
    const [toast, setToast] = useState(null);
    const lastShown = useRef('');

    // Surface a server flash message (set via with('success'|'warning'|'error'))
    useEffect(() => {
        if (!flash) return;

        const type = ['success', 'warning', 'error'].find((key) => flash[key]);
        if (!type) return;

        const raw = flash[type];
        const message = (Array.isArray(raw) ? raw.join(', ') : String(raw ?? '')).trim();
        if (!message || message === lastShown.current) return;

        lastShown.current = message;
        setToast({ type, message });
    }, [flash]);

    // Auto-dismiss
    useEffect(() => {
        if (!toast) return;
        const timer = setTimeout(() => setToast(null), duration);
        return () => clearTimeout(timer);
    }, [toast, duration]);

    if (!toast) return null;

    const variant = variants[toast.type];

    return (
        <div
            className="fixed top-4 right-4 z-[100] w-full max-w-sm animate-in"
            role="status"
            aria-live="polite"
        >
            <div className={`card flex items-start gap-3 p-4 border-l-4 ${variant.accent} ${variant.border}`}>
                <span className={`inline-flex h-9 w-9 shrink-0 items-center justify-center rounded-xl ${variant.iconWrap}`}>
                    {variant.icon}
                </span>
                <div className="min-w-0 flex-1 pt-0.5">
                    <p className="font-heading text-sm font-semibold text-brand-900">{variant.label}</p>
                    <p className="mt-0.5 text-sm leading-snug text-brand-600 break-words">{toast.message}</p>
                </div>
                <button
                    type="button"
                    onClick={() => setToast(null)}
                    className="shrink-0 rounded-lg p-1 text-brand-400 transition-colors hover:bg-navy-100 hover:text-brand-600"
                    aria-label="Dismiss notification"
                >
                    <svg className="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                        <path strokeLinecap="round" strokeLinejoin="round" strokeWidth="2" d="M6 18L18 6M6 6l12 12" />
                    </svg>
                </button>
            </div>
        </div>
    );
}
