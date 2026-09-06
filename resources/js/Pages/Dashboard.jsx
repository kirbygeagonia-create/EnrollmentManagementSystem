import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { Head, usePage, Link } from '@inertiajs/react';
import { Card, StatCard, Badge } from '@/Components/ui';
import { useState, useEffect, useMemo } from 'react';

function AdmissionIcon({ className }) {
    return <svg className={className} fill="none" stroke="currentColor" viewBox="0 0 24 24"><path strokeLinecap="round" strokeLinejoin="round" strokeWidth="2" d="M12 4.354a4 4 0 110 5.292M15 21H3v-1a6 6 0 0112 0v1zm0 0h6v-1a6 6 0 00-9-5.197M13 7a4 4 0 11-8 0 4 4 0 018 0z" /></svg>;
}
function ExamIcon({ className }) {
    return <svg className={className} fill="none" stroke="currentColor" viewBox="0 0 24 24"><path strokeLinecap="round" strokeLinejoin="round" strokeWidth="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" /></svg>;
}
function EvaluationIcon({ className }) {
    return <svg className={className} fill="none" stroke="currentColor" viewBox="0 0 24 24"><path strokeLinecap="round" strokeLinejoin="round" strokeWidth="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z" /></svg>;
}
function AssessmentIcon({ className }) {
    return <svg className={className} fill="none" stroke="currentColor" viewBox="0 0 24 24"><path strokeLinecap="round" strokeLinejoin="round" strokeWidth="2" d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z" /></svg>;
}
function AccountingIcon({ className }) {
    return <svg className={className} fill="none" stroke="currentColor" viewBox="0 0 24 24"><path strokeLinecap="round" strokeLinejoin="round" strokeWidth="2" d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z" /></svg>;
}
function ClearanceIcon({ className }) {
    return <svg className={className} fill="none" stroke="currentColor" viewBox="0 0 24 24"><path strokeLinecap="round" strokeLinejoin="round" strokeWidth="2" d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z" /></svg>;
}
function BlockingIcon({ className }) {
    return <svg className={className} fill="none" stroke="currentColor" viewBox="0 0 24 24"><path strokeLinecap="round" strokeLinejoin="round" strokeWidth="2" d="M10 14l2-2m0 0l2-2m-2 2l-2-2m2 2l2 2m7-2a9 9 0 11-18 0 9 9 0 0118 0z" /></svg>;
}
function RegistrarIcon({ className }) {
    return <svg className={className} fill="none" stroke="currentColor" viewBox="0 0 24 24"><path strokeLinecap="round" strokeLinejoin="round" strokeWidth="2" d="M19 11H5m14 0a2 2 0 012 2v6a2 2 0 01-2 2H5a2 2 0 01-2-2v-6a2 2 0 012-2m14 0V9a2 2 0 00-2-2M5 11V9a2 2 0 012-2m0 0V5a2 2 0 012-2h6a2 2 0 012 2v2M7 7h10" /></svg>;
}
function ClinicIcon({ className }) {
    return <svg className={className} fill="none" stroke="currentColor" viewBox="0 0 24 24"><path strokeLinecap="round" strokeLinejoin="round" strokeWidth="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z" /></svg>;
}
function IdIcon({ className }) {
    return <svg className={className} fill="none" stroke="currentColor" viewBox="0 0 24 24"><path strokeLinecap="round" strokeLinejoin="round" strokeWidth="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z" /></svg>;
}
function DatabaseIcon({ className }) {
    return <svg className={className} fill="none" stroke="currentColor" viewBox="0 0 24 24"><path strokeLinecap="round" strokeLinejoin="round" strokeWidth="2" d="M4 7v10c0 2.21 3.582 4 8 4s8-1.79 8-4V7M4 7c0 2.21 3.582 4 8 4s8-1.79 8-4M4 7c0-2.21 3.582-4 8-4s8 1.79 8 4m0 5c0 2.21-3.582 4-8 4s-8-1.79-8-4" /></svg>;
}
function UsersIcon({ className }) {
    return <svg className={className} fill="none" stroke="currentColor" viewBox="0 0 24 24"><path strokeLinecap="round" strokeLinejoin="round" strokeWidth="2" d="M12 4.354a4 4 0 110 5.292M15 21H3v-1a6 6 0 0112 0v1zm0 0h6v-1a6 6 0 00-9-5.197M13 7a4 4 0 11-8 0 4 4 0 018 0z" /></svg>;
}

