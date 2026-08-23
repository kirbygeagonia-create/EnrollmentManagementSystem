import { Link } from '@inertiajs/react';
import { useState, useEffect, useRef } from 'react';

const subSystems = [
    {
        category: 'Intake & Applicant Gating',
        items: [
            {
                name: 'Admissions Desk',
                phase: 'Phase 0',
                route: 'admission.index',
                officeId: 6,
                roles: ['staff', 'officeHead', 'dean', 'programHead', 'admin'],
                color: 'from-emerald-500 to-teal-600',
                textColor: 'text-emerald-700 bg-emerald-50 border-emerald-200',
                description: 'Applicant intake, requirement checklist verification, and document digitization.',
                icon: (
                    <svg className="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path strokeLinecap="round" strokeLinejoin="round" strokeWidth="2" d="M12 4.354a4 4 0 110 5.292M15 21H3v-1a6 6 0 0112 0v1zm0 0h6v-1a6 6 0 00-9-5.197M13 7a4 4 0 11-8 0 4 4 0 018 0z" />
                    </svg>
                ),
            },
            {
                name: 'Guidance & Exam Lab',
                phase: 'Phase 0.5 & Retention',
                route: 'exam.index',
                officeId: 7,
                roles: ['staff', 'officeHead', 'dean', 'programHead', 'admin'],
                color: 'from-indigo-500 to-purple-600',
                textColor: 'text-indigo-700 bg-indigo-50 border-indigo-200',
                description: 'General & department entrance exams, board course retention gating, and scorecards.',
                icon: (
                    <svg className="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path strokeLinecap="round" strokeLinejoin="round" strokeWidth="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
                    </svg>
                ),
            },
        ],
    },
    {
        category: 'Academic & Clearance Hub',
        items: [
            {
                name: 'Campus Clearance',
                phase: 'Phase 1',
                route: 'clearance.index',
                officeId: 8,
                roles: ['staff', 'officeHead', 'dean', 'programHead', 'admin'],
                color: 'from-amber-500 to-orange-600',
                textColor: 'text-amber-700 bg-amber-50 border-amber-200',
                description: 'End-of-term multi-office sign-offs, digital clearance seals, and ₱100 lost-slip handling.',
                icon: (
                    <svg className="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path strokeLinecap="round" strokeLinejoin="round" strokeWidth="2" d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z" />
                    </svg>
                ),
            },
            {
                name: 'Academic Evaluation',
                phase: 'Phase 2',
                route: 'evaluation.index',
                officeId: 4,
                roles: ['staff', 'officeHead', 'dean', 'programHead', 'admin'],
                color: 'from-blue-600 to-cyan-700',
                textColor: 'text-blue-700 bg-blue-50 border-blue-200',
                description: 'Interactive curriculum load builder, academic standing, transferee crediting & dean signing.',
                icon: (
                    <svg className="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path strokeLinecap="round" strokeLinejoin="round" strokeWidth="2" d="M12 6.253v13m0-13C10.832 5.477 9.246 5 7.5 5S4.168 5.477 3 6.253v13C4.168 18.477 5.754 18 7.5 18s3.332.477 4.5 1.253m0-13C13.168 5.477 14.754 5 16.5 5c1.747 0 3.332.477 4.5 1.253v13C19.832 18.477 18.247 18 16.5 18c-1.746 0-3.332.477-4.5 1.253" />
                    </svg>
                ),
            },
            {
                name: 'Blocking & Timetables',
                phase: 'Phase 6',
                route: 'blocking.index',
                officeId: 5,
                roles: ['staff', 'officeHead', 'dean', 'programHead', 'admin'],
                color: 'from-cyan-600 to-teal-700',
                textColor: 'text-cyan-700 bg-cyan-50 border-cyan-200',
                description: 'Section capacity management, visual timetable matrix, room scheduling & conflict detection.',
                icon: (
                    <svg className="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path strokeLinecap="round" strokeLinejoin="round" strokeWidth="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z" />
                    </svg>
                ),
            },
        ],
    },
    {
        category: 'Finance & Cashiering',
        items: [
            {
                name: 'Scholarship & Assessment',
                phase: 'Phase 3',
                route: 'assessment.index',
                officeId: 3,
                roles: ['staff', 'officeHead', 'dean', 'programHead', 'admin'],
                color: 'from-fuchsia-600 to-purple-700',
                textColor: 'text-fuchsia-700 bg-fuchsia-50 border-fuchsia-200',
                description: 'Itemized tuition billing, stacked scholarship grant calculations, and charge waivers.',
                icon: (
                    <svg className="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path strokeLinecap="round" strokeLinejoin="round" strokeWidth="2" d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                    </svg>
                ),
            },
            {
                name: 'Cashier Payment Desk',
                phase: 'Phase 4',
                route: 'accounting.index',
                officeId: 2,
                roles: ['staff', 'officeHead', 'dean', 'programHead', 'admin'],
                color: 'from-emerald-600 to-green-700',
                textColor: 'text-emerald-700 bg-emerald-50 border-emerald-200',
                description: 'POS cashier terminal, unique OR numbering, partial payments, and live daily collection report.',
                icon: (
                    <svg className="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path strokeLinecap="round" strokeLinejoin="round" strokeWidth="2" d="M17 9V7a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2m2 4h10a2 2 0 002-2v-6a2 2 0 00-2-2H9a2 2 0 00-2 2v6a2 2 0 002 2zm7-5a2 2 0 11-4 0 2 2 0 014 0z" />
                    </svg>
                ),
            },
        ],
    },
    {
        category: 'Official Certification & Student Services',
        items: [
            {
                name: 'Registrar Official Suite',
                phase: 'Phase 5',
                route: 'registrar.index',
                officeId: 1,
                roles: ['staff', 'officeHead', 'dean', 'programHead', 'admin'],
                color: 'from-seait-600 to-amber-700',
                textColor: 'text-seait-700 bg-seait-50 border-seait-200',
                description: 'Pre-enrollment validation gate, official enrollment certification, and Class Card generation.',
                icon: (
                    <svg className="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path strokeLinecap="round" strokeLinejoin="round" strokeWidth="2" d="M19 11H5m14 0a2 2 0 012 2v6a2 2 0 01-2 2H5a2 2 0 01-2-2v-6a2 2 0 012-2m14 0V9a2 2 0 00-2-2M5 11V9a2 2 0 012-2m0 0V5a2 2 0 012-2h6a2 2 0 012 2v2M7 7h10" />
                    </svg>
                ),
            },
            {
                name: 'School Clinic',
                phase: 'Phase 7',
                route: 'clinic.index',
                officeId: 11,
                roles: ['staff', 'officeHead', 'dean', 'programHead', 'admin'],
                color: 'from-rose-500 to-pink-600',
                textColor: 'text-rose-700 bg-rose-50 border-rose-200',
                description: 'Health assessment, vital signs, physical exam findings, and PhilHealth registration verification.',
                icon: (
                    <svg className="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path strokeLinecap="round" strokeLinejoin="round" strokeWidth="2" d="M4.318 6.318a4.5 4.5 0 000 6.364L12 20.364l7.682-7.682a4.5 4.5 0 00-6.364-6.364L12 7.636l-1.318-1.318a4.5 4.5 0 00-6.364 0z" />
                    </svg>
                ),
            },
            {
                name: 'Student ID Hub',
                phase: 'Phase 8',
                route: 'id.index',
                officeId: 22,
                roles: ['staff', 'officeHead', 'dean', 'programHead', 'admin'],
                color: 'from-slate-700 to-slate-900',
                textColor: 'text-slate-700 bg-slate-100 border-slate-300',
                description: 'JZEL vendor intake, PVC card mockup, QR security encoding, validation, and card release.',
                icon: (
                    <svg className="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path strokeLinecap="round" strokeLinejoin="round" strokeWidth="2" d="M10 6H5a2 2 0 00-2 2v9a2 2 0 002 2h14a2 2 0 002-2V8a2 2 0 00-2-2h-5m-4 0V5a2 2 0 114 0v1m-4 0a2 2 0 104 0m-5 8a2 2 0 100-4 2 2 0 000 4zm0 0c1.306 0 2.417.835 2.83 2M9 14a3.001 3.001 0 00-2.83 2M15 11h3m-3 4h2" />
                    </svg>
                ),
            },
            {
                name: 'Student 360° Portal',
                phase: 'Full Lifecycle',
                route: 'students.index',
                officeId: null,
                roles: ['staff', 'officeHead', 'dean', 'programHead', 'admin'],
                color: 'from-seait-500 to-indigo-600',
                textColor: 'text-seait-700 bg-seait-50 border-seait-200',
                description: 'Complete student academic trail, 8-step workflow timeline, and demographic inspection.',
                icon: (
                    <svg className="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path strokeLinecap="round" strokeLinejoin="round" strokeWidth="2" d="M12 14l9-5-9-5-9 5 9 5z" />
                        <path strokeLinecap="round" strokeLinejoin="round" strokeWidth="2" d="M12 14l6.16-3.422a12.083 12.083 0 01.665 6.479A11.952 11.952 0 0012 20.055a11.952 11.952 0 00-6.824-2.998 12.078 12.078 0 01.665-6.479L12 14z" />
                    </svg>
                ),
            },
        ],
    },
    {
        category: 'Administration & System Controls',
        adminOnly: true,
        items: [
            {
                name: 'Reference Catalogs',
                phase: 'Admin Hub',
                route: 'admin.reference-data.index',
                officeId: null,
                roles: ['admin'],
                color: 'from-slate-600 to-slate-800',
                textColor: 'text-slate-700 bg-slate-100 border-slate-300',
                description: 'Curriculums, subjects, fee types, scholarship catalog, rooms, and academic terms.',
                icon: (
                    <svg className="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path strokeLinecap="round" strokeLinejoin="round" strokeWidth="2" d="M4 7v10c0 2.21 3.582 4 8 4s8-1.79 8-4V7M4 7c0 2.21 3.582 4 8 4s8-1.79 8-4M4 7c0-2.21 3.582-4 8-4s8 1.79 8 4m0 5c0 2.21-3.582 4-8 4s-8-1.79-8-4" />
                    </svg>
                ),
            },
            {
                name: 'User & Role Security',
                phase: 'RBAC',
                route: 'admin.users.index',
                officeId: null,
                roles: ['admin'],
                color: 'from-slate-700 to-slate-900',
                textColor: 'text-slate-700 bg-slate-100 border-slate-300',
                description: 'Staff user accounts, role permission matrix, office assignments, and account status.',
                icon: (
                    <svg className="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path strokeLinecap="round" strokeLinejoin="round" strokeWidth="2" d="M12 4.354a4 4 0 110 5.292M15 21H3v-1a6 6 0 0112 0v1zm0 0h6v-1a6 6 0 00-9-5.197M13 7a4 4 0 11-8 0 4 4 0 018 0z" />
                    </svg>
                ),
            },
            {
                name: 'System Audit Logs',
                phase: 'Compliance',
                route: 'admin.users.audit-logs',
                officeId: null,
                roles: ['admin'],
                color: 'from-slate-800 to-black',
                textColor: 'text-slate-700 bg-slate-100 border-slate-300',
                description: 'Append-only audit trail logging user operations, timestamps, and JSON state diffs.',
                icon: (
                    <svg className="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path strokeLinecap="round" strokeLinejoin="round" strokeWidth="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
                    </svg>
                ),
            },
        ],
    },
];

