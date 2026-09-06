import ApplicationLogo from '@/Components/ApplicationLogo';
import { Link } from '@inertiajs/react';

export default function GuestLayout({ children }) {
    return (
        <div className="relative min-h-screen overflow-hidden bg-navy-950 font-sans text-navy-100 selection:bg-seait-500 selection:text-white">
            {/* Real SEAIT Campus Photo — front facade with arch and Mt. Matutum backdrop */}
            <div
                aria-hidden="true"
                className="pointer-events-none absolute inset-0 bg-cover bg-center bg-no-repeat transform scale-105 transition-all duration-1000"
                style={{
                    backgroundImage: "url('/images/seait_front_facade.jpg')",
                    filter: 'blur(1.5px) brightness(0.40) saturate(1.15)',
                }}
            />

            {/* University Deep Navy & Institutional Overlay */}
            <div
                aria-hidden="true"
                className="pointer-events-none absolute inset-0 bg-gradient-to-tr from-navy-950/85 via-navy-900/55 to-slate-950/75 backdrop-blur-[0.5px]"
            />

            {/* Ambient Brand Glow Accents */}
            <div
                aria-hidden="true"
                className="pointer-events-none absolute -top-32 -left-24 h-[28rem] w-[28rem] rounded-full bg-seait-500/20 blur-[130px]"
            />
            <div
                aria-hidden="true"
                className="pointer-events-none absolute top-1/3 -right-24 h-[24rem] w-[24rem] rounded-full bg-seait-600/15 blur-[120px]"
            />
            <div
                aria-hidden="true"
                className="pointer-events-none absolute -bottom-32 left-1/4 h-[22rem] w-[22rem] rounded-full bg-navy-700/40 blur-[100px]"
            />



            <div className="relative flex min-h-screen flex-col items-center justify-center px-4 py-10 sm:px-6 z-10">
                {/* Brand header with seal and university identity */}
                <Link
                    href="/"
                    className="group mb-7 flex flex-col items-center gap-3.5 text-center focus:outline-none"
                >
                    <div className="relative flex h-20 w-20 items-center justify-center rounded-2xl bg-white/10 p-1.5 ring-1 ring-white/30 backdrop-blur-md shadow-2xl transition-all duration-300 group-hover:scale-105 group-hover:ring-seait-400/60 group-hover:bg-white/15">
                        <ApplicationLogo className="h-16 w-16 rounded-xl object-contain drop-shadow-md" />
                    </div>
                    <div className="flex flex-col items-center">
                        <span className="font-heading text-2xl sm:text-3xl font-extrabold tracking-tight text-white drop-shadow-sm">
                            South East Asian Institute of Technology
                        </span>
                        <span className="mt-1 inline-flex items-center gap-2 text-xs sm:text-sm font-semibold uppercase tracking-[0.2em] text-seait-300">
                            <span className="h-1 w-1 rounded-full bg-seait-400" />
                            Enrollment Management System
                            <span className="h-1 w-1 rounded-full bg-seait-400" />
                        </span>
                    </div>
                </Link>

                {/* Premium Glassmorphism Card */}
                <div className="w-full max-w-md rounded-2xl border border-white/40 bg-white/95 p-6 sm:p-8 shadow-[0_20px_50px_rgba(0,0,0,0.4)] backdrop-blur-xl animate-slide-up transition-all">
                    {children}
                </div>

                {/* Institutional Footer */}
                <footer className="mt-8 text-center text-xs text-slate-300/80 drop-shadow">
                    <p className="font-medium">
                        &copy; {new Date().getFullYear()} South East Asian Institute of Technology, Inc.
                    </p>
                    <p className="mt-0.5 text-[11px] text-slate-400/90">
                        Tupi, South Cotabato &bull; Providing Quality and Free Education
                    </p>
                </footer>
            </div>
        </div>
    );
}
