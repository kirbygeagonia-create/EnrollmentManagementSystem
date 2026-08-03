import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { Head } from '@inertiajs/react';
import { useForm } from '@inertiajs/react';
import { useState, useMemo } from 'react';
import { PageHeader, Card, DataTable, Modal, EmptyState, FormSection } from '@/Components/ui';
import PrimaryButton from '@/Components/PrimaryButton';

export default function Settings({ settings }) {
    const [editingSetting, setEditingSetting] = useState(null);

    const editForm = useForm({
        settingValue: '',
    });

    const columns = useMemo(() => [
        { key: 'settingKey', label: 'Key', className: 'font-mono text-sm' },
        { key: 'settingValue', label: 'Value', render: (row) => (
            <div className="max-w-xs truncate" title={row.settingValue}>
                {row.settingValue !== null && row.settingValue !== undefined ? String(row.settingValue) : <span className="text-brand-400 italic">(empty)</span>}
            </div>
        )},
        { key: 'description', label: 'Description', render: (row) => row.description || '—' },
    ], []);

    const openEditModal = (setting) => {
        editForm.reset({
            settingValue: setting.settingValue !== null && setting.settingValue !== undefined ? String(setting.settingValue) : '',
        });
        setEditingSetting(setting);
    };

    const closeEditModal = () => {
        editForm.reset();
        setEditingSetting(null);
    };

    const handleEdit = (e) => {
        e.preventDefault();
        if (editingSetting) {
            editForm.patch(route('admin.users.settings.update', { setting: editingSetting.settingKey }), {
                onSuccess: () => closeEditModal(),
            });
        }
    };

    const renderActions = (row) => (
        <div className="flex items-center gap-1">
            <button
                onClick={() => openEditModal(row)}
                className="btn btn-ghost btn-sm text-brand-600 hover:text-brand-900"
                title="Edit"
            >
                <svg className="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path strokeLinecap="round" strokeLinejoin="round" strokeWidth="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z" />
                </svg>
            </button>
        </div>
    );

    return (
        <AuthenticatedLayout
            header={
                <PageHeader
                    title="System Settings"
                    subtitle="Manage application configuration settings"
                />
            }
        >
            <Head title="System Settings" />

            {/* Data Table */}
            <Card>
                {settings?.length > 0 ? (
                    <DataTable
                        columns={columns}
                        rows={settings}
                        children={renderActions}
                        emptyMessage="No settings found"
                    />
                ) : (
                    <EmptyState
                        title="No settings found"
                        message="No system settings have been configured."
                    />
                )}
            </Card>

            {/* Edit Modal */}
            <Modal show={!!editingSetting} onClose={closeEditModal} title={`Edit Setting: ${editingSetting?.settingKey || ''}`} size="md">
                <form onSubmit={handleEdit} className="space-y-4">
                    <FormSection label="Key" className="mb-4">
                        <div className="form-input bg-brand-50 text-brand-700 font-mono text-sm cursor-not-allowed" style={{ userSelect: 'all' }}>
                            {editingSetting?.settingKey}
                        </div>
                    </FormSection>
                    <FormSection label="Description" className="mb-4">
                        <div className="form-input bg-brand-50 text-brand-600 cursor-not-allowed">
                            {editingSetting?.description || '—'}
                        </div>
                    </FormSection>
                    <FormSection label="Value" error={editForm.errors.settingValue} required>
                        <textarea
                            value={editForm.data.settingValue}
                            onChange={(e) => editForm.setData('settingValue', e.target.value)}
                            className="form-input"
                            rows={4}
                            required
                        />
                    </FormSection>
                    <div className="flex justify-end gap-3 pt-4 border-t border-brand-100">
                        <button type="button" onClick={closeEditModal} className="btn btn-secondary" disabled={editForm.processing}>
                            Cancel
                        </button>
                        <PrimaryButton type="submit" disabled={editForm.processing}>
                            {editForm.processing ? 'Saving...' : 'Save Changes'}
                        </PrimaryButton>
                    </div>
                </form>
            </Modal>
        </AuthenticatedLayout>
    );
}