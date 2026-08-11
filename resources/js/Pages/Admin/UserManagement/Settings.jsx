import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { Head } from '@inertiajs/react';
import { useForm } from '@inertiajs/react';
import { useState, useMemo } from 'react';
import { PageHeader, Card, DataTable, Modal, EmptyState, FormSection } from '@/Components/ui';

export default function Settings({ settings }) {
    const [editingSetting, setEditingSetting] = useState(null);

    const editForm = useForm({
        settingValue: '',
    });

    const columns = useMemo(() => [
        { key: 'settingKey', label: 'Key', render: (row) => (
            <span className="font-mono text-sm text-brand-800 font-medium">{row.settingKey}</span>
        )},
        { key: 'settingValue', label: 'Value', render: (row) => (
            <div className="max-w-xs truncate" title={row.settingValue}>
                {row.settingValue !== null && row.settingValue !== undefined ? (
                    <span className="text-brand-700">{String(row.settingValue)}</span>
                ) : (
                    <span className="text-brand-400 italic">(empty)</span>
                )}
            </div>
        )},
        { key: 'description', label: 'Description', render: (row) => (
            <span className="text-sm text-brand-500">{row.description || '—'}</span>
        )},
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
                    subtitle="Manage application configuration values"
                    logo="/images/logos/seait-logo.png"
                    logoAlt="SEAIT Logo"
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
            <Modal
                show={!!editingSetting}
                onClose={closeEditModal}
                title="Edit Setting"
                subtitle={editingSetting?.settingKey || ''}
                icon={
                    <svg className="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path strokeLinecap="round" strokeLinejoin="round" strokeWidth="2" d="M10.325 4.317c.426-1.756 2.924-1.756 3.35 0a1.724 1.724 0 002.573 1.066c1.543-.94 3.31.826 2.37 2.37a1.724 1.724 0 001.065 2.572c1.756.426 1.756 2.924 0 3.35a1.724 1.724 0 00-1.066 2.573c.94 1.543-.826 3.31-2.37 2.37a1.724 1.724 0 00-2.572 1.065c-.426 1.756-2.924 1.756-3.35 0a1.724 1.724 0 00-2.573-1.066c-1.543.94-3.31-.826-2.37-2.37a1.724 1.724 0 00-1.065-2.572c-1.756-.426-1.756-2.924 0-3.35a1.724 1.724 0 001.066-2.573c-.94-1.543.826-3.31 2.37-2.37.996.608 2.296.07 2.572-1.065z" />
                        <path strokeLinecap="round" strokeLinejoin="round" strokeWidth="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" />
                    </svg>
                }
                size="md"
                footer={
                    <div className="flex justify-end gap-3">
                        <button type="button" onClick={closeEditModal} className="btn btn-secondary" disabled={editForm.processing}>
                            Cancel
                        </button>
                        <button type="submit" form="edit-setting-form" className="btn btn-primary" disabled={editForm.processing}>
                            {editForm.processing ? 'Saving...' : 'Save Changes'}
                        </button>
                    </div>
                }
            >
                <form id="edit-setting-form" onSubmit={handleEdit} className="space-y-4">
                    <FormSection label="Key">
                        <div className="form-input bg-brand-50 text-brand-700 font-mono text-sm cursor-not-allowed" style={{ userSelect: 'all' }}>
                            {editingSetting?.settingKey}
                        </div>
                    </FormSection>
                    <FormSection label="Description">
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
                </form>
            </Modal>
        </AuthenticatedLayout>
    );
}