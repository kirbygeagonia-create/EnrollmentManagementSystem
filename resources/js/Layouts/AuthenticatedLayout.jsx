import ApplicationLogo from '@/Components/ApplicationLogo';
import Dropdown from '@/Components/Dropdown';
import NavLink from '@/Components/NavLink';
import { Link, usePage } from '@inertiajs/react';
import { useState, useEffect } from 'react';

// Office ID mapping
// 1 = Registrar, 2 = Accounting, 3 = Assessment, 4 = Department Evaluation
// 5 = Blocking, 6 = Admission, 7 = Guidance/Entrance Exam, 8 = Clearance
// 11 = Clinic, 22 = ID Office

const navSections = [
    {
        label: 'Dashboard',
        items: [
            { name: 'Dashboard', route: 'dashboard', icon: DashboardIcon, roles: ['staff', 'officeHead', 'dean', 'programHead', 'admin'], offices: [] },
        ],
    },
    {
        label: 'Admission',
        items: [
            { name: 'Admissions', route: 'admission.index', icon: AdmissionIcon, roles: ['staff', 'officeHead', 'dean', 'programHead', 'admin'], offices: [6] },
            { name: 'Entrance Exam', route: 'exam.index', icon: ExamIcon, roles: ['staff', 'officeHead', 'dean', 'programHead', 'admin'], offices: [7] },
            { name: 'Evaluation', route: 'evaluation.index', icon: EvaluationIcon, roles: ['staff', 'officeHead', 'dean', 'programHead', 'admin'], offices: [4] },
        ],
    },
    {
        label: 'Assessment & Accounting',
        items: [
            { name: 'Assessment', route: 'assessment.index', icon: AssessmentIcon, roles: ['staff', 'officeHead', 'dean', 'programHead', 'admin'], offices: [3] },
            { name: 'Accounting', route: 'accounting.index', icon: AccountingIcon, roles: ['staff', 'officeHead', 'dean', 'programHead', 'admin'], offices: [2] },
            { name: 'Daily Report', route: 'accounting.daily-report', icon: ReportIcon, roles: ['staff', 'officeHead', 'dean', 'programHead', 'admin'], offices: [2] },
        ],
    },
    {
        label: 'Clearance',
        items: [
            { name: 'Clearance', route: 'clearance.index', icon: ClearanceIcon, roles: ['staff', 'officeHead', 'dean', 'programHead', 'admin'], offices: [8] },
            { name: 'Periods', route: 'clearance.periods', icon: CalendarIcon, roles: ['staff', 'officeHead', 'dean', 'programHead', 'admin'], offices: [8] },
        ],
    },
    {
        label: 'Blocking',
        items: [
            { name: 'Blocking', route: 'blocking.index', icon: BlockingIcon, roles: ['staff', 'officeHead', 'dean', 'programHead', 'admin'], offices: [5] },
        ],
    },
    {
        label: 'Registrar',
        items: [
            { name: 'Registrar', route: 'registrar.index', icon: RegistrarIcon, roles: ['staff', 'officeHead', 'dean', 'programHead', 'admin'], offices: [1] },
        ],
    },
    {
        label: 'Clinic',
        items: [
            { name: 'Clinic Records', route: 'clinic.index', icon: ClinicIcon, roles: ['staff', 'officeHead', 'dean', 'programHead', 'admin'], offices: [11] },
        ],
    },
    {
        label: 'ID Office',
        items: [
            { name: 'ID Requests', route: 'id.index', icon: IdIcon, roles: ['staff', 'officeHead', 'dean', 'programHead', 'admin'], offices: [22] },
        ],
    },
    {
        label: 'Administration',
        items: [
            { name: 'Reference Data', route: 'admin.reference-data.index', icon: DatabaseIcon, roles: ['admin'], offices: [] },
            { name: 'User Management', route: 'admin.users.index', icon: UsersIcon, roles: ['admin'], offices: [] },
        ],
        adminOnly: true,
    },
];

