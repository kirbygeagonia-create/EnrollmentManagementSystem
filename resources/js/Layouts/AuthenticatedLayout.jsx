import ApplicationLogo from '@/Components/ApplicationLogo';
import Dropdown from '@/Components/Dropdown';
import DeskSubNav from '@/Components/navigation/DeskSubNav';
import GlobalSearchModal from '@/Components/navigation/GlobalSearchModal';
import MegaAppLauncher from '@/Components/navigation/MegaAppLauncher';
import { Toast } from '@/Components/ui';
import { Link, usePage } from '@inertiajs/react';
import { useState, useEffect } from 'react';

const deskInfoMap = {
    admission: { name: 'Admissions Desk', phase: 'Phase 0', badgeClass: 'bg-emerald-500/15 text-emerald-300 border-emerald-500/30' },
    exam: { name: 'Guidance & Exam Lab', phase: 'Phase 0.5', badgeClass: 'bg-indigo-500/15 text-indigo-300 border-indigo-500/30' },
    clearance: { name: 'Campus Clearance', phase: 'Phase 1', badgeClass: 'bg-amber-500/15 text-amber-300 border-amber-500/30' },
    evaluation: { name: 'Academic Evaluation', phase: 'Phase 2', badgeClass: 'bg-blue-500/15 text-blue-300 border-blue-500/30' },
    assessment: { name: 'Scholarship & Assessment', phase: 'Phase 3', badgeClass: 'bg-fuchsia-500/15 text-fuchsia-300 border-fuchsia-500/30' },
    accounting: { name: 'Cashier Payment Desk', phase: 'Phase 4', badgeClass: 'bg-emerald-500/15 text-emerald-300 border-emerald-500/30' },
    registrar: { name: 'Office of the Registrar', phase: 'Phase 5', badgeClass: 'bg-seait-500/15 text-seait-300 border-seait-500/30' },
    blocking: { name: 'Blocking & Timetables', phase: 'Phase 6', badgeClass: 'bg-cyan-500/15 text-cyan-300 border-cyan-500/30' },
    clinic: { name: 'School Health Clinic', phase: 'Phase 7', badgeClass: 'bg-rose-500/15 text-rose-300 border-rose-500/30' },
    id: { name: 'Student ID Management', phase: 'Phase 8', badgeClass: 'bg-slate-500/15 text-slate-300 border-slate-500/30' },
    students: { name: 'Student 360° Trail', phase: 'Student Info', badgeClass: 'bg-seait-500/15 text-seait-300 border-seait-500/30' },
    admin: { name: 'System Administration', phase: 'Admin Suite', badgeClass: 'bg-slate-600/15 text-slate-300 border-slate-500/30' },
    dashboard: { name: 'Executive Dashboard', phase: 'Overview', badgeClass: 'bg-seait-500/15 text-seait-300 border-seait-500/30' },
};