const quickLinks = [
    { name: 'Admissions', category: 'admissions', desc: 'Process student applicants', route: 'admission.index', icon: AdmissionIcon, roles: ['staff', 'officeHead', 'dean', 'programHead', 'admin'], offices: [6] },
    { name: 'Entrance Exam', category: 'admissions', desc: 'Lab scoring & retention', route: 'exam.index', icon: ExamIcon, roles: ['staff', 'officeHead', 'dean', 'programHead', 'admin'], offices: [4, 7] },
    { name: 'Evaluation', category: 'admissions', desc: 'Curriculum & subject review', route: 'evaluation.index', icon: EvaluationIcon, roles: ['staff', 'officeHead', 'dean', 'programHead', 'admin'], offices: [4, 5] },
    { name: 'Assessment', category: 'finance', desc: 'Fee assessment & discounts', route: 'assessment.index', icon: AssessmentIcon, roles: ['staff', 'officeHead', 'dean', 'programHead', 'admin'], offices: [3] },
    { name: 'Accounting', category: 'finance', desc: 'Cashier payments & receipts', route: 'accounting.index', icon: AccountingIcon, roles: ['staff', 'officeHead', 'dean', 'programHead', 'admin'], offices: [2] },
    { name: 'Clearance', category: 'finance', desc: 'Student clearance sign-offs', route: 'clearance.index', icon: ClearanceIcon, roles: ['staff', 'officeHead', 'dean', 'programHead', 'admin'], offices: [6, 8] },
    { name: 'Registrar', category: 'records', desc: 'Enrollment & official records', route: 'registrar.index', icon: RegistrarIcon, roles: ['staff', 'officeHead', 'dean', 'programHead', 'admin'], offices: [1] },
    { name: 'Blocking', category: 'records', desc: 'Timetables & section loads', route: 'blocking.index', icon: BlockingIcon, roles: ['staff', 'officeHead', 'dean', 'programHead', 'admin'], offices: [5] },
    { name: 'Clinic', category: 'records', desc: 'Student medical & health', route: 'clinic.index', icon: ClinicIcon, roles: ['staff', 'officeHead', 'dean', 'programHead', 'admin'], offices: [11] },
    { name: 'ID Office', category: 'records', desc: 'Card printing & verification', route: 'id.index', icon: IdIcon, roles: ['staff', 'officeHead', 'dean', 'programHead', 'admin'], offices: [22] },
    { name: 'Reference Data', category: 'admin', desc: 'Curricula, terms & courses', route: 'admin.reference-data.index', icon: DatabaseIcon, roles: ['admin'], offices: [] },
    { name: 'User Management', category: 'admin', desc: 'Staff accounts & permissions', route: 'admin.users.index', icon: UsersIcon, roles: ['admin'], offices: [] },
];

const categoryLabels = {
    all: 'All Modules',
    admissions: 'Admissions & Entry',
    records: 'Academics & Records',
    finance: 'Finance & Clearance',
    admin: 'System Admin',
};

