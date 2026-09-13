import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { Head } from '@inertiajs/react';
import { PageHeader } from '@/Components/ui';
import DeleteUserForm from './Partials/DeleteUserForm';
import UpdatePasswordForm from './Partials/UpdatePasswordForm';
import UpdateProfileInformationForm from './Partials/UpdateProfileInformationForm';

export default function Edit() {
    return (
        <AuthenticatedLayout
            header={
                <PageHeader
                    title="Profile"
                    subtitle="Manage your account information and security settings"
                    logo="/images/logos/seait-logo.png"
                    logoAlt="SEAIT logo"
                />
            }
        >
            <Head title="Profile" />

            <div className="space-y-6">
                <UpdateProfileInformationForm />

                <UpdatePasswordForm />

                <DeleteUserForm />
            </div>
        </AuthenticatedLayout>
    );
}
