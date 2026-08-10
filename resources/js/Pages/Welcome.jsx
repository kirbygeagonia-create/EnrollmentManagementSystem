import ApplicationLogo from '@/Components/ApplicationLogo';
import { Head, Link } from '@inertiajs/react';

const OFFICES = [
    'Registrar',
    'Evaluation',
    'Blocking',
    'Assessment',
    'Accounting',
    'Clinic',
    'Admission',
    'Clearance',
    'ID',
    'Exam',
];

export default function Welcome({ auth, laravelVersion, phpVersion }) {
    return (
        <>
            <Head title="Welcome" />
            <div className="relative min-h-screen overflow-hidden bg-navy-950 font-sans text-navy-100">
                {/* Ambient gradient mesh */}
                <div
                    aria-hidden="true"
                    className="pointer-events-none absolute inset-0 bg-gradient-to-b from-navy-900 via-navy-950 to-navy-950"
                />
                {/* Orange glow accents */}
                <div
                    aria-hidden="true"
                    className="pointer-events-none absolute -top-40 left-1/2 h-[34rem] w-[34rem] -translate-x-1/2 rounded-full bg-seait-500/20 blur-[140px]"
                />
                <div
                    aria-hidden="true"
                    className="pointer-events-none absolute top-1/2 -left-32 h-[26rem] w-[26rem] rounded-full bg-seait-600/15 blur-[120px]"
                />
                <div
                    aria-hidden="true"
                    className="pointer-events-none absolute -bottom-32 -right-24 h-[28rem] w-[28rem] rounded-full bg-navy-700/40 blur-[120px]"
                />
                {/* Subtle grid overlay */}
                <div
                    aria-hidden="true"
                    className="pointer-events-none absolute inset-0 opacity-[0.04]"
                    style={{
                        backgroundImage:
                            'linear-gradient(to right, #fff 1px, transparent 1px), linear-gradient(to bottom, #fff 1px, transparent 1px)',
                        backgroundSize: '56px 56px',
                    }}
                />

                <div className="relative mx-auto flex min-h-screen max-w-5xl flex-col px-6 py-10">
                    {/* Top bar */}
                    <header className="flex items-center justify-between">
                        <div className="flex items-center gap-3">
                            <span className="flex h-11 w-11 items-center justify-center rounded-full bg-white/10 ring-1 ring-white/20 backdrop-blur-sm">
                                <ApplicationLogo className="h-8 w-8 rounded-full object-cover" />
                            </span>
                            <span className="font-heading text-lg font-bold tracking-tight text-white">
                                SEAIT
                            </span>
                        </div>

                        <nav className="-mx-3 flex items-center gap-1">
                            {auth.user ? (
                                <Link
                                    href={route('dashboard')}
                                    className="rounded-btn px-4 py-2 text-sm font-medium text-navy-200 transition-colors hover:bg-white/10 hover:text-white focus:outline-none focus-visible:ring-2 focus-visible:ring-seait-500 focus-visible:ring-offset-2 focus-visible:ring-offset-navy-950"
                                >
                                    Dashboard
                                </Link>
                            ) : (
                                <>
                                    <Link
                                        href={route('login')}
                                        className="rounded-btn px-4 py-2 text-sm font-medium text-navy-200 transition-colors hover:bg-white/10 hover:text-white focus:outline-none focus-visible:ring-2 focus-visible:ring-seait-500 focus-visible:ring-offset-2 focus-visible:ring-offset-navy-950"
                                    >
                                        Sign in
                                    </Link>
                                    <Link
                                        href={route('register')}
                                        className="rounded-btn bg-white/5 px-4 py-2 text-sm font-medium text-white ring-1 ring-white/15 transition-colors hover:bg-white/10 focus:outline-none focus-visible:ring-2 focus-visible:ring-seait-500 focus-visible:ring-offset-2 focus-visible:ring-offset-navy-950"
                                    >
                                        Register
                                    </Link>
                                </>
                            )}
                        </nav>
                    </header>

                    {/* Hero */}
                    <main className="flex flex-1 flex-col items-center justify-center text-center">
                        <div className="animate-slide-up">
                            <span className="mx-auto flex h-24 w-24 items-center justify-center rounded-full bg-white/10 ring-1 ring-white/20 backdrop-blur-sm">
                                <ApplicationLogo className="h-16 w-16 rounded-full object-cover" />
                            </span>
                        </div>

                        <h1 className="mt-6 animate-slide-up font-heading text-4xl font-bold tracking-tight text-white sm:text-5xl md:text-6xl">
                            SEAIT
                        </h1>
                        <p className="mt-3 animate-slide-up text-sm font-medium uppercase tracking-[0.22em] text-seait-300 sm:text-base">
                            Enrollment Management System
                        </p>
                        <p className="mx-auto mt-6 max-w-xl animate-slide-up text-base leading-relaxed text-navy-200 sm:text-lg">
                            A streamlined enrollment workflow connecting
                            students with every office — from evaluation
                            and blocking to assessment, clearance, and
                            final registration.
                        </p>

                        <div className="mt-9 flex animate-slide-up flex-col items-center gap-3 sm:flex-row">
                            <Link
                                href={route('login')}
                                className="btn-primary btn-lg w-full justify-center sm:w-auto"
                            >
                                Sign in
                                <svg
                                    className="h-5 w-5"
                                    xmlns="http://www.w3.org/2000/svg"
                                    fill="none"
                                    viewBox="0 0 24 24"
                                    strokeWidth="2"
                                    stroke="currentColor"
                                    aria-hidden="true"
                                >
                                    <path
                                        strokeLinecap="round"
                                        strokeLinejoin="round"
                                        d="M13.5 4.5 21 12m0 0-7.5 7.5M21 12H3"
                                    />
                                </svg>
                            </Link>
                            <Link
                                href={route('register')}
                                className="btn btn-lg w-full justify-center bg-white/5 text-white ring-1 ring-white/15 hover:bg-white/10 focus:ring-white/40 sm:w-auto"
                            >
                                Create account
                            </Link>
                        </div>

                        {/* Office chips */}
                        <div className="mt-14 w-full animate-slide-up">
                            <p className="text-xs font-semibold uppercase tracking-[0.18em] text-navy-400">
                                Enrollment Offices
                            </p>
                            <div className="mt-4 flex flex-wrap items-center justify-center gap-2.5">
                                {OFFICES.map((office) => (
                                    <span
                                        key={office}
                                        className="inline-flex items-center gap-1.5 rounded-badge border border-white/10 bg-white/5 px-3.5 py-1.5 text-sm text-navy-100 backdrop-blur-sm"
                                    >
                                        <span className="h-1.5 w-1.5 rounded-full bg-seait-500" />
                                        {office}
                                    </span>
                                ))}
                            </div>
                        </div>
                    </main>

                    {/* Footer */}
                    <footer className="mt-10 text-center text-xs text-navy-400">
                        <p>
                            &copy; {new Date().getFullYear()} SEAIT. All
                            rights reserved.
                        </p>
                        <p className="mt-1">
                            Laravel v{laravelVersion} (PHP v{phpVersion})
                        </p>
                    </footer>
                </div>
            </div>
        </>
    );
}
