import { Transition } from '@headlessui/react';
import { useForm, usePage } from '@inertiajs/react';
import { Card, FormSection } from '@/Components/ui';

export default function UpdateProfileInformation({
    className = '',
}) {
    const user = usePage().props.auth.user;

    const { data, setData, patch, errors, processing, recentlySuccessful } =
        useForm({
            name: user.name,
            email: user.email,
        });

    const submit = (e) => {
        e.preventDefault();

        patch(route('profile.update'));
    };

    return (
        <Card title="Profile Information" subtitle="Update your account's profile information and email address" className={className}>
            <form onSubmit={submit} className="space-y-6">
                <FormSection label="Name" required>
                    <input
                        id="name"
                        type="text"
                        className="form-input"
                        value={data.name}
                        onChange={(e) => setData('name', e.target.value)}
                        required
                        autoFocus
                        autoComplete="name"
                    />
                    {errors.name && <p className="form-error">{errors.name}</p>}
                </FormSection>

                <FormSection label="Email" required>
                    <input
                        id="email"
                        type="email"
                        className="form-input"
                        value={data.email}
                        onChange={(e) => setData('email', e.target.value)}
                        required
                        autoComplete="username"
                    />
                    {errors.email && <p className="form-error">{errors.email}</p>}
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