export default function MegaAppLauncher({ isOpen, onClose, user }) {
    const [filterQuery, setFilterQuery] = useState('');
    const inputRef = useRef(null);

    useEffect(() => {
        if (isOpen) {
            const timer = setTimeout(() => inputRef.current?.focus(), 50);
            return () => clearTimeout(timer);
        }
    }, [isOpen]);

    const handleClose = () => {
        setFilterQuery('');
        onClose();
    };

    if (!isOpen) return null;

    const isAuthorized = (item) => {
        if (user?.role === 'admin') return true;
        if (item.roles && !item.roles.includes(user?.role)) return false;
        if (item.officeId) {
            if (['dean', 'programHead'].includes(user?.role)) {
                if (user?.role === 'dean') return [4, 6, 7].includes(item.officeId);
                if (user?.role === 'programHead') return [4, 6].includes(item.officeId);
            }
            return user?.officeId === item.officeId;
        }
        return true;
    };

    return (
        <div className="fixed inset-0 z-50 overflow-y-auto" role="dialog" aria-modal="true">
            {/* Backdrop with blur */}
            <div
                className="fixed inset-0 bg-navy-950/70 backdrop-blur-md transition-opacity animate-fade-in"
                onClick={handleClose}
            />

            {/* Modal Dialog */}
            <div className="relative min-h-screen flex items-start justify-center p-4 sm:p-6 lg:p-8">
                <div className="relative w-full max-w-6xl bg-white rounded-2xl shadow-2xl border border-slate-200/80 overflow-hidden my-8 animate-scale-in">
                    {/* Header */}
                    <div className="px-6 py-5 bg-gradient-to-r from-[#0B1528] via-navy-800 to-[#0B1528] text-white flex flex-col sm:flex-row items-start sm:items-center justify-between gap-4 border-b border-white/10">
                        <div className="flex items-center gap-3">
                            <div className="h-10 w-10 rounded-xl bg-seait-500/20 border border-seait-500/40 flex items-center justify-center text-seait-400">
                                <svg className="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path strokeLinecap="round" strokeLinejoin="round" strokeWidth="2" d="M4 6a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2H6a2 2 0 01-2-2V6zM14 6a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2h-2a2 2 0 01-2-2V6zM4 16a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2H6a2 2 0 01-2-2v-2zM14 16a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2h-2a2 2 0 01-2-2v-2z" />
                                </svg>
                            </div>
                            <div>
                                <h2 className="text-xl font-heading font-bold text-white tracking-wide">
                                    Campus Desks & Sub-Systems
                                </h2>
                                <p className="text-xs text-slate-300">
                                    Select a desk workspace or administrative module to switch focus
                                </p>
                            </div>
                        </div>

                        {/* Search Filter Inside Launcher */}
                        <div className="flex items-center gap-3 w-full sm:w-auto">
                            <div className="relative w-full sm:w-64">
                                <input
                                    ref={inputRef}
                                    type="text"
                                    value={filterQuery}
                                    onChange={(e) => setFilterQuery(e.target.value)}
                                    placeholder="Filter desks or phases..."
                                    className="w-full rounded-lg bg-white/10 border border-white/20 px-3 py-1.5 pl-8 text-xs text-white placeholder-slate-400 focus:outline-none focus:ring-2 focus:ring-seait-400 focus:bg-white/20"
                                />
                                <svg className="w-4 h-4 text-slate-400 absolute left-2.5 top-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path strokeLinecap="round" strokeLinejoin="round" strokeWidth="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z" />
                                </svg>
                            </div>
                            <button
                                onClick={handleClose}
                                className="p-1.5 rounded-lg bg-white/10 hover:bg-white/20 text-slate-300 hover:text-white transition-colors"
                                aria-label="Close"
                            >
                                <svg className="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path strokeLinecap="round" strokeLinejoin="round" strokeWidth="2" d="M6 18L18 6M6 6l12 12" />
                                </svg>
                            </button>
                        </div>
                    </div>

                    {/* Body */}
                    <div className="p-6 lg:p-8 max-h-[75vh] overflow-y-auto space-y-8 bg-slate-50/50">
                        {subSystems.map((section, sIdx) => {
                            if (section.adminOnly && user?.role !== 'admin') return null;

                            const visibleItems = section.items.filter((item) => {
                                if (!isAuthorized(item)) return false;
                                if (!filterQuery) return true;
                                const q = filterQuery.toLowerCase();
                                return (
                                    item.name.toLowerCase().includes(q) ||
                                    item.phase.toLowerCase().includes(q) ||
                                    item.description.toLowerCase().includes(q)
                                );
                            });

                            if (visibleItems.length === 0) return null;

                            return (
                                <div key={sIdx} className="space-y-3">
                                    <div className="flex items-center gap-2 border-b border-slate-200 pb-2">
                                        <span className="h-2 w-2 rounded-full bg-seait-500" />
                                        <h3 className="text-xs font-bold uppercase tracking-wider text-slate-600 font-heading">
                                            {section.category}
                                        </h3>
                                    </div>

                                    <div className="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">
                                        {visibleItems.map((item, iIdx) => (
                                            <Link
                                                key={iIdx}
                                                href={route(item.route)}
                                                onClick={onClose}
                                                className="group relative bg-white rounded-xl p-4 border border-slate-200 hover:border-seait-400 shadow-xs hover:shadow-md transition-all duration-200 hover:-translate-y-0.5 flex flex-col justify-between"
                                            >
                                                <div className="flex items-start gap-3.5">
                                                    <div className={`h-11 w-11 rounded-xl bg-gradient-to-br ${item.color} text-white flex items-center justify-center shadow-xs flex-shrink-0 group-hover:scale-105 transition-transform duration-200`}>
                                                        {item.icon}
                                                    </div>
                                                    <div className="min-w-0 flex-1">
                                                        <div className="flex items-center gap-2 mb-0.5">
                                                            <span className={`text-[10px] font-bold px-2 py-0.5 rounded-full border ${item.textColor}`}>
                                                                {item.phase}
                                                            </span>
                                                        </div>
                                                        <h4 className="font-heading font-bold text-slate-900 text-sm group-hover:text-seait-600 transition-colors truncate">
                                                            {item.name}
                                                        </h4>
                                                        <p className="text-xs text-slate-500 mt-1 line-clamp-2 leading-relaxed">
                                                            {item.description}
                                                        </p>
                                                    </div>
                                                </div>

                                                <div className="mt-3 pt-2.5 border-t border-slate-100 flex items-center justify-between text-xs text-slate-400 group-hover:text-seait-600 font-medium">
                                                    <span>Open Workspace</span>
                                                    <svg className="w-4 h-4 transform group-hover:translate-x-1 transition-transform" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                        <path strokeLinecap="round" strokeLinejoin="round" strokeWidth="2" d="M9 5l7 7-7 7" />
                                                    </svg>
                                                </div>
                                            </Link>
                                        ))}
                                    </div>
                                </div>
                            );
                        })}
                    </div>

                    {/* Footer */}
                    <div className="px-6 py-3 bg-white border-t border-slate-200 flex items-center justify-between text-xs text-slate-500">
                        <div className="flex items-center gap-2">
                            <span className="font-semibold text-slate-700">Quick Hint:</span>
                            <span>Press <kbd className="px-1.5 py-0.5 bg-slate-100 border border-slate-300 rounded text-[11px] font-mono text-slate-700">Ctrl + K</kbd> to search students directly</span>
                        </div>
                        <button
                            onClick={onClose}
                            className="px-3 py-1 bg-slate-100 hover:bg-slate-200 text-slate-700 font-medium rounded-lg text-xs transition-colors"
                        >
                            Close
                        </button>
                    </div>
                </div>
            </div>
        </div>
    );
}
