import ApplicationLogo from '@/Components/ApplicationLogo';
import { Link } from '@inertiajs/react';

export default function GuestLayout({ children }) {
    return (
        <div className="relative min-h-screen overflow-hidden bg-navy-950 font-sans text-navy-100">
            {/* Ambient gradient mesh */}
            <div
                aria-hidden="true"
                className="pointer-events-none absolute inset-0 bg-gradient-to-b from-navy-900 via-navy-950 to-navy-950"
            />
            {/* Orange glow accents */}
            <div
                aria-hidden="true"
                className="pointer-events-none absolute -top-32 -left-24 h-[28rem] w-[28rem] rounded-full bg-seait-500/20 blur-[120px]"
            />
            <div
                aria-hidden="true"
                className="pointer-events-none absolute top-1/3 -right-24 h-[24rem] w-[24rem] rounded-full bg-seait-600/15 blur-[120px]"
            />
            <div
                aria-hidden="true"
                className="pointer-events-none absolute -bottom-32 left-1/4 h-[22rem] w-[22rem] rounded-full bg-navy-700/40 blur-[100px]"
            />
            {/* Subtle grid overlay for depth */}
            <div
                aria-hidden="true"
                className="pointer-events-none absolute inset-0 opacity-[0.04]"
                style={{
                    backgroundImage:
                        'linear-gradient(to right, #fff 1px, transparent 1px), linear-gradient(to bottom, #fff 1px, transparent 1px)',
                    backgroundSize: '56px 56px',
                }}
            />

            <div className="relative flex min-h-screen flex-col items-center justify-center px-4 py-10 sm:px-6">
                {/* Brand header */}
                <Link
                    href="/"
                    className="group mb-8 flex flex-col items-center gap-4 text-center focus:outline-none"
                >
                    <span className="flex h-20 w-20 items-center justify-center rounded-full bg-white/10 ring-1 ring-white/20 backdrop-blur-sm transition-transform duration-300 group-hover:scale-105">
                        <ApplicationLogo className="h-14 w-14 rounded-full object-cover" />
                    </span>
                    <span className="flex flex-col">
                        <span className="font-heading text-3xl font-bold tracking-tight text-white">
                            SEAIT
                        </span>
                        <span className="mt-0.5 text-sm font-medium uppercase tracking-[0.18em] text-seait-300">
                            Enrollment Management System
                        </span>
                    </span>
                </Link>

                {/* Glassmorphism card */}
                <div className="w-full max-w-md rounded-card border border-white/40 bg-white/95 p-6 shadow-card-lg backdrop-blur-md sm:p-8 animate-slide-up">
                    {children}
                </div>

                {/* Footer */}
                <footer className="mt-8 text-center text-xs text-navy-400">
                    <p>
                        &copy; {new Date().getFullYear()} SEAIT. All rights
                        reserved.
                    </p>
                </footer>
            </div>
        </div>
    );
}
