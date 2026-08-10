import InputError from '@/Components/InputError';
import GuestLayout from '@/Layouts/GuestLayout';
import { Head, Link, useForm } from '@inertiajs/react';

export default function ForgotPassword({ status }) {
    const { data, setData, post, processing, errors } = useForm({
        email: '',
    });

    const submit = (e) => {
        e.preventDefault();

        post(route('password.email'));
    };

    return (
        <GuestLayout>
            <Head title="Forgot Password" />

            <div className="mb-6">
                <h1 className="font-heading text-2xl font-bold tracking-tight text-brand-900">
                    Forgot password?
                </h1>
                <p className="mt-1 text-sm text-brand-500">
                    No problem — we'll send you a reset link.
                </p>
            </div>

            <div className="mb-5 rounded-lg border border-brand-200 bg-brand-50 px-4 py-3 text-sm text-brand-600">
                Enter your email address and we will email you a password
                reset link that will allow you to choose a new one.
            </div>

            {status && (
                <div className="mb-4 rounded-lg border border-success-200 bg-success-50 px-4 py-3 text-sm font-medium text-success-700">
                    {status}
                </div>
            )}

            <form onSubmit={submit} className="space-y-4">
                <div>
                    <label htmlFor="email" className="form-label">
                        Email
                    </label>
                    <input
                        id="email"
                        type="email"
                        name="email"
                        value={data.email}
                        autoFocus
                        onChange={(e) => setData('email', e.target.value)}
                        className={`form-input ${
                            errors.email ? 'form-input-error' : ''
                        }`}
                        placeholder="you@example.com"
                    />
                    <InputError message={errors.email} className="mt-1.5" />
                </div>

                <button
                    type="submit"
                    disabled={processing}
                    className="btn-primary btn-lg w-full justify-center"
                >
                    {processing ? (
                        <>
                            <svg
                                className="h-5 w-5 animate-spin"
                                xmlns="http://www.w3.org/2000/svg"
                                fill="none"
                                viewBox="0 0 24 24"
                                aria-hidden="true"
                            >
                                <circle
                                    className="opacity-25"
                                    cx="12"
                                    cy="12"
                                    r="10"
                                    stroke="currentColor"
                                    strokeWidth="4"
                                />
                                <path
                                    className="opacity-75"
                                    fill="currentColor"
                                    d="M4 12a8 8 0 0 1 8-8V0C5.373 0 0 5.373 0 12h4Z"
                                />
                            </svg>
                            Sending…
                        </>
                    ) : (
                        'Email password reset link'
                    )}
                </button>

                <p className="text-center text-sm text-brand-500">
                    Remember your password?{' '}
                    <Link
                        href={route('login')}
                        className="font-medium text-seait-600 hover:text-seait-700 focus:outline-none focus-visible:ring-2 focus-visible:ring-seait-500 focus-visible:ring-offset-2"
                    >
                        Back to sign in
                    </Link>
                </p>
            </form>
        </GuestLayout>
    );
}
