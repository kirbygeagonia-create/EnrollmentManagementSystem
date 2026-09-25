export default function PrintLayout({ title, subtitle, date, children, className = '', headerContent, footerContent }) {
    return (
        <div className={`print-layout ${className}`}>
            <div className="no-print mb-4 flex justify-end">
                <button
                    onClick={() => window.print()}
                    className="btn btn-primary"
                >
                    <svg className="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path strokeLinecap="round" strokeLinejoin="round" strokeWidth="2" d="M17 17h2a2 2 0 002-2v-4a2 2 0 00-2-2H5a2 2 0 00-2 2v4a2 2 0 002 2h2m2 4h6a2 2 0 002-2v-4a2 2 0 00-2-2H9a2 2 0 00-2 2v4a2 2 0 002 2zm8-12V5a2 2 0 00-2-2H9a2 2 0 00-2 2v4h10z" />
                    </svg>
                    Print
                </button>
            </div>

            <header className="print-header">
                <div className="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
                    <div className="flex items-center gap-4">
                        <img
                            src="/images/logos/seait-logo.png"
                            alt="SEAIT seal"
                            className="print-header-logo flex-shrink-0"
                        />
                        <div>
                            <h1 className="print-header-title">SEAIT</h1>
                            <p className="print-header-subtitle">Southeast Asian Institute of Technology</p>
                        </div>
                    </div>
                    <div className="text-right sm:text-left">
                        <h2 className="text-xl font-heading font-semibold text-brand-900">{title}</h2>
                        {subtitle && <p className="text-brand-600 mt-1">{subtitle}</p>}
                        {date && <p className="text-brand-500 text-sm mt-1">{date}</p>}
                    </div>
                </div>
                {headerContent && <div className="mt-4">{headerContent}</div>}
            </header>

            <main className="print-body">
                {children}
            </main>

            <footer className="print-footer">
                {footerContent || (
                    <>
                        <p>This document was generated on {new Date().toLocaleDateString('en-PH', { year: 'numeric', month: 'long', day: 'numeric' })}</p>
                        <p className="mt-1">SEAIT Enrollment Management System — Official Document</p>
                    </>
                )}
            </footer>
        </div>
    );
}