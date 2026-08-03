import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { Head } from '@inertiajs/react';
import { usePage } from '@inertiajs/react';
import { Link } from '@inertiajs/react';
import { Card, StatCard } from '@/Components/ui';

const quickLinks = [
    { name: 'Admissions', route: 'admission.index', icon: AdmissionIcon, roles: ['staff', 'officeHead', 'dean', 'programHead', 'admin'], offices: [6] },
    { name: 'Entrance Exam', route: 'exam.index', icon: ExamIcon, roles: ['staff', 'officeHead', 'dean', 'programHead', 'admin'], offices: [7] },
    { name: 'Evaluation', route: 'evaluation.index', icon: EvaluationIcon, roles: ['staff', 'officeHead', 'dean', 'programHead', 'admin'], offices: [4] },
    { name: 'Assessment', route: 'assessment.index', icon: AssessmentIcon, roles: ['staff', 'officeHead', 'dean', 'programHead', 'admin'], offices: [3] },
    { name: 'Accounting', route: 'accounting.index', icon: AccountingIcon, roles: ['staff', 'officeHead', 'dean', 'programHead', 'admin'], offices: [2] },
    { name: 'Clearance', route: 'clearance.index', icon: ClearanceIcon, roles: ['staff', 'officeHead', 'dean', 'programHead', 'admin'], offices: [8] },
    { name: 'Blocking', route: 'blocking.index', icon: BlockingIcon, roles: ['staff', 'officeHead', 'dean', 'programHead', 'admin'], offices: [5] },
    { name: 'Registrar', route: 'registrar.index', icon: RegistrarIcon, roles: ['staff', 'officeHead', 'dean', 'programHead', 'admin'], offices: [1] },
    { name: 'Clinic', route: 'clinic.index', icon: ClinicIcon, roles: ['staff', 'officeHead', 'dean', 'programHead', 'admin'], offices: [11] },
    { name: 'ID Office', route: 'id.index', icon: IdIcon, roles: ['staff', 'officeHead', 'dean', 'programHead', 'admin'], offices: [22] },
    { name: 'Reference Data', route: 'admin.reference-data.index', icon: DatabaseIcon, roles: ['admin'], offices: [] },
    { name: 'User Management', route: 'admin.users.index', icon: UsersIcon, roles: ['admin'], offices: [] },
];

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

export default function Dashboard() {
    const { user } = usePage().props.auth;
    const stats = usePage().props.stats || {};

    // Filter quick links same way as nav
    const accessibleLinks = quickLinks.filter(link => {
        if (!link.roles.includes(user?.role)) return false;
        if (link.offices && link.offices.length > 0) {
            if (user?.role === 'admin') return true;
            // Dean sees Admission, Exam, Evaluation modules (offices 4, 6, 7)
            if (user?.role === 'dean') return link.offices.some(o => [4, 6, 7].includes(o));
            // ProgramHead sees Admission + Evaluation (offices 4, 6)
            if (user?.role === 'programHead') return link.offices.some(o => [4, 6].includes(o));
            return link.offices.includes(user?.officeId);
        }
        return true;
    });

    const timeOfDay = new Date().getHours();
    let greeting = 'Good morning';
    if (timeOfDay >= 12 && timeOfDay < 18) greeting = 'Good afternoon';
    else if (timeOfDay >= 18) greeting = 'Good evening';

    return (
        <AuthenticatedLayout
            header={
                <div className="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
                    <div>
                        <h2 className="text-2xl font-semibold text-brand-900">{greeting}, {user?.name || 'User'}</h2>
                        <p className="text-brand-500 mt-1">Welcome to the SEAIT Enrollment Management System</p>
                    </div>
                </div>
            }
        >
            <Head title="Dashboard" />

            {/* Stats Cards */}
            <div className="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4 mb-6">
                <StatCard
                    label="Total Admissions"
                    value={stats.totalAdmissions || '—'}
                    icon={<AdmissionIcon className="h-5 w-5" />}
                    iconBg="seait"
                    trend={stats.admissionsTrend}
                    trendUp={stats.admissionsTrendUp}
                />
                <StatCard
                    label="Pending Evaluations"
                    value={stats.pendingEvaluations || '—'}
                    icon={<EvaluationIcon className="h-5 w-5" />}
                    iconBg="warning"
                    trend={stats.evaluationsTrend}
                    trendUp={stats.evaluationsTrendUp}
                />
                <StatCard
                    label="Enrolled Students"
                    value={stats.enrolledStudents || '—'}
                    icon={<RegistrarIcon className="h-5 w-5" />}
                    iconBg="success"
                    trend={stats.enrolledTrend}
                    trendUp={stats.enrolledTrendUp}
                />
                <StatCard
                    label="Revenue (This Month)"
                    value={stats.monthlyRevenue ? `₱${Number(stats.monthlyRevenue).toLocaleString()}` : '—'}
                    icon={<AccountingIcon className="h-5 w-5" />}
                    iconBg="accent"
                    trend={stats.revenueTrend}
                    trendUp={stats.revenueTrendUp}
                />
            </div>

            {/* Quick Links */}
            <Card title="Quick Access" subtitle="Navigate to frequently used modules">
                <div className="grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-4 xl:grid-cols-6 gap-3">
                    {accessibleLinks.map((link) => (
                        <Link
                            key={link.route}
                            href={route(link.route)}
                            className="card p-4 hover:shadow-card-hover hover:-translate-y-1 transition-all duration-200 group"
                        >
                            <div className="flex flex-col items-center text-center">
                                <div className="h-12 w-12 rounded-lg bg-seait-100 flex items-center justify-center text-seait-600 group-hover:bg-seait-200 group-hover:scale-105 transition-all duration-200">
                                    <link.icon className="h-6 w-6" />
                                </div>
                                <span className="mt-3 text-sm font-medium text-brand-900 group-hover:text-seait-600 transition-colors">
                                    {link.name}
                                </span>
                            </div>
                        </Link>
                    ))}
                </div>
            </Card>

            {/* Recent Activity / Notifications placeholder */}
            {user?.role === 'admin' || user?.role === 'dean' ? (
                <Card title="System Overview" subtitle="Administrative summary" className="mt-6">
                    <div className="grid grid-cols-1 sm:grid-cols-3 gap-4">
                        <div className="p-4 bg-brand-50 rounded-btn">
                            <p className="text-sm text-brand-500">Total Staff</p>
                            <p className="text-2xl font-bold text-brand-900">{stats.totalStaff || '—'}</p>
                        </div>
                        <div className="p-4 bg-brand-50 rounded-btn">
                            <p className="text-sm text-brand-500">Active Terms</p>
                            <p className="text-2xl font-bold text-brand-900">{stats.activeTerms || '—'}</p>
                        </div>
                        <div className="p-4 bg-brand-50 rounded-btn">
                            <p className="text-sm text-brand-500">Courses Offered</p>
                            <p className="text-2xl font-bold text-brand-900">{stats.totalCourses || '—'}</p>
                        </div>
                    </div>
                </Card>
            ) : null}
        </AuthenticatedLayout>
    );
}