export default function Dashboard() {
    const { user } = usePage().props.auth;
    const stats = usePage().props.stats || {};
    const [queueCounts, setQueueCounts] = useState({});
    const [selectedCategory, setSelectedCategory] = useState('all');

    // Poll live queue counts every 30s
    useEffect(() => {
        const fetchQueueCounts = () => {
            fetch(route('dashboard.queue-counts'))
                .then((res) => res.json())
                .then((data) => setQueueCounts(data.queueCounts || {}))
                .catch(() => {});
        };

        fetchQueueCounts();
        const interval = setInterval(fetchQueueCounts, 30000);
        return () => clearInterval(interval);
    }, []);

    const queueItems = [
        { key: 'admission', label: 'Admission Queue', route: 'admission.index', offices: [6] },
        { key: 'evaluation', label: 'Evaluation Queue', route: 'evaluation.index', offices: [4, 5] },
        { key: 'assessment', label: 'Assessment Queue', route: 'assessment.index', offices: [3] },
        { key: 'accounting', label: 'Cashier Queue', route: 'accounting.index', offices: [2] },
        { key: 'registrar', label: 'Registrar Queue', route: 'registrar.index', offices: [1] },
        { key: 'blocking', label: 'Blocking Queue', route: 'blocking.index', offices: [5] },
        { key: 'clinic', label: 'Clinic Queue', route: 'clinic.index', offices: [11] },
        { key: 'id', label: 'ID Desk Queue', route: 'id.index', offices: [22] },
        { key: 'clearance', label: 'Clearance Queue', route: 'clearance.index', offices: [6, 8] },
    ];

    const canSeeQueue = (offices) => {
        if (user?.role === 'admin') return true;
        if (user?.role === 'dean') return offices.some((o) => [4, 6, 7].includes(o));
        if (user?.role === 'programHead') return offices.some((o) => [4, 6].includes(o));
        return offices.includes(user?.officeId);
    };

    const visibleQueues = queueItems.filter((q) => canSeeQueue(q.offices));

    // Filter quick links by role and office permissions
    const accessibleLinks = useMemo(() => {
        return quickLinks.filter(link => {
            if (!link.roles.includes(user?.role)) return false;
            if (link.offices && link.offices.length > 0) {
                if (user?.role === 'admin') return true;
                if (user?.role === 'dean') return link.offices.some(o => [4, 6, 7].includes(o));
                if (user?.role === 'programHead') return link.offices.some(o => [4, 6].includes(o));
                return link.offices.includes(user?.officeId);
            }
            return true;
        });
    }, [user]);

    // Filter by tab category
    const filteredLinks = useMemo(() => {
        if (selectedCategory === 'all') return accessibleLinks;
        return accessibleLinks.filter(l => l.category === selectedCategory);
    }, [accessibleLinks, selectedCategory]);

    const timeOfDay = new Date().getHours();
    let greeting = 'Good morning';
    if (timeOfDay >= 12 && timeOfDay < 18) greeting = 'Good afternoon';
    else if (timeOfDay >= 18) greeting = 'Good evening';

    const currentDateString = new Date().toLocaleDateString('en-US', {
        weekday: 'long',
        year: 'numeric',
        month: 'long',
        day: 'numeric',
    });

    return (
        <AuthenticatedLayout>
            <Head title="Executive Dashboard" />

            <div className="space-y-4 sm:space-y-5">
                {/* 1. Integrated Compact Header Strip */}
                <div className="bg-white rounded-card border border-slate-200/90 shadow-sm px-5 py-3.5 sm:px-6 sm:py-4 flex flex-col md:flex-row md:items-center md:justify-between gap-3">
                    <div className="flex items-center gap-3.5 min-w-0">
                        <div className="h-11 w-11 rounded-xl bg-gradient-to-br from-seait-600 to-seait-700 text-white flex items-center justify-center p-2 shadow-xs ring-1 ring-seait-500/30 flex-shrink-0">
                            <img src="/images/logos/seait-logo.png" alt="SEAIT" className="h-full w-full object-contain" />
                        </div>
                        <div className="min-w-0">
                            <h1 className="text-lg sm:text-xl font-extrabold text-slate-900 tracking-tight leading-tight">
                                {greeting}, {user?.name || 'Administrator'}
                            </h1>
                            <p className="text-xs font-semibold text-slate-600 flex items-center gap-2 mt-0.5">
                                <span>{currentDateString}</span>
                                <span className="h-1 w-1 rounded-full bg-slate-300" />
                                <span className="text-seait-700 font-bold uppercase tracking-wider text-[11px]">
                                    {user?.positionTitle || user?.office?.officeName || user?.role}
                                </span>
                            </p>
                        </div>
                    </div>

                    <div className="flex items-center gap-2.5 flex-wrap">
                        <div className="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-lg bg-emerald-50 border border-emerald-200 text-emerald-800 text-xs font-bold">
                            <span className="h-2 w-2 rounded-full bg-emerald-500 animate-pulse" />
                            <span>AY 2026-2027 · 1st Semester</span>
                        </div>
                        <div className="hidden xl:inline-flex items-center gap-1.5 px-2.5 py-1 rounded-lg bg-slate-100 border border-slate-200 text-slate-600 text-[11px] font-medium">
                            <span>Shortcuts:</span>
                            <span className="kbd-badge"><kbd>Ctrl</kbd>+<kbd>K</kbd> Search</span>
                            <span className="kbd-badge"><kbd>Enter ↵</kbd> Next</span>
                        </div>
                    </div>
                </div>

                {/* 2. Streamlined High-Density KPI Stat Row (Compact 72px Profile) */}
                <div className="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-3 sm:gap-4">
                    <StatCard
                        compact={true}
                        label="Total Admissions"
                        value={stats.totalAdmissions || '—'}
                        icon={<AdmissionIcon className="h-5 w-5" />}
                        iconBg="seait"
                        trend={stats.admissionsTrend}
                        trendUp={stats.admissionsTrendUp}
                    />
                    <StatCard
                        compact={true}
                        label="Pending Evaluations"
                        value={stats.pendingEvaluations || '—'}
                        icon={<EvaluationIcon className="h-5 w-5" />}
                        iconBg="warning"
                        trend={stats.evaluationsTrend}
                        trendUp={stats.evaluationsTrendUp}
                    />
                    <StatCard
                        compact={true}
                        label="Enrolled Students"
                        value={stats.enrolledStudents || '—'}
                        icon={<RegistrarIcon className="h-5 w-5" />}
                        iconBg="success"
                        trend={stats.enrolledTrend}
                        trendUp={stats.enrolledTrendUp}
                    />
                    <StatCard
                        compact={true}
                        label="Revenue (This Month)"
                        value={stats.monthlyRevenue ? `₱${Number(stats.monthlyRevenue).toLocaleString()}` : '—'}
                        icon={<AccountingIcon className="h-5 w-5" />}
                        iconBg="accent"
                        trend={stats.revenueTrend}
                        trendUp={stats.revenueTrendUp}
                    />
                </div>

                {/* 3. Two-Column Master Workstation Layout (Fits completely above the fold on desktop!) */}
                <div className="grid grid-cols-1 lg:grid-cols-12 gap-4 sm:gap-5 items-start">
                    {/* Left Column (60% width): Grouped Operational Workstation */}
                    <div className="lg:col-span-7 xl:col-span-8 space-y-4">
                        <Card
                            title="Campus Operational Desks"
                            subtitle="Direct access to university administrative modules and record workflows"
                            actions={
                                <div className="flex items-center gap-1 overflow-x-auto scrollbar-none max-w-full">
                                    {['all', 'admissions', 'records', 'finance', 'admin'].map((cat) => {
                                        // Only show admin tab if user has admin links
                                        if (cat === 'admin' && user?.role !== 'admin') return null;
                                        const isSelected = selectedCategory === cat;
                                        return (
                                            <button
                                                key={cat}
                                                type="button"
                                                onClick={() => setSelectedCategory(cat)}
                                                className={`px-2.5 py-1 rounded-lg text-xs font-bold transition-all duration-150 whitespace-nowrap ${
                                                    isSelected
                                                        ? 'bg-seait-600 text-white shadow-xs'
                                                        : 'text-slate-600 hover:text-slate-900 hover:bg-slate-100'
                                                }`}
                                            >
                                                {categoryLabels[cat]}
                                            </button>
                                        );
                                    })}
                                </div>
                            }
                        >
                            <div className="grid grid-cols-1 sm:grid-cols-2 xl:grid-cols-3 gap-3">
                                {filteredLinks.map((link) => (
                                    <Link
                                        key={link.route}
                                        href={route(link.route)}
                                        className="group p-3 sm:p-3.5 rounded-xl border border-slate-200/90 bg-slate-50/50 hover:bg-white hover:border-seait-400 hover:shadow-card-hover transition-all duration-150 flex items-start gap-3 text-left"
                                    >
                                        <div className="h-10 w-10 rounded-lg bg-seait-100 text-seait-700 flex items-center justify-center flex-shrink-0 group-hover:bg-seait-600 group-hover:text-white group-hover:scale-105 transition-all duration-200 shadow-sm">
                                            <link.icon className="h-5 w-5" />
                                        </div>
                                        <div className="flex-1 min-w-0">
                                            <div className="flex items-center justify-between gap-1">
                                                <span className="text-sm font-bold text-slate-900 group-hover:text-seait-700 transition-colors truncate">
                                                    {link.name}
                                                </span>
                                                <svg className="w-3.5 h-3.5 text-slate-400 group-hover:text-seait-600 group-hover:translate-x-0.5 transition-all flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                    <path strokeLinecap="round" strokeLinejoin="round" strokeWidth="2" d="M9 5l7 7-7 7" />
                                                </svg>
                                            </div>
                                            <p className="text-[11px] font-medium text-slate-600 mt-0.5 truncate leading-tight">
                                                {link.desc}
                                            </p>
                                        </div>
                                    </Link>
                                ))}
                            </div>
                        </Card>
                    </div>

                    {/* Right Column (40% width): Live Office Queues & Real-Time Monitoring */}
                    <div className="lg:col-span-5 xl:col-span-4 space-y-4">
                        {visibleQueues.length > 0 && (
                            <Card
                                title="Live Office Queues"
                                subtitle="Pending student workload · Real-time 30s sync"
                                actions={
                                    <span className="flex h-2 w-2 relative">
                                        <span className="animate-ping absolute inline-flex h-full w-full rounded-full bg-emerald-400 opacity-75" />
                                        <span className="relative inline-flex rounded-full h-2 w-2 bg-emerald-500" />
                                    </span>
                                }
                            >
                                <div className="space-y-2 max-h-[380px] overflow-y-auto pr-1">
                                    {visibleQueues.map((q) => {
                                        const count = queueCounts[q.key] !== undefined ? Number(queueCounts[q.key]) : 0;
                                        const hasPending = count > 0;
                                        return (
                                            <Link
                                                key={q.key}
                                                href={route(q.route)}
                                                className="flex items-center justify-between p-2.5 sm:p-3 rounded-xl border border-slate-200 bg-white hover:border-seait-400 hover:bg-slate-50/80 transition-all duration-150 group"
                                            >
                                                <div className="flex items-center gap-2.5 min-w-0">
                                                    <span className={`h-2.5 w-2.5 rounded-full flex-shrink-0 ${hasPending ? 'bg-amber-500 animate-pulse' : 'bg-emerald-500'}`} />
                                                    <div className="min-w-0">
                                                        <p className="text-xs sm:text-sm font-bold text-slate-900 group-hover:text-seait-700 truncate">
                                                            {q.label}
                                                        </p>
                                                        <p className="text-[10px] font-semibold text-slate-500">
                                                            {hasPending ? `${count} waiting in line` : 'Queue clear'}
                                                        </p>
                                                    </div>
                                                </div>

                                                <div className="flex items-center gap-2 flex-shrink-0">
                                                    <span className={`text-base font-extrabold font-mono ${hasPending ? 'text-amber-700' : 'text-slate-400'}`}>
                                                        {count}
                                                    </span>
                                                    <Badge tone={hasPending ? 'warning' : 'success'}>
                                                        {hasPending ? 'Incoming' : 'Clear'}
                                                    </Badge>
                                                </div>
                                            </Link>
                                        );
                                    })}
                                </div>
                            </Card>
                        )}

                        {/* System Overview (admin/dean only) */}
                        {(user?.role === 'admin' || user?.role === 'dean') && (
                            <Card title="System Overview" subtitle="Campus administrative metrics">
                                <div className="grid grid-cols-3 gap-2 text-center">
                                    <div className="p-2.5 rounded-xl bg-slate-50 border border-slate-200">
                                        <p className="text-[10px] font-bold text-slate-500 uppercase tracking-wider">Staff</p>
                                        <p className="text-lg font-extrabold text-slate-900 mt-0.5">{stats.totalStaff || '—'}</p>
                                    </div>
                                    <div className="p-2.5 rounded-xl bg-slate-50 border border-slate-200">
                                        <p className="text-[10px] font-bold text-slate-500 uppercase tracking-wider">Terms</p>
                                        <p className="text-lg font-extrabold text-slate-900 mt-0.5">{stats.activeTerms || '—'}</p>
                                    </div>
                                    <div className="p-2.5 rounded-xl bg-slate-50 border border-slate-200">
                                        <p className="text-[10px] font-bold text-slate-500 uppercase tracking-wider">Courses</p>
                                        <p className="text-lg font-extrabold text-slate-900 mt-0.5">{stats.totalCourses || '—'}</p>
                                    </div>
                                </div>
                            </Card>
                        )}
                    </div>
                </div>
            </div>
        </AuthenticatedLayout>
    );
}
