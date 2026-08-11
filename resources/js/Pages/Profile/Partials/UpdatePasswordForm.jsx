import { Transition } from '@headlessui/react';
import { useForm } from '@inertiajs/react';
import { useRef } from 'react';
import { Card, FormSection } from '@/Components/ui';

export default function UpdatePasswordForm({ className = '' }) {
    const passwordInput = useRef();
    const currentPasswordInput = useRef();

    const {
        data,
        setData,
        errors,
        put,
        reset,
        processing,
        recentlySuccessful,
    } = useForm({
        current_password: '',
        password: '',
        password_confirmation: '',
    });

    const updatePassword = (e) => {
        e.preventDefault();

        put(route('password.update'), {
            preserveScroll: true,
            onSuccess: () => reset(),
            onError: (errors) => {
                if (errors.password) {
                    reset('password', 'password_confirmation');
                    passwordInput.current.focus();
                }

                if (errors.current_password) {
                    reset('current_password');
                    currentPasswordInput.current.focus();
                }
            },
        });
    };

    return (
        <Card title="Update Password" subtitle="Ensure your account is using a long, random password to stay secure" className={className}>
            <form onSubmit={updatePassword} className="space-y-6">
                <FormSection label="Current Password" required>
                    <input
                        id="current_password"
                        ref={currentPasswordInput}
                        value={data.current_password}
                        onChange={(e) =>
                            setData('current_password', e.target.value)
                        }
                        type="password"
                        className="form-input"
                        autoComplete="current-password"
                    />
                    {errors.current_password && (
                        <p className="form-error">{errors.current_password}</p>
                    )}
                </FormSection>

                <FormSection label="New Password" required>
                    <input
                        id="password"
                        ref={passwordInput}
                        value={data.password}
                        onChange={(e) => setData('password', e.target.value)}
                        type="password"
                        className="form-input"
                        autoComplete="new-password"
                    />
                    {errors.password && (
                        <p className="form-error">{errors.password}</p>
                    )}
                </FormSection>

                <FormSection label="Confirm Password" required>
                    <input
                        id="password_confirmation"
                        value={data.password_confirmation}
                        onChange={(e) =>
                            setData('password_confirmation', e.target.value)
                        }
                        type="password"
                        className="form-input"
                        autoComplete="new-password"
                    />
                    {errors.password_confirmation && (
                        <p className="form-error">{errors.password_confirmation}</p>
                    )}
                </FormSection>

                <div className="flex items-center gap-4">
                    <button type="submit" className="btn btn-primary" disabled={processing}>
                        Save
                    </button>

                    <Transition
                        show={recentlySuccessful}
                        enter="transition ease-in-out"
                        enterFrom="opacity-0"
                        leave="transition ease-in-out"
                        leaveTo="opacity-0"
                    >
                        <p className="text-sm text-success-700 flex items-center gap-1">
                            <svg className="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path strokeLinecap="round" strokeLinejoin="round" strokeWidth="2" d="M5 13l4 4L19 7" />
                            </svg>
                            Saved.
                        </p>
                    </Transition>
                </div>
            </form>
        </Card>
    );
}
