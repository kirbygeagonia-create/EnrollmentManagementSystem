import { Component } from 'react';
import { router } from '@inertiajs/react';

/**
 * Global React Error Boundary.
 *
 * Catches unhandled JavaScript errors in the component tree and
 * renders a branded recovery screen instead of a blank white page.
 */
export default class ErrorBoundary extends Component {
    constructor(props) {
        super(props);
        this.state = { hasError: false, error: null, errorInfo: null, showStack: false };
    }

    static getDerivedStateFromError(error) {
        return { hasError: true, error };
    }

    componentDidCatch(error, errorInfo) {
        this.setState({ errorInfo });
        console.error('[ErrorBoundary]', error, errorInfo);
    }

    handleReload = () => {
        window.location.reload();
    };

    handleDashboard = () => {
        this.setState({ hasError: false, error: null, errorInfo: null, showStack: false });
        router.visit('/dashboard');
    };

    toggleStack = () => {
        this.setState((prev) => ({ showStack: !prev.showStack }));
    };

    render() {
        if (this.state.hasError) {
            return (
                <div className="min-h-screen bg-gradient-to-br from-slate-50 via-slate-100 to-slate-200 flex items-center justify-center p-4">
                    <div className="w-full max-w-lg">
                        {/* Card */}
                        <div className="bg-white rounded-2xl shadow-2xl border border-slate-200 overflow-hidden">
                            {/* Top accent bar */}
                            <div className="h-1.5 bg-gradient-to-r from-red-500 via-orange-500 to-amber-500" />

                            <div className="px-8 pt-8 pb-6 text-center">
                                {/* Error Icon */}
                                <div className="inline-flex items-center justify-center w-16 h-16 rounded-2xl bg-red-50 mb-5">
                                    <svg className="w-8 h-8 text-red-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path strokeLinecap="round" strokeLinejoin="round" strokeWidth="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-2.5L13.732 4c-.77-.833-1.964-.833-2.732 0L4.082 16.5c-.77.833.192 2.5 1.732 2.5z" />
                                    </svg>
                                </div>

                                <h2 className="text-xl font-bold text-slate-900 mb-2">Something Went Wrong</h2>
                                <p className="text-sm text-slate-500 leading-relaxed mb-6">
                                    An unexpected error occurred while rendering this page.
                                    This has been logged for investigation.
                                </p>

                                {/* Error message */}
                                <div className="bg-red-50 border border-red-100 rounded-xl p-4 mb-6 text-left">
                                    <p className="text-sm font-mono text-red-800 break-words">
                                        {this.state.error?.message || 'Unknown error'}
                                    </p>
                                </div>

                                {/* Actions */}
                                <div className="flex flex-col sm:flex-row items-center justify-center gap-3">
                                    <button
                                        onClick={this.handleReload}
                                        className="w-full sm:w-auto inline-flex items-center justify-center gap-2 px-5 py-2.5 rounded-xl bg-gradient-to-r from-seait-600 to-seait-700 text-white text-sm font-semibold shadow-sm hover:from-seait-500 hover:to-seait-600 transition-all"
                                    >
                                        <svg className="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path strokeLinecap="round" strokeLinejoin="round" strokeWidth="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15" />
                                        </svg>
                                        Reload Page
                                    </button>
                                    <button
                                        onClick={this.handleDashboard}
                                        className="w-full sm:w-auto inline-flex items-center justify-center gap-2 px-5 py-2.5 rounded-xl bg-white border border-slate-300 text-slate-800 text-sm font-semibold shadow-sm hover:bg-slate-50 transition-all"
                                    >
                                        <svg className="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path strokeLinecap="round" strokeLinejoin="round" strokeWidth="2" d="M3 12l2-2m0 0l7-7 7 7M5 10v10a1 1 0 001 1h3m10-11l2 2m-2-2v10a1 1 0 01-1 1h-3m-6 0a1 1 0 001-1v-4a1 1 0 011-1h2a1 1 0 011 1v4a1 1 0 001 1m-6 0h6" />
                                        </svg>
                                        Go to Dashboard
                                    </button>
                                </div>
                            </div>

                            {/* Stack trace toggle */}
                            <div className="border-t border-slate-100 px-8 py-3">
                                <button
                                    onClick={this.toggleStack}
                                    className="w-full flex items-center justify-between text-xs text-slate-400 hover:text-slate-600 transition-colors"
                                >
                                    <span>Technical Details</span>
                                    <svg className={`w-4 h-4 transition-transform ${this.state.showStack ? 'rotate-180' : ''}`} fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path strokeLinecap="round" strokeLinejoin="round" strokeWidth="2" d="M19 9l-7 7-7-7" />
                                    </svg>
                                </button>
                                {this.state.showStack && this.state.errorInfo && (
                                    <pre className="mt-3 p-3 bg-slate-900 text-green-400 text-[10px] leading-relaxed rounded-lg overflow-x-auto max-h-48 font-mono">
                                        {this.state.errorInfo.componentStack}
                                    </pre>
                                )}
                            </div>
                        </div>

                        {/* Footer */}
                        <p className="text-center text-xs text-slate-400 mt-4">
                            SEAIT Enrollment Management System
                        </p>
                    </div>
                </div>
            );
        }

        return this.props.children;
    }
}
