import ApplicationLogo from '@/Components/ApplicationLogo';
import Dropdown from '@/Components/Dropdown';
import NavLink from '@/Components/NavLink';
import { Toast } from '@/Components/ui';
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
        label: 'Student',
        items: [
            { name: 'Student 360', route: 'students.index', icon: StudentIcon, roles: ['staff', 'officeHead', 'dean', 'programHead', 'admin'], offices: [] },
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
function StudentIcon({ className }) {
    return <svg className={className} fill="none" stroke="currentColor" viewBox="0 0 24 24"><path strokeLinecap="round" strokeLinejoin="round" strokeWidth="2" d="M12 14l9-5-9-5-9 5 9 5z" /><path strokeLinecap="round" strokeLinejoin="round" strokeWidth="2" d="M12 14l6.16-3.422a12.083 12.083 0 01.665 6.479A11.952 11.952 0 0012 20.055a11.952 11.952 0 00-6.824-2.998 12.078 12.078 0 01.665-6.479L12 14z" /></svg>;
}

export default function AuthenticatedLayout({ header, children }) {
    const { user } = usePage().props.auth;
    const { url } = usePage();
    const [sidebarOpen, setSidebarOpen] = useState(false);
    const [showingUserMenu, setShowingUserMenu] = useState(false);
    const [showingNotifications, setShowingNotifications] = useState(false);
    const [notifications, setNotifications] = useState([]);
    const [unreadCount, setUnreadCount] = useState(0);

    // Fetch notifications on mount and every 30s
    useEffect(() => {
        const fetchNotifications = () => {
            fetch(route('notifications.index'))
                .then((res) => res.json())
                .then((data) => {
                    setNotifications(data.notifications || []);
                    setUnreadCount(data.unreadCount || 0);
                })
                .catch(() => {});
        };

        fetchNotifications();
        const interval = setInterval(fetchNotifications, 30000);
        return () => clearInterval(interval);
    }, []);

    const handleMarkAllRead = () => {
        const xsrf = document.cookie
            .split('; ')
            .find((row) => row.startsWith('XSRF-TOKEN='))
            ?.split('=')[1];

        fetch(route('notifications.read-all'), {
            method: 'POST',
            headers: {
                'X-XSRF-TOKEN': xsrf ? decodeURIComponent(xsrf) : '',
                'Accept': 'application/json',
            },
        })
            .then((res) => res.json())
            .then(() => {
                setUnreadCount(0);
                setNotifications((prev) => prev.map((n) => ({ ...n, read_at: new Date().toISOString() })));
            })
            .catch(() => {});
    };

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

    // Compute breadcrumb (section label + active item name) from the current route
    const activeBreadcrumb = (() => {
        for (const section of filteredSections) {
            for (const item of section.items) {
                if (!item.roles.includes(user?.role)) continue;
                if (item.offices && item.offices.length > 0) {
                    if (['dean', 'programHead'].includes(user?.role)) {
                        // ok
                    } else if (!item.offices.includes(user?.officeId)) {
                        continue;
                    }
                }
                const routePath = route(item.route).split('?')[0];
                if (url.startsWith(routePath)) {
                    return { section: section.label, item: item.name };
                }
            }
        }
        return null;
    })();

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
                    {/* Logo / Wordmark */}
                    <div className="px-4 py-4 border-b border-white/10">
                        <Link href={route('dashboard')} className="flex items-center gap-3 group">
                            <span className="inline-flex h-10 w-10 items-center justify-center rounded-xl bg-white/10 ring-1 ring-white/20 overflow-hidden transition-transform duration-200 group-hover:scale-105">
                                <ApplicationLogo className="h-9 w-9 object-contain" />
                            </span>
                            <span className="flex flex-col leading-tight">
                                <span className="font-heading font-bold text-white text-lg tracking-wide">SEAIT</span>
                                <span className="text-[10px] font-semibold uppercase tracking-[0.18em] text-seait-400">Enrollment System</span>
                            </span>
                        </Link>
                    </div>

                    {/* Navigation */}
                    <nav className="flex-1 overflow-y-auto px-2 py-3 space-y-1" aria-label="Main navigation">
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
                                    <ul className="space-y-0.5" role="list">
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
                                                        <span className="flex items-center gap-3">
                                                            <item.icon className="nav-item-icon" />
                                                            <span>{item.name}</span>
                                                        </span>
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
                    <div className="px-4 py-3 border-t border-white/10">
                        <p className="text-[10px] uppercase tracking-[0.16em] text-slate-500 text-center font-semibold">
                            SEAIT · Enrollment Management
                        </p>
                    </div>
                </div>
            </aside>

            {/* Main Content */}
            <div className="lg:pl-64">
                {/* Top Bar */}
                <header className="top-bar no-print">
                    <div className="flex items-center justify-between h-16 px-4 sm:px-6 lg:px-8">
                        <div className="flex items-center gap-4 min-w-0">
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

                            {/* Breadcrumb / Page Title (desktop) */}
                            <div className="hidden lg:flex items-center gap-2 min-w-0">
                                {activeBreadcrumb ? (
                                    <>
                                        <span className="text-sm font-medium text-brand-400">{activeBreadcrumb.section}</span>
                                        <svg className="h-4 w-4 text-brand-300 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                                            <path strokeLinecap="round" strokeLinejoin="round" strokeWidth="2" d="M9 5l7 7-7 7" />
                                        </svg>
                                        <h1 className="font-heading font-semibold text-brand-900 text-base truncate">
                                            {activeBreadcrumb.item}
                                        </h1>
                                    </>
                                ) : (
                                    <h1 className="font-heading font-semibold text-brand-900 text-base">SEAIT</h1>
                                )}
                            </div>
                        </div>

                        <div className="flex items-center gap-3">
                            {/* Notification Bell */}
                            <div className="relative">
                                <button
                                    type="button"
                                    className="p-2 rounded-btn text-brand-500 hover:bg-brand-100 hover:text-brand-900 transition-colors relative"
                                    onClick={() => {
                                        setShowingNotifications(!showingNotifications);
                                        setShowingUserMenu(false);
                                    }}
                                    aria-label="Notifications"
                                    aria-expanded={showingNotifications}
                                >
                                    <svg className="h-6 w-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path strokeLinecap="round" strokeLinejoin="round" strokeWidth="2" d="M15 17h5l-1.405-1.405A2.032 2.032 0 0118 14.158V11a6.002 6.002 0 00-4-5.659V5a2 2 0 10-4 0v.341C7.67 6.165 6 8.388 6 11v3.159c0 .538-.214 1.055-.595 1.436L4 17h5m6 0v1a3 3 0 11-6 0v-1m6 0H9" />
                                    </svg>
                                    {unreadCount > 0 && (
                                        <span className="absolute top-0.5 right-0.5 min-w-[18px] h-[18px] px-1 rounded-full bg-seait-500 ring-2 ring-white text-white text-[10px] font-bold flex items-center justify-center">
                                            {unreadCount > 99 ? '99+' : unreadCount}
                                        </span>
                                    )}
                                </button>

                                {showingNotifications && (
                                    <div className="absolute right-0 mt-2 w-80 sm:w-96 rounded-card shadow-dropdown border border-brand-200 bg-white z-50 overflow-hidden">
                                        <div className="flex items-center justify-between px-4 py-3 border-b border-brand-100 bg-brand-50/40">
                                            <div className="flex items-center gap-2">
                                                <h3 className="font-heading font-semibold text-brand-900 text-sm">Notifications</h3>
                                                {unreadCount > 0 && (
                                                    <span className="inline-flex items-center justify-center min-w-[20px] h-5 px-1.5 rounded-full bg-seait-500 text-white text-[10px] font-bold">
                                                        {unreadCount > 99 ? '99+' : unreadCount}
                                                    </span>
                                                )}
                                            </div>
                                            <button
                                                type="button"
                                                onClick={handleMarkAllRead}
                                                className="text-xs font-medium text-seait-600 hover:text-seait-800 transition-colors"
                                            >
                                                Mark all as read
                                            </button>
                                        </div>
                                        <div className="max-h-96 overflow-y-auto divide-y divide-brand-50">
                                            {notifications.length === 0 ? (
                                                <div className="px-4 py-10 text-center">
                                                    <svg className="mx-auto h-8 w-8 text-brand-300 mb-2" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                                                        <path strokeLinecap="round" strokeLinejoin="round" strokeWidth="1.5" d="M15 17h5l-1.405-1.405A2.032 2.032 0 0118 14.158V11a6.002 6.002 0 00-4-5.659V5a2 2 0 10-4 0v.341C7.67 6.165 6 8.388 6 11v3.159c0 .538-.214 1.055-.595 1.436L4 17h5m6 0v1a3 3 0 11-6 0v-1m6 0H9" />
                                                    </svg>
                                                    <p className="text-sm text-brand-500">No notifications yet.</p>
                                                </div>
                                            ) : (
                                                notifications.map((n) => (
                                                    <div key={n.id} className={`px-4 py-3 transition-colors ${n.read_at ? '' : 'bg-seait-50/60'}`}>
                                                        <div className="flex items-start gap-2.5">
                                                            {!n.read_at && (
                                                                <span className="mt-1.5 h-2 w-2 rounded-full bg-seait-500 flex-shrink-0" aria-hidden="true" />
                                                            )}
                                                            <div className="min-w-0 flex-1">
                                                                <p className="text-sm leading-snug text-brand-900">{n.data?.message || 'Notification'}</p>
                                                                <p className="text-xs text-brand-500 mt-1">
                                                                    {n.created_at ? new Date(n.created_at).toLocaleString('en-PH') : ''}
                                                                </p>
                                                            </div>
                                                        </div>
                                                    </div>
                                                ))
                                            )}
                                        </div>
                                    </div>
                                )}
                            </div>

                            {/* User Dropdown */}
                            {/* User Dropdown */}
                            <Dropdown>
                                <Dropdown.Trigger>
                                    <button
                                        type="button"
                                        className="user-menu-trigger group flex items-center gap-2.5 px-3 py-1.5 rounded-lg hover:bg-brand-50 transition-colors"
                                        onClick={() => setShowingUserMenu(!showingUserMenu)}
                                        aria-expanded={showingUserMenu}
                                        aria-haspopup="true"
                                    >
                                        <div className="user-avatar ring-2 ring-seait-300 shadow-xs flex-shrink-0">
                                            {getInitials(user?.name || 'User')}
                                        </div>
                                        <div className="hidden sm:flex flex-col text-left leading-tight min-w-0">
                                            <span className="font-semibold text-brand-900 text-sm truncate max-w-[140px] lg:max-w-[180px]">
                                                {user?.name}
                                            </span>
                                            <span className="text-[11px] font-medium text-seait-700 truncate max-w-[140px] lg:max-w-[180px]">
                                                {user?.positionTitle || user?.office?.officeName || 'Staff'}
                                            </span>
                                        </div>
                                        <svg className="h-4 w-4 text-brand-400 group-hover:text-brand-600 transition-colors ml-0.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path strokeLinecap="round" strokeLinejoin="round" strokeWidth="2" d="M19 9l-7 7-7-7" />
                                        </svg>
                                    </button>
                                </Dropdown.Trigger>

                                <Dropdown.Content align="right" width="64" contentClasses="py-2 bg-white rounded-card shadow-dropdown border border-brand-200 divide-y divide-brand-100">
                                    <div className="px-4 py-3 bg-gradient-to-br from-brand-50/60 to-white">
                                        <div className="flex items-start gap-3">
                                            <div className="user-avatar ring-2 ring-seait-400 shadow-sm flex-shrink-0">
                                                {getInitials(user?.name || 'User')}
                                            </div>
                                            <div className="min-w-0 flex-1">
                                                <p className="text-sm font-bold text-brand-900 truncate">{user?.name}</p>
                                                <p className="text-xs font-semibold text-seait-700 mt-0.5">{user?.positionTitle || 'Staff'}</p>
                                                {user?.office?.officeName && (
                                                    <p className="text-[11px] text-brand-500 mt-0.5 truncate flex items-center gap-1">
                                                        <svg className="h-3 w-3 text-brand-400 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                            <path strokeLinecap="round" strokeLinejoin="round" strokeWidth="2" d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4" />
                                                        </svg>
                                                        {user.office.officeName}
                                                    </p>
                                                )}
                                                <p className="text-[11px] text-brand-400 mt-0.5 truncate">{user?.email}</p>
                                            </div>
                                        </div>
                                    </div>
                                    <div className="py-1">
                                        <Dropdown.Link href={route('profile.edit')} className="flex items-center gap-2 text-xs font-medium text-brand-700">
                                            <svg className="h-4 w-4 text-brand-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path strokeLinecap="round" strokeLinejoin="round" strokeWidth="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z" />
                                            </svg>
                                            My Profile & Password
                                        </Dropdown.Link>
                                    </div>
                                    <div className="py-1">
                                        <Dropdown.Link
                                            href={route('logout')}
                                            method="post"
                                            as="button"
                                            className="flex items-center gap-2 text-xs font-medium text-danger-600 hover:text-danger-700 hover:bg-danger-50"
                                        >
                                            <svg className="h-4 w-4 text-danger-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path strokeLinecap="round" strokeLinejoin="round" strokeWidth="2" d="M17 16l4-4m0 0l-4-4m4 4H7m6 4v1a3 3 0 01-3 3H6a3 3 0 01-3-3V7a3 3 0 013-3h4a3 3 0 013 3v1" />
                                            </svg>
                                            Sign Out
                                        </Dropdown.Link>
                                    </div>
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

            {/* Flash toast notifications (success/warning/error from server) */}
            <Toast />
        </div>
    );
}
