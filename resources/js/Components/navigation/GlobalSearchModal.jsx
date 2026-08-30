import { router, usePage } from '@inertiajs/react';
import { useState, useEffect, useRef } from 'react';

export default function GlobalSearchModal({ isOpen, onClose }) {
    const { props } = usePage();
    const canStudentsView = props.can?.studentsView ?? false;
    const [query, setQuery] = useState('');
    const [results, setResults] = useState([]);
    const [loading, setLoading] = useState(false);
    const [selectedIndex, setSelectedIndex] = useState(0);
    const inputRef = useRef(null);

    useEffect(() => {
        if (isOpen) {
            const timer = setTimeout(() => inputRef.current?.focus(), 50);
            return () => clearTimeout(timer);
        }
    }, [isOpen]);

    // Debounced search
    useEffect(() => {
        if (!canStudentsView || query.trim().length < 2) {
            return;
        }

        const timer = setTimeout(() => {
            setLoading(true);
            fetch(route('students.quick-search') + `?query=${encodeURIComponent(query)}`)
                .then((res) => res.json())
                .then((data) => {
                    setResults(data.results || []);
                    setSelectedIndex(0);
                    setLoading(false);
                })
                .catch(() => {
                    setLoading(false);
                });
        }, 200);

        return () => clearTimeout(timer);
    }, [canStudentsView, query]);

    const activeResults = query.trim().length >= 2 ? results : [];

    const handleSelectStudent = (student) => {
        setQuery('');
        setResults([]);
        onClose();
        router.visit(route('students.show', { student: student.studentId }));
    };

    const handleClose = () => {
        setQuery('');
        setResults([]);
        setSelectedIndex(0);
        onClose();
    };

    const handleKeyDown = (e) => {
        if (e.key === 'Escape') {
            handleClose();
        } else if (e.key === 'ArrowDown') {
            e.preventDefault();
            setSelectedIndex((prev) => (prev < activeResults.length - 1 ? prev + 1 : prev));
        } else if (e.key === 'ArrowUp') {
            e.preventDefault();
            setSelectedIndex((prev) => (prev > 0 ? prev - 1 : 0));
        } else if (e.key === 'Enter') {
            e.preventDefault();
            if (activeResults[selectedIndex]) {
                handleSelectStudent(activeResults[selectedIndex]);
            }
        }
    };

    if (!isOpen) return null;

    return (
        <div className="fixed inset-0 z-50 flex items-start justify-center pt-16 sm:pt-24 p-4">
            {/* Backdrop */}
            <div
                className="fixed inset-0 bg-slate-950/70 backdrop-blur-md transition-opacity animate-fade-in"
                onClick={handleClose}
            />

            {/* Modal Dialog */}
            <div
                onKeyDown={handleKeyDown}
                className="relative w-full max-w-2xl bg-white dark:bg-slate-900 rounded-2xl shadow-2xl border border-slate-200 dark:border-slate-800 overflow-hidden z-10 transform transition-all animate-scale-up"
            >
                {/* Search Bar Input */}
                <div className="flex items-center px-4 py-3.5 border-b border-slate-100 dark:border-slate-800 bg-slate-50/50 dark:bg-slate-900/50">
                    <svg className="w-5 h-5 text-slate-400 mr-3 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path strokeLinecap="round" strokeLinejoin="round" strokeWidth="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z" />
                    </svg>
                    <input
                        ref={inputRef}
                        type="text"
                        value={query}
                        onChange={(e) => setQuery(e.target.value)}
                        placeholder="Search student by Name, School ID (e.g. 2026-0001), or Course..."
                        className="w-full bg-transparent border-0 p-0 text-slate-900 dark:text-white placeholder-slate-400 text-base focus:ring-0 focus:outline-none"
                    />
                    {loading && (
                        <div className="animate-spin rounded-full h-4 w-4 border-2 border-seait-500 border-t-transparent mr-2 flex-shrink-0" />
                    )}
                    <kbd className="hidden sm:inline-block px-2 py-0.5 text-[10px] font-mono font-bold text-slate-400 bg-slate-200 dark:bg-slate-800 rounded border border-slate-300 dark:border-slate-700">
                        ESC
                    </kbd>
                </div>

                {/* Results List */}
                <div className="max-h-96 overflow-y-auto divide-y divide-slate-100 dark:divide-slate-800/60 p-2">
                    {!canStudentsView ? (
                        <div className="py-12 px-6 text-center text-slate-400 dark:text-slate-500">
                            <svg className="w-10 h-10 mx-auto mb-2 opacity-40 text-slate-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path strokeLinecap="round" strokeLinejoin="round" strokeWidth="1.5" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z" />
                            </svg>
                            <p className="text-sm font-medium text-slate-600 dark:text-slate-400">Student search unavailable</p>
                            <p className="text-xs text-slate-400 mt-1">Your role does not include access to student records. Contact the System Administrator if you need it.</p>
                        </div>
                    ) : query.trim().length < 2 ? (
                        <div className="py-12 px-6 text-center text-slate-400 dark:text-slate-500">
                            <svg className="w-10 h-10 mx-auto mb-2 opacity-40 text-seait-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path strokeLinecap="round" strokeLinejoin="round" strokeWidth="1.5" d="M12 4.354a4 4 0 110 5.292M15 21H3v-1a6 6 0 0112 0v1zm0 0h6v-1a6 6 0 00-9-5.197M13 7a4 4 0 11-8 0 4 4 0 018 0z" />
                            </svg>
                            <p className="text-sm font-medium text-slate-600 dark:text-slate-400">Search Students Across All 10 Desks</p>
                            <p className="text-xs text-slate-400 mt-1">Type at least 2 characters to search by ID number, last name, or program.</p>
                        </div>
                    ) : activeResults.length === 0 && !loading ? (
                        <div className="py-12 px-6 text-center text-slate-400">
                            <p className="text-sm font-medium text-slate-600 dark:text-slate-400">No students found matching "{query}"</p>
                            <p className="text-xs text-slate-400 mt-1">Try searching by partial name or full School ID Number.</p>
                        </div>
                    ) : (
                        activeResults.map((student, idx) => {
                            const isSelected = idx === selectedIndex;
                            return (
                                <div
                                    key={student.studentId}
                                    onClick={() => handleSelectStudent(student)}
                                    onMouseEnter={() => setSelectedIndex(idx)}
                                    className={`p-3 rounded-xl cursor-pointer transition-all flex items-center justify-between gap-3 ${
                                        isSelected
                                            ? 'bg-seait-50/80 dark:bg-seait-900/30 text-seait-900 dark:text-seait-100'
                                            : 'hover:bg-slate-50 dark:hover:bg-slate-800/40 text-slate-700 dark:text-slate-300'
                                    }`}
                                >
                                    <div className="flex items-center gap-3 min-w-0">
                                        <div className={`h-10 w-10 rounded-xl flex items-center justify-center font-bold text-xs flex-shrink-0 ${
                                            isSelected ? 'bg-seait-600 text-white shadow-xs' : 'bg-slate-100 dark:bg-slate-800 text-slate-600 dark:text-slate-300'
                                        }`}>
                                            {student.lastName?.slice(0, 2).toUpperCase() || 'ST'}
                                        </div>
                                        <div className="min-w-0">
                                            <div className="flex items-center gap-2">
                                                <span className="font-bold text-sm text-slate-900 dark:text-white truncate">
                                                    {student.lastName}, {student.firstName} {student.middleName ? `${student.middleName[0]}.` : ''}
                                                </span>
                                                <span className="font-mono text-xs text-seait-600 dark:text-seait-400 font-semibold px-1.5 py-0.5 rounded bg-seait-50 dark:bg-seait-950/50 border border-seait-200 dark:border-seait-800/60">
                                                    {student.schoolIdNumber}
                                                </span>
                                            </div>
                                            <p className="text-xs text-slate-500 dark:text-slate-400 truncate mt-0.5">
                                                {student.courseName || 'Unassigned Course'} {student.studentType ? `• ${student.studentType}` : ''}
                                            </p>
                                        </div>
                                    </div>

                                    <div className="flex items-center gap-2 flex-shrink-0">
                                        {student.enrollmentStatus && (
                                            <span className="text-[10px] uppercase font-bold px-2 py-0.5 rounded-full bg-slate-100 dark:bg-slate-800 text-slate-600 dark:text-slate-300">
                                                {student.enrollmentStatus}
                                            </span>
                                        )}
                                        <kbd className="hidden sm:inline-block text-[10px] font-mono text-slate-400">↵</kbd>
                                    </div>
                                </div>
                            );
                        })
                    )}
                </div>

                {/* Footer Command Bar */}
                <div className="px-4 py-2.5 bg-slate-50 dark:bg-slate-950/70 border-t border-slate-100 dark:border-slate-800 flex items-center justify-between text-[11px] text-slate-400">
                    <div className="flex items-center gap-3">
                        <span><kbd className="font-mono font-bold bg-white dark:bg-slate-800 px-1.5 py-0.5 rounded border border-slate-200 dark:border-slate-700">↑</kbd> <kbd className="font-mono font-bold bg-white dark:bg-slate-800 px-1.5 py-0.5 rounded border border-slate-200 dark:border-slate-700">↓</kbd> Navigate</span>
                        <span><kbd className="font-mono font-bold bg-white dark:bg-slate-800 px-1.5 py-0.5 rounded border border-slate-200 dark:border-slate-700">↵</kbd> Open Student 360</span>
                    </div>
                    <span>Global Desk Search</span>
                </div>
            </div>
        </div>
    );
}