export default function AuthenticatedLayout({ header, children }) {
    const { user } = usePage().props.auth;
    const { url } = usePage();
    const [isLauncherOpen, setIsLauncherOpen] = useState(false);
    const [isSearchOpen, setIsSearchOpen] = useState(false);
    const [showingNotifications, setShowingNotifications] = useState(false);
    const [notifications, setNotifications] = useState([]);
    const [unreadCount, setUnreadCount] = useState(0);

    // Global keyboard shortcuts (Ctrl+K or Cmd+K for search)
    useEffect(() => {
        const handleKeyDown = (e) => {
            if ((e.ctrlKey || e.metaKey) && e.key === 'k') {
                e.preventDefault();
                setIsSearchOpen(true);
            }
        };
        window.addEventListener('keydown', handleKeyDown);
        return () => window.removeEventListener('keydown', handleKeyDown);
    }, []);

    // Fetch notifications
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

    // Determine active desk
    const activeKey = Object.keys(deskInfoMap).find((key) => {
        if (key === 'dashboard' && (url === '/dashboard' || url === '/')) return true;
        if (key === 'admin' && url.startsWith('/admin')) return true;
        return url.startsWith(`/${key}`) || url.includes(`/${key}/`);
    }) || 'dashboard';

    const currentDesk = deskInfoMap[activeKey] || deskInfoMap.dashboard;

    const getInitials = (name) => {
        return name
            ? name
                .split(' ')
                .map((n) => n[0])
                .join('')
                .toUpperCase()
                .slice(0, 2)
            : 'ST';
    };

    return (
        <div className="min-h-screen bg-slate-100/70 text-slate-900 flex flex-col">
            {/* Top Tier: Executive Global Command Console */}
            <header className="sticky top-0 z-40 bg-[#0B1528] text-white border-b border-slate-800 shadow-md no-print">
                <div className="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8">
                    <div className="flex items-center justify-between h-16 gap-3 sm:gap-6">
                        {/* Left: Brand + Mega-App Launcher */}
                        <div className="flex items-center gap-3 sm:gap-4 flex-shrink-0">
                            <Link href={route('dashboard')} className="flex items-center gap-2.5 group">
                                <span className="inline-flex h-9 w-9 items-center justify-center rounded-xl bg-white/10 ring-1 ring-white/20 overflow-hidden transition-transform duration-200 group-hover:scale-105">
                                    <ApplicationLogo className="h-8 w-8 object-contain" />
                                </span>
                                <div className="hidden sm:flex flex-col leading-tight">
                                    <span className="font-heading font-extrabold text-white text-base tracking-wide flex items-center gap-1.5">
                                        SEAIT
                                        <span className="text-[10px] font-bold text-seait-400 bg-seait-500/20 px-1.5 py-0.2 rounded border border-seait-500/30 uppercase tracking-wider">
                                            SIS
                                        </span>
                                    </span>
                                    <span className="text-[9px] font-semibold uppercase tracking-[0.18em] text-slate-400">
                                        Enrollment System
                                    </span>
                                </div>
                            </Link>

                            {/* Sub-System Mega-Launcher Button */}
                            <button
                                type="button"
                                onClick={() => setIsLauncherOpen(true)}
                                className="flex items-center gap-2 px-3 py-1.5 rounded-xl bg-slate-800/80 hover:bg-slate-700/80 border border-slate-700 text-slate-200 hover:text-white transition-all duration-150 shadow-2xs group"
                                aria-label="Open Campus Sub-Systems"
                            >
                                <svg className="w-4 h-4 text-seait-400 group-hover:scale-110 transition-transform" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path strokeLinecap="round" strokeLinejoin="round" strokeWidth="2" d="M4 6a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2H6a2 2 0 01-2-2V6zM14 6a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2h-2a2 2 0 01-2-2V6zM4 16a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2H6a2 2 0 01-2-2v-2zM14 16a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2h-2a2 2 0 01-2-2v-2z" />
                                </svg>
                                <span className="text-xs font-bold font-heading hidden md:inline">Desks & Apps</span>
                                <svg className="w-3.5 h-3.5 text-slate-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path strokeLinecap="round" strokeLinejoin="round" strokeWidth="2" d="M19 9l-7 7-7-7" />
                                </svg>
                            </button>

                            {/* Current Active Desk Pill */}
                            <div className="hidden lg:flex items-center gap-2 px-3 py-1 rounded-xl bg-navy-900/90 border border-slate-700/60">
                                <span className={`text-[10px] font-extrabold uppercase px-2 py-0.5 rounded-full border ${currentDesk.badgeClass}`}>
                                    {currentDesk.phase}
                                </span>
                                <span className="text-xs font-bold text-slate-200">
                                    {currentDesk.name}
                                </span>
                            </div>
                        </div>

                        {/* Center: Global Search Bar */}
                        <div className="flex-1 max-w-md mx-auto">
                            <button
                                type="button"
                                onClick={() => setIsSearchOpen(true)}
                                className="w-full flex items-center justify-between px-3.5 py-1.5 rounded-xl bg-slate-900/80 hover:bg-slate-800 border border-slate-700/80 text-slate-400 hover:text-slate-200 transition-all duration-150 shadow-inner group"
                            >
                                <div className="flex items-center gap-2.5 min-w-0">
                                    <svg className="w-4 h-4 text-slate-400 group-hover:text-seait-400 transition-colors flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path strokeLinecap="round" strokeLinejoin="round" strokeWidth="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z" />
                                    </svg>
                                    <span className="text-xs truncate">Quick search student by name or ID...</span>
                                </div>
                                <kbd className="hidden sm:inline-flex items-center gap-0.5 px-1.5 py-0.5 text-[10px] font-mono text-slate-400 bg-slate-800 border border-slate-700 rounded font-semibold">
                                    Ctrl+K
                                </kbd>
                            </button>
                        </div>

                        {/* Right: Term Badge, Notification Bell & User Dropdown */}
                        <div className="flex items-center gap-2 sm:gap-3 flex-shrink-0">
                            {/* Academic Term Indicator */}
                            <div className="hidden xl:flex items-center gap-2 px-3 py-1 rounded-xl bg-slate-800/60 border border-slate-700 text-slate-300 text-xs">
                                <span className="h-2 w-2 rounded-full bg-emerald-400 animate-pulse" />
                                <span className="font-semibold text-[11px]">2026-2027 · 1st Sem</span>
                            </div>

                            {/* Notification Bell */}
                            <div className="relative">
                                <button
                                    type="button"
                                    onClick={() => setShowingNotifications(!showingNotifications)}
                                    className="p-2 rounded-xl text-slate-300 hover:bg-slate-800 hover:text-white transition-colors relative"
                                    aria-label="Notifications"
                                >
                                    <svg className="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path strokeLinecap="round" strokeLinejoin="round" strokeWidth="2" d="M15 17h5l-1.405-1.405A2.032 2.032 0 0118 14.158V11a6.002 6.002 0 00-4-5.659V5a2 2 0 10-4 0v.341C7.67 6.165 6 8.388 6 11v3.159c0 .538-.214 1.055-.595 1.436L4 17h5m6 0v1a3 3 0 11-6 0v-1m6 0H9" />
                                    </svg>
                                    {unreadCount > 0 && (
                                        <span className="absolute top-1 right-1 min-w-[16px] h-4 px-1 rounded-full bg-seait-500 ring-2 ring-[#0B1528] text-white text-[9px] font-bold flex items-center justify-center">
                                            {unreadCount > 99 ? '99+' : unreadCount}
                                        </span>
                                    )}
                                </button>

                                {showingNotifications && (
                                    <div className="absolute right-0 mt-2 w-80 sm:w-96 rounded-2xl shadow-2xl border border-slate-200 bg-white z-50 overflow-hidden text-slate-900 animate-scale-in">
                                        <div className="flex items-center justify-between px-4 py-3 border-b border-slate-100 bg-slate-50">
                                            <div className="flex items-center gap-2">
                                                <h3 className="font-heading font-bold text-slate-900 text-sm">Notifications</h3>
                                                {unreadCount > 0 && (
                                                    <span className="inline-flex items-center justify-center min-w-[20px] h-5 px-1.5 rounded-full bg-seait-500 text-white text-[10px] font-bold">
                                                        {unreadCount > 99 ? '99+' : unreadCount}
                                                    </span>
                                                )}
                                            </div>
                                            <button
                                                type="button"
                                                onClick={handleMarkAllRead}
                                                className="text-xs font-semibold text-seait-600 hover:text-seait-800 transition-colors"
                                            >
                                                Mark all read
                                            </button>
                                        </div>
                                        <div className="max-h-80 overflow-y-auto divide-y divide-slate-100">
                                            {notifications.length === 0 ? (
                                                <div className="px-4 py-8 text-center text-xs text-slate-400">
                                                    No notifications yet.
                                                </div>
                                            ) : (
                                                notifications.map((n) => (
                                                    <div key={n.id} className={`px-4 py-3 transition-colors ${n.read_at ? '' : 'bg-seait-50/50'}`}>
                                                        <p className="text-xs text-slate-800 font-medium leading-snug">{n.data?.message || 'Notification'}</p>
                                                        <p className="text-[10px] text-slate-400 mt-1">{n.created_at ? new Date(n.created_at).toLocaleString('en-PH') : ''}</p>
                                                    </div>
                                                ))
                                            )}
                                        </div>
                                    </div>
                                )}
                            </div>

                            {/* User Profile Menu */}
                            <Dropdown>
                                <Dropdown.Trigger>
                                    <button
                                        type="button"
                                        className="flex items-center gap-2.5 p-1 sm:px-2.5 sm:py-1 rounded-xl bg-slate-800/80 hover:bg-slate-700/80 border border-slate-700 transition-all text-left"
                                    >
                                        <div className="h-8 w-8 rounded-lg bg-seait-600 text-white font-heading font-bold text-xs flex items-center justify-center ring-2 ring-seait-400/40 shadow-xs flex-shrink-0">
                                            {getInitials(user?.name)}
                                        </div>
                                        <div className="hidden sm:flex flex-col leading-tight min-w-0 pr-1">
                                            <span className="font-bold text-white text-xs truncate max-w-[120px]">
                                                {user?.name}
                                            </span>
                                            <span className="text-[10px] text-seait-400 truncate max-w-[120px] font-semibold">
                                                {user?.positionTitle || user?.office?.officeName || user?.role}
                                            </span>
                                        </div>
                                        <svg className="w-3.5 h-3.5 text-slate-400 hidden sm:block" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path strokeLinecap="round" strokeLinejoin="round" strokeWidth="2" d="M19 9l-7 7-7-7" />
                                        </svg>
                                    </button>
                                </Dropdown.Trigger>

                                <Dropdown.Content align="right" width="60" contentClasses="py-2 bg-white rounded-2xl shadow-2xl border border-slate-200 divide-y divide-slate-100 text-slate-900">
                                    <div className="px-4 py-3 bg-gradient-to-br from-slate-50 to-white">
                                        <p className="text-xs font-extrabold text-slate-900 truncate">{user?.name}</p>
                                        <p className="text-[11px] font-bold text-seait-600 mt-0.5">{user?.positionTitle || 'Staff'}</p>
                                        {user?.office?.officeName && (
                                            <p className="text-[10px] text-slate-500 mt-0.5 truncate">{user.office.officeName}</p>
                                        )}
                                        <p className="text-[10px] text-slate-400 mt-0.5 truncate">{user?.email}</p>
                                    </div>
                                    <div className="py-1">
                                        <Dropdown.Link href={route('profile.edit')} className="flex items-center gap-2 text-xs font-semibold text-slate-700">
                                            <svg className="w-4 h-4 text-slate-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path strokeLinecap="round" strokeLinejoin="round" strokeWidth="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z" />
                                            </svg>
                                            My Profile & Settings
                                        </Dropdown.Link>
                                    </div>
                                    <div className="py-1">
                                        <Dropdown.Link
                                            href={route('logout')}
                                            method="post"
                                            as="button"
                                            className="flex items-center gap-2 text-xs font-semibold text-danger-600 hover:text-danger-700 hover:bg-danger-50"
                                        >
                                            <svg className="w-4 h-4 text-danger-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path strokeLinecap="round" strokeLinejoin="round" strokeWidth="2" d="M17 16l4-4m0 0l-4-4m4 4H7m6 4v1a3 3 0 01-3 3H6a3 3 0 01-3-3V7a3 3 0 013-3h4a3 3 0 013 3v1" />
                                            </svg>
                                            Sign Out
                                        </Dropdown.Link>
                                    </div>
                                </Dropdown.Content>
                            </Dropdown>
                        </div>
                    </div>
                </div>
            </header>

            {/* Second Tier: Context-Aware Desk Sub-Nav */}
            <DeskSubNav />

            {/* Optional Header Banner */}
            {header && (
                <div className="bg-white border-b border-slate-200/80 shadow-2xs no-print">
                    <div className="mx-auto max-w-7xl px-4 py-4 sm:px-6 lg:px-8">
                        {header}
                    </div>
                </div>
            )}

            {/* Main Content Area - Full-Width Canvas */}
            <main className="flex-1 mx-auto w-full max-w-7xl px-4 sm:px-6 lg:px-8 py-6">
                {children}
            </main>

            {/* Modals & Dialogs */}
            <MegaAppLauncher
                isOpen={isLauncherOpen}
                onClose={() => setIsLauncherOpen(false)}
                user={user}
            />

            <GlobalSearchModal
                isOpen={isSearchOpen}
                onClose={() => setIsSearchOpen(false)}
            />

            {/* Toast Notifications */}
            <Toast />
        </div>
    );
}
