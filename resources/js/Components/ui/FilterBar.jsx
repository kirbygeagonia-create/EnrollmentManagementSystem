import { router } from '@inertiajs/react';

export default function FilterBar({ children, onSubmit, onClear, className = '' }) {
    const handleClear = (e) => {
        e.preventDefault();
        if (onClear) {
            onClear();
        } else {
            router.visit(window.location.pathname, { preserveState: false, preserveScroll: true });
        }
    };

    return (
        <form onSubmit={onSubmit} className={`filter-bar ${className}`}>
            <div className="filter-bar-fields flex flex-col sm:flex-row gap-3 w-full flex-1">
                {children}
            </div>
            <div className="filter-bar-actions flex items-center gap-2">
                <button type="submit" className="btn btn-primary btn-sm">
                    <svg className="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path strokeLinecap="round" strokeLinejoin="round" strokeWidth="2" d="M3 4a1 1 0 011-1h16a1 1 0 011 1v2.586a1 1 0 01-.293.707l-6.414 6.414a1 1 0 00-.293.707V17l-4 4v-6.586a1 1 0 00-.293-.707L3.293 7.293A1 1 0 013 6.586V4z" />
                    </svg>
                    <span className="hidden sm:inline">Filter</span>
                </button>
                <button type="button" className="btn btn-secondary btn-sm" onClick={handleClear}>
                    <span className="hidden sm:inline">Clear</span>
                </button>
            </div>
        </form>
    );
}