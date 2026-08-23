import { Link, usePage } from '@inertiajs/react';

const deskConfigs = {
    admission: {
        title: 'Admissions Desk',
        phase: 'Phase 0',
        badgeColor: 'bg-emerald-500/10 text-emerald-700 border-emerald-300',
        tabs: [
            { name: 'Applicant Queue', route: 'admission.index' },
            { name: 'Register Applicant', route: 'admission.create' },
        ],
    },
    exam: {
        title: 'Guidance & Entrance Exam Lab',
        phase: 'Phase 0.5 & Retention',
        badgeColor: 'bg-indigo-500/10 text-indigo-700 border-indigo-300',
        tabs: [
            { name: 'Exam Queue', route: 'exam.index' },
            { name: 'Record Entrance / Retention', route: 'exam.create' },
            { name: 'Pass / Fail Results', route: 'exam.results' },
        ],
    },
    clearance: {
        title: 'Campus Clearance Office',
        phase: 'Phase 1',
        badgeColor: 'bg-amber-500/10 text-amber-700 border-amber-300',
        tabs: [
            { name: 'Clearance Queue', route: 'clearance.index' },
            { name: 'Clearance Periods', route: 'clearance.periods' },
        ],
    },
    evaluation: {
        title: 'Academic Department Evaluation',
        phase: 'Phase 2',
        badgeColor: 'bg-blue-500/10 text-blue-700 border-blue-300',
        tabs: [
            { name: 'Evaluation Queue', route: 'evaluation.index' },
        ],
    },
    assessment: {
        title: 'Scholarship & Financial Assessment',
        phase: 'Phase 3',
        badgeColor: 'bg-fuchsia-500/10 text-fuchsia-700 border-fuchsia-300',
        tabs: [
            { name: 'Assessment Queue', route: 'assessment.index' },
        ],
    },
    accounting: {
        title: 'Cashier & Payment Desk',
        phase: 'Phase 4',
        badgeColor: 'bg-emerald-500/10 text-emerald-700 border-emerald-300',
        tabs: [
            { name: 'Payment Desk', route: 'accounting.index' },
            { name: 'Daily Collections Report', route: 'accounting.daily-report' },
        ],
    },
    registrar: {
        title: 'Office of the Registrar',
        phase: 'Phase 5',
        badgeColor: 'bg-seait-500/10 text-seait-700 border-seait-300',
        tabs: [
            { name: 'Approval Queue', route: 'registrar.index' },
        ],
    },
    blocking: {
        title: 'Blocking & Scheduling Office',
        phase: 'Phase 6',
        badgeColor: 'bg-cyan-500/10 text-cyan-700 border-cyan-300',
        tabs: [
            { name: 'Blocks & Timetables', route: 'blocking.index' },
        ],
    },
    clinic: {
        title: 'School Health Clinic',
        phase: 'Phase 7',
        badgeColor: 'bg-rose-500/10 text-rose-700 border-rose-300',
        tabs: [
            { name: 'Clinic Queue', route: 'clinic.index' },
        ],
    },
    id: {
        title: 'Student ID Management',
        phase: 'Phase 8',
        badgeColor: 'bg-slate-500/10 text-slate-700 border-slate-300',
        tabs: [
            { name: 'ID Request Queue', route: 'id.index' },
        ],
    },
    students: {
        title: 'Student 360° Trail',
        phase: 'Student Services',
        badgeColor: 'bg-seait-500/10 text-seait-700 border-seait-300',
        tabs: [
            { name: 'All Students Directory', route: 'students.index' },
        ],
    },
    admin: {
        title: 'System Administration & Security',
        phase: 'Admin Suite',
        badgeColor: 'bg-slate-700/10 text-slate-800 border-slate-300',
        tabs: [
            { name: 'Reference Catalogs', route: 'admin.reference-data.index' },
            { name: 'User Management', route: 'admin.users.index' },
            { name: 'Role Permissions', route: 'admin.users.roles' },
            { name: 'System Settings', route: 'admin.users.settings' },
            { name: 'Audit Logs', route: 'admin.users.audit-logs' },
        ],
    },
};

export default function DeskSubNav() {
    const { url } = usePage();

    // Determine which desk is currently active based on route path
    const activeKey = Object.keys(deskConfigs).find((key) => {
        if (key === 'admin' && (url.startsWith('/admin') || url.includes('/admin/'))) return true;
        return url.startsWith(`/${key}`) || url.includes(`/${key}/`);
    });

    const activeDesk = activeKey ? deskConfigs[activeKey] : null;

    if (!activeDesk || activeDesk.tabs.length <= 1) {
        return null;
    }

    return (
        <div className="bg-white/80 backdrop-blur border-b border-slate-200 shadow-2xs no-print">
            <div className="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8">
                <div className="flex items-center justify-between overflow-x-auto scrollbar-thin py-1.5 gap-4">
                    {/* Tabs */}
                    <nav className="flex items-center gap-1 sm:gap-2 min-w-max" aria-label="Sub-navigation">
                        {activeDesk.tabs.map((tab, idx) => {
                            const tabPath = route(tab.route).split('?')[0];
                            const isActive = url === tabPath || url.startsWith(tabPath + '/');
                            return (
                                <Link
                                    key={idx}
                                    href={route(tab.route)}
                                    className={`px-3 py-1.5 rounded-lg text-xs font-semibold transition-all duration-150 flex items-center gap-1.5 ${
                                        isActive
                                            ? 'bg-seait-600 text-white shadow-xs'
                                            : 'text-slate-600 hover:text-slate-900 hover:bg-slate-100'
                                    }`}
                                >
                                    <span>{tab.name}</span>
                                </Link>
                            );
                        })}
                    </nav>
                </div>
            </div>
        </div>
    );
}