function DashboardIcon({ className }) {
    return <svg className={className} fill="none" stroke="currentColor" viewBox="0 0 24 24"><path strokeLinecap="round" strokeLinejoin="round" strokeWidth="2" d="M4 6a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2H6a2 2 0 01-2-2V6zM14 6a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2h-2a2 2 0 01-2-2V6zM4 16a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2H6a2 2 0 01-2-2v-2zM14 16a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2h-2a2 2 0 01-2-2v-2z" /></svg>;
}
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
function ReportIcon({ className }) {
    return <svg className={className} fill="none" stroke="currentColor" viewBox="0 0 24 24"><path strokeLinecap="round" strokeLinejoin="round" strokeWidth="2" d="M9 17v-2m3 2v-4m3 4v-6m2 10H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" /></svg>;
}
function ClearanceIcon({ className }) {
    return <svg className={className} fill="none" stroke="currentColor" viewBox="0 0 24 24"><path strokeLinecap="round" strokeLinejoin="round" strokeWidth="2" d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z" /></svg>;
}
function CalendarIcon({ className }) {
    return <svg className={className} fill="none" stroke="currentColor" viewBox="0 0 24 24"><path strokeLinecap="round" strokeLinejoin="round" strokeWidth="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z" /></svg>;
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

export default function AuthenticatedLayout({ header, children }) {
    const { user } = usePage().props.auth;
    const { url } = usePage();
    const [sidebarOpen, setSidebarOpen] = useState(false);
    const [showingUserMenu, setShowingUserMenu] = useState(false);

    // Role-aware navigation filtering
    const filteredSections = navSections.filter(section => {
        // Admin sees everything
        if (user?.role === 'admin') return true;

        // Admin-only sections (Administration) - only admin
        if (section.adminOnly) {
            return user?.role === 'admin';
        }

        // For other sections, check if any item is accessible to this user
        return section.items.some(item => {
            // Role match required
            if (!item.roles.includes(user?.role)) return false;

            // If item has specific offices, user must belong to one of them (or be admin/dean/programHead with broader access)
            if (item.offices && item.offices.length > 0) {
                // Admin already handled above
                // Dean sees Admission, Exam, Evaluation modules (offices 4, 6, 7)
                if (user?.role === 'dean') {
                    return item.offices.some(o => [4, 6, 7].includes(o));
                }
                // ProgramHead sees Admission + Evaluation (offices 4, 6)
                if (user?.role === 'programHead') {
                    return item.offices.some(o => [4, 6].includes(o));
                }
                // officeHead and staff must match their officeId
                return item.offices.includes(user?.officeId);
            }

            // No office restriction - accessible by role
            return true;
        });
    });

    const roleBadgeClasses = {
        admin: 'role-badge role-badge-admin',
        dean: 'role-badge role-badge-dean',
        officeHead: 'role-badge role-badge-officehead',
        programHead: 'role-badge role-badge-programhead',
        staff: 'role-badge role-badge-staff',
    };

    const getInitials = (name) => {
        return name
            .split(' ')
            .map(n => n[0])
            .join('')
            .toUpperCase()
            .slice(0, 2);
    };

    // Determine sidebar panel class based on role
    const getSidebarPanelClass = () => {
        switch (user?.role) {
            case 'admin': return 'panel-super';
            case 'dean': return 'panel-dean';
            case 'programHead': return 'panel-program-head';
            case 'officeHead': return 'panel-sao';
            case 'staff': return 'panel-moderator';
            default: return '';
        }
    };

    useEffect(() => {
        const handleResize = () => {
            if (window.innerWidth >= 1024) {
                setSidebarOpen(false);
            }
        };
        window.addEventListener('resize', handleResize);
        return () => window.removeEventListener('resize', handleResize);
    }, []);

    return (
        <div className="min-h-screen bg-gradient-to-b from-navy-50 to-navy-100">
            {/* Sidebar Overlay */}
            <div
                className={`sidebar-overlay ${sidebarOpen ? 'sidebar-overlay-open' : ''}`}
                onClick={() => setSidebarOpen(false)}
                aria-hidden="true"
            />

            {/* Sidebar */}
            <aside
                className={`sidebar ${getSidebarPanelClass()} ${sidebarOpen ? 'sidebar-open' : 'sidebar-collapsed'}`}
                role="navigation"
                aria-label="Main navigation"
            >
                <div className="flex flex-col h-full">
                    {/* Logo */}
                    <div className="flex items-center gap-3 p-4 border-b border-navy-800">
                        <Link href={route('dashboard')} className="flex items-center gap-3">
                            <ApplicationLogo className="h-10 w-10" />
                            <span className="font-heading font-bold text-white text-lg hidden sm:block">SEAIT</span>
                        </Link>
                    </div>

                    {/* Navigation */}
                    <nav className="flex-1 overflow-y-auto p-4 space-y-6" aria-label="Main navigation">
                        {filteredSections.map((section, sectionIndex) => {
                            // Filter items for this user
                            const visibleItems = section.items.filter(item => {
                                if (!item.roles.includes(user?.role)) return false;
                                if (item.offices && item.offices.length > 0) {
                                    if (['dean', 'programHead'].includes(user?.role)) return true;
                                    return item.offices.includes(user?.officeId);
                                }
                                return true;
                            });

                            if (visibleItems.length === 0) return null;

                            return (
                                <div key={sectionIndex} className="nav-section">
                                    <p className="nav-section-label">{section.label}</p>
                                    <ul className="space-y-1" role="list">
                                        {visibleItems.map((item, itemIndex) => {
                                            const routePath = route(item.route).split('?')[0];
                                            const isActive = url.startsWith(routePath);
                                            return (
                                                <li key={itemIndex}>
                                                    <NavLink
                                                        href={route(item.route)}
                                                        active={isActive}
                                                        className={`nav-item ${isActive ? 'nav-item-active' : 'nav-item-inactive'}`}
                                                    >
                                                        <item.icon className="nav-item-icon" />
                                                        <span>{item.name}</span>
                                                    </NavLink>
                                                </li>
                                            );
                                        })}
                                    </ul>
                                </div>
                            );
                        })}
                    </nav>

                    {/* Footer */}
                    <div className="p-4 border-t border-navy-800">
                        <p className="text-xs text-navy-500 text-center">
                            SEAIT Enrollment Management System
                        </p>
                    </div>
                </div>
            </aside>

            {/* Main Content */}
            <div className="lg:pl-64">
                {/* Top Bar */}
                <header className="top-bar no-print">
                    <div className="flex items-center justify-between h-16 px-4 sm:px-6 lg:px-8">
                        <div className="flex items-center gap-4">
                            <button
                                onClick={() => setSidebarOpen(true)}
                                className="lg:hidden p-2 rounded-btn text-brand-500 hover:bg-brand-100 transition-colors"
                                aria-label="Open navigation menu"
                                aria-expanded={sidebarOpen}
                            >
                                <svg className="h-6 w-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path strokeLinecap="round" strokeLinejoin="round" strokeWidth="2" d="M4 6h16M4 12h16M4 18h16" />
                                </svg>
                            </button>

                            <div className="hidden lg:block">
                                <h1 className="font-heading font-semibold text-brand-900 text-xl">SEAIT</h1>
                            </div>
                        </div>

                        <div className="flex items-center gap-4">
                            <Dropdown>
                                <Dropdown.Trigger>
                                    <button
                                        type="button"
                                        className="user-menu-trigger"
                                        onClick={() => setShowingUserMenu(!showingUserMenu)}
                                        aria-expanded={showingUserMenu}
                                        aria-haspopup="true"
                                    >
                                        <div className="user-avatar">
                                            {getInitials(user?.name || 'User')}
                                        </div>
                                        <span className="hidden sm:block">{user?.name}</span>
                                        <svg className="h-4 w-4 text-brand-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path strokeLinecap="round" strokeLinejoin="round" strokeWidth="2" d="M19 9l-7 7-7-7" />
                                        </svg>
                                    </button>
                                </Dropdown.Trigger>

                                <Dropdown.Content align="right" width="48" contentClasses="py-1 bg-white rounded-card shadow-dropdown border border-brand-200">
                                    <div className="px-3 py-2 border-b border-brand-100">
                                        <p className="text-sm font-medium text-brand-900">{user?.name}</p>
                                        <p className="text-xs text-brand-500">{user?.email}</p>
                                        <span className={roleBadgeClasses[user?.role] || roleBadgeClasses.staff} style={{ textTransform: 'capitalize' }}>
                                            {user?.role?.replace(/([A-Z])/g, ' $1') || 'Staff'}
                                        </span>
                                    </div>
                                    <Dropdown.Link href={route('profile.edit')}>
                                        Profile
                                    </Dropdown.Link>
                                    <Dropdown.Link
                                        href={route('logout')}
                                        method="post"
                                        as="button"
                                    >
                                        Log Out
                                    </Dropdown.Link>
                                </Dropdown.Content>
                            </Dropdown>
                        </div>
                    </div>
                </header>

                {/* Page Header */}
                {header && (
                    <header className="bg-white border-b border-brand-100 no-print">
                        <div className="mx-auto max-w-7xl px-4 py-6 sm:px-6 lg:px-8">
                            {header}
                        </div>
                    </header>
                )}

                {/* Main Content */}
                <main className="mx-auto max-w-7xl px-4 py-6 sm:px-6 lg:px-8">
                    {children}
                </main>
            </div>
        </div>
    );
}