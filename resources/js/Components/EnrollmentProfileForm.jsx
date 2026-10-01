import { useForm } from '@inertiajs/react';
import { useState } from 'react';
import { Badge, FormSection, Select } from '@/Components/ui';

const GENDER_OPTIONS = [
    { value: 'male', label: 'Male' },
    { value: 'female', label: 'Female' },
];

const CIVIL_STATUS_OPTIONS = [
    { value: 'single', label: 'Single' },
    { value: 'married', label: 'Married' },
    { value: 'widowed', label: 'Widowed' },
    { value: 'separated', label: 'Separated' },
];

const ADDRESS_TYPE_OPTIONS = [
    { value: 'home', label: 'Home' },
    { value: 'current', label: 'Current' },
    { value: 'permanent', label: 'Permanent' },
];

const RELATIONSHIP_OPTIONS = [
    { value: 'mother', label: 'Mother' },
    { value: 'father', label: 'Father' },
    { value: 'guardian', label: 'Guardian' },
    { value: 'other', label: 'Other' },
];

const PERSON_INPUTS = [
    { key: 'lastName', label: 'Last Name', required: true, maxLength: 100 },
    { key: 'firstName', label: 'First Name', required: true, maxLength: 100 },
    { key: 'middleName', label: 'Middle Name', maxLength: 100 },
    { key: 'suffix', label: 'Suffix', maxLength: 20 },
    { key: 'birthplace', label: 'Birthplace', required: true, maxLength: 255 },
    { key: 'citizenship', label: 'Citizenship', required: true, maxLength: 100 },
    { key: 'contactNumber', label: 'Contact Number', required: true, maxLength: 20 },
    { key: 'telephoneNumber', label: 'Telephone Number', maxLength: 20 },
    { key: 'email', label: 'Email', required: true, type: 'email', maxLength: 255 },
];

const ADDRESS_INPUTS = [
    { key: 'houseBuildingNo', label: 'House / Building No.', maxLength: 100 },
    { key: 'street', label: 'Street', maxLength: 255 },
    { key: 'sitioPurok', label: 'Sitio / Purok', maxLength: 100 },
    { key: 'barangay', label: 'Barangay', required: true, maxLength: 100 },
    { key: 'cityMunicipality', label: 'City / Municipality', required: true, maxLength: 100 },
    { key: 'district', label: 'District', maxLength: 100 },
    { key: 'province', label: 'Province', required: true, maxLength: 100 },
    { key: 'region', label: 'Region', maxLength: 100 },
    { key: 'zipCode', label: 'ZIP Code', maxLength: 20 },
    { key: 'country', label: 'Country', required: true, maxLength: 100 },
];

const GUARDIAN_INPUTS = [
    { key: 'fullName', label: 'Full Name', required: true, maxLength: 255 },
    { key: 'contactNumber', label: 'Contact Number', required: true, maxLength: 20 },
    { key: 'email', label: 'Email', type: 'email', maxLength: 255 },
];

const blankAddress = (addressType = 'home') => ({
    addressType,
    houseBuildingNo: '',
    street: '',
    sitioPurok: '',
    barangay: '',
    cityMunicipality: '',
    district: '',
    province: '',
    region: '',
    zipCode: '',
    country: 'Philippines',
});

const blankGuardian = () => ({
    relationship: 'mother',
    fullName: '',
    contactNumber: '',
    email: '',
    isEmergencyContact: false,
    isAuthorizedToActOnBehalf: false,
});

const dateOnly = (value) => (value ? String(value).slice(0, 10) : '');

const oneLineAddress = (addr) =>
    [addr.houseBuildingNo, addr.street, addr.sitioPurok, addr.barangay, addr.cityMunicipality, addr.province, addr.zipCode, addr.country]
        .filter(Boolean)
        .join(', ');

const inputClass = 'w-full rounded-xl border-slate-300 text-sm focus:border-indigo-500 focus:ring-indigo-500';

function FieldGrid({ fields, row, rowIndex, prefix, errors, onChange }) {
    return (
        <div className="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-4">
            {fields.map((field) => (
                <FormSection
                    key={field.key}
                    label={field.label}
                    required={field.required}
                    error={errors[`${prefix}.${rowIndex}.${field.key}`]}
                >
                    <input
                        type={field.type || 'text'}
                        value={row[field.key] ?? ''}
                        onChange={(e) => onChange(rowIndex, field.key, e.target.value)}
                        maxLength={field.maxLength}
                        className={inputClass}
                    />
                </FormSection>
            ))}
        </div>
    );
}

export default function EnrollmentProfileForm({ enrollment, religions = [], academicStandings = [], gaps = [], canCapture = false, startOpen = false }) {
    const student = enrollment.student || {};
    const [editing, setEditing] = useState(startOpen);

    const [addresses, setAddresses] = useState(() => {
        const rows = (student.addresses || []).map((a) => ({ ...blankAddress(), ...a }));
        return rows.length > 0 ? rows : [blankAddress('home'), blankAddress('current')];
    });

    const [guardians, setGuardians] = useState(() => {
        const rows = (student.guardians || []).map((g) => ({ ...blankGuardian(), ...g }));
        return rows.length > 0 ? rows : [blankGuardian()];
    });

    const form = useForm({
        lastName: student.lastName || '',
        firstName: student.firstName || '',
        middleName: student.middleName || '',
        suffix: student.suffix || '',
        gender: student.gender || '',
        birthdate: dateOnly(student.birthdate),
        birthplace: student.birthplace || '',
        citizenship: student.citizenship || '',
        religionId: student.religionId || '',
        civilStatus: student.civilStatus || '',
        contactNumber: student.contactNumber || '',
        telephoneNumber: student.telephoneNumber || '',
        email: student.email || '',
        semestersCompleted: student.semestersCompleted ?? 0,
        yearsInInstitution: student.yearsInInstitution ?? 0,
        academicStanding: enrollment.academicStanding || '',
        formIssuedDate: dateOnly(enrollment.formIssuedDate),
    });

    const fullName = student.lastName || student.firstName
        ? `${student.lastName || ''}, ${student.firstName || ''}${student.middleName ? ` ${student.middleName}` : ''}${student.suffix ? ` ${student.suffix}` : ''}`
        : '';

    const religionName = (religions.find((r) => String(r.religionId) === String(student.religionId)) || {}).religionName;

    const summaryRow = (label, value) => (
        <div key={label}>
            <dt className="text-[10px] font-bold uppercase tracking-wider text-slate-400">{label}</dt>
            <dd className="mt-0.5 text-sm font-semibold text-slate-800">
                {value === null || value === undefined || value === '' ? '—' : value}
            </dd>
        </div>
    );

    const cancelEditing = () => {
        form.clearErrors();
        setEditing(false);
    };

    const submit = (e) => {
        e.preventDefault();
        form.transform((data) => ({ ...data, addresses, guardians })).put(
            route('evaluation.profile.capture', { enrollment: enrollment.enrollmentId }),
            {
                preserveScroll: true,
                onSuccess: () => setEditing(false),
            },
        );
    };

    const updateRow = (setter) => (index, key, value) => {
        setter((prev) => prev.map((row, i) => (i === index ? { ...row, [key]: value } : row)));
    };

    return (
        <div className="mb-8 rounded-2xl border border-slate-200 bg-white p-5 shadow-xs">
            <div className="mb-4 flex flex-wrap items-center justify-between gap-3 border-b border-slate-100 pb-3">
                <div>
                    <h3 className="flex items-center gap-2 font-heading text-sm font-bold text-slate-900">
                        <span className="h-2.5 w-2.5 rounded-full bg-cyan-600" />
                        Demographic Enrollment Profile
                    </h3>
                    <p className="mt-0.5 text-xs text-slate-400">
                        BR32 — every field must be captured on this desk before the evaluation can be forwarded.
                    </p>
                </div>
                <div className="flex items-center gap-2">
                    <Badge tone={gaps.length === 0 ? 'success' : 'warning'}>
                        {gaps.length === 0 ? 'Complete' : `${gaps.length} missing`}
                    </Badge>
                    {canCapture && !editing && (
                        <button
                            type="button"
                            onClick={() => setEditing(true)}
                            className="rounded-xl border border-slate-300 px-3 py-1.5 text-xs font-bold text-slate-700 transition hover:border-indigo-400 hover:bg-indigo-50"
                        >
                            {gaps.length === 0 ? 'Edit Profile' : 'Capture Profile'}
                        </button>
                    )}
                </div>
            </div>

            {gaps.length > 0 && !editing && (
                <div className="mb-4 rounded-xl border border-amber-300 bg-amber-50 px-4 py-3">
                    <p className="text-xs font-bold text-amber-900">
                        The evaluation cannot be signed until these are filled:
                    </p>
                    <p className="mt-1 text-[11px] leading-relaxed text-amber-800">{gaps.join(' · ')}</p>
                </div>
            )}

            {!editing ? (
                <div className="space-y-4">
                    <dl className="grid grid-cols-2 gap-4 sm:grid-cols-3 lg:grid-cols-4">
                        {summaryRow('Name', fullName)}
                        {summaryRow('School ID', student.schoolIdNumber)}
                        {summaryRow('Gender', student.gender)}
                        {summaryRow('Birthdate', dateOnly(student.birthdate))}
                        {summaryRow('Birthplace', student.birthplace)}
                        {summaryRow('Citizenship', student.citizenship)}
                        {summaryRow('Religion', religionName)}
                        {summaryRow('Civil Status', student.civilStatus)}
                        {summaryRow('Contact Number', student.contactNumber)}
                        {summaryRow('Telephone', student.telephoneNumber)}
                        {summaryRow('Email', student.email)}
                        {summaryRow('Semesters Completed', student.semestersCompleted ?? null)}
                        {summaryRow('Years in Institution', student.yearsInInstitution ?? null)}
                        {summaryRow('Academic Standing', enrollment.academicStanding)}
                        {summaryRow('Form Issued Date', dateOnly(enrollment.formIssuedDate))}
                    </dl>

                    <div>
                        <h4 className="mb-2 text-[10px] font-bold uppercase tracking-wider text-slate-500">Addresses</h4>
                        {(student.addresses || []).length === 0 ? (
                            <p className="text-xs text-slate-400">No address on file.</p>
                        ) : (
                            <ul className="space-y-1">
                                {student.addresses.map((addr) => (
                                    <li key={addr.addressId} className="flex gap-2 text-xs text-slate-700">
                                        <span className="font-bold uppercase text-slate-500">{addr.addressType}</span>
                                        <span>{oneLineAddress(addr)}</span>
                                    </li>
                                ))}
                            </ul>
                        )}
                    </div>

                    <div>
                        <h4 className="mb-2 text-[10px] font-bold uppercase tracking-wider text-slate-500">Parents / Guardians</h4>
                        {(student.guardians || []).length === 0 ? (
                            <p className="text-xs text-slate-400">No guardian on file.</p>
                        ) : (
                            <ul className="space-y-1">
                                {student.guardians.map((g) => (
                                    <li key={g.guardianId} className="text-xs text-slate-700">
                                        <span className="font-bold uppercase text-slate-500">{g.relationship}</span>
                                        {' — '}
                                        {g.fullName} · {g.contactNumber}
                                        {g.email ? ` · ${g.email}` : ''}
                                        {g.isEmergencyContact ? ' · emergency contact' : ''}
                                        {g.isAuthorizedToActOnBehalf ? ' · authorized to act' : ''}
                                    </li>
                                ))}
                            </ul>
                        )}
                    </div>
                </div>
            ) : (
                <form onSubmit={submit} className="space-y-6">
                    <div>
                        <h4 className="mb-3 text-[10px] font-bold uppercase tracking-wider text-slate-500">Personal Information</h4>
                        <div className="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-4">
                            {PERSON_INPUTS.map((field) => (
                                <FormSection key={field.key} label={field.label} required={field.required} error={form.errors[field.key]}>
                                    <input
                                        type={field.type || 'text'}
                                        value={form.data[field.key]}
                                        onChange={(e) => form.setData(field.key, e.target.value)}
                                        maxLength={field.maxLength}
                                        className={inputClass}
                                    />
                                </FormSection>
                            ))}
                            <FormSection label="Gender" required error={form.errors.gender}>
                                <Select
                                    value={form.data.gender}
                                    onChange={(v) => form.setData('gender', v)}
                                    options={GENDER_OPTIONS}
                                    placeholder="Select gender…"
                                />
                            </FormSection>
                            <FormSection label="Birthdate" required error={form.errors.birthdate}>
                                <input
                                    type="date"
                                    value={form.data.birthdate}
                                    onChange={(e) => form.setData('birthdate', e.target.value)}
                                    max={dateOnly(new Date().toISOString())}
                                    className={inputClass}
                                />
                            </FormSection>
                            <FormSection label="Religion" required error={form.errors.religionId}>
                                <Select
                                    value={form.data.religionId}
                                    onChange={(v) => form.setData('religionId', v)}
                                    options={religions.map((r) => ({ value: r.religionId, label: r.religionName }))}
                                    placeholder="Select religion…"
                                />
                            </FormSection>
                            <FormSection label="Civil Status" required error={form.errors.civilStatus}>
                                <Select
                                    value={form.data.civilStatus}
                                    onChange={(v) => form.setData('civilStatus', v)}
                                    options={CIVIL_STATUS_OPTIONS}
                                    placeholder="Select civil status…"
                                />
                            </FormSection>
                        </div>
                    </div>

                    <div>
                        <div className="mb-3 flex items-center justify-between">
                            <h4 className="text-[10px] font-bold uppercase tracking-wider text-slate-500">
                                Addresses (home and current are both required)
                            </h4>
                            <button
                                type="button"
                                onClick={() => setAddresses((prev) => [...prev, blankAddress()])}
                                className="text-xs font-bold text-indigo-700 hover:text-indigo-900"
                            >
                                + Add address
                            </button>
                        </div>
                        <div className="space-y-4">
                            {addresses.map((addr, idx) => (
                                <div key={idx} className="rounded-xl border border-slate-200 bg-slate-50/60 p-4">
                                    <div className="mb-3 flex items-end justify-between gap-3">
                                        <FormSection label="Address Type" required error={form.errors[`addresses.${idx}.addressType`]}>
                                            <div className="w-44">
                                                <Select
                                                    value={addr.addressType}
                                                    onChange={(v) => updateRow(setAddresses)(idx, 'addressType', v)}
                                                    options={ADDRESS_TYPE_OPTIONS}
                                                />
                                            </div>
                                        </FormSection>
                                        {addresses.length > 1 && (
                                            <button
                                                type="button"
                                                onClick={() => setAddresses((prev) => prev.filter((_, i) => i !== idx))}
                                                className="text-xs font-bold text-danger-600 hover:text-danger-900"
                                            >
                                                Remove
                                            </button>
                                        )}
                                    </div>
                                    <FieldGrid
                                        fields={ADDRESS_INPUTS}
                                        row={addr}
                                        rowIndex={idx}
                                        prefix="addresses"
                                        errors={form.errors}
                                        onChange={updateRow(setAddresses)}
                                    />
                                </div>
                            ))}
                        </div>
                    </div>

                    <div>
                        <div className="mb-3 flex items-center justify-between">
                            <h4 className="text-[10px] font-bold uppercase tracking-wider text-slate-500">Parents / Guardians</h4>
                            <button
                                type="button"
                                onClick={() => setGuardians((prev) => [...prev, blankGuardian()])}
                                className="text-xs font-bold text-indigo-700 hover:text-indigo-900"
                            >
                                + Add guardian
                            </button>
                        </div>
                        <div className="space-y-4">
                            {guardians.map((g, idx) => (
                                <div key={idx} className="rounded-xl border border-slate-200 bg-slate-50/60 p-4">
                                    <div className="mb-3 flex items-end justify-between gap-3">
                                        <FormSection label="Relationship" required error={form.errors[`guardians.${idx}.relationship`]}>
                                            <div className="w-44">
                                                <Select
                                                    value={g.relationship}
                                                    onChange={(v) => updateRow(setGuardians)(idx, 'relationship', v)}
                                                    options={RELATIONSHIP_OPTIONS}
                                                />
                                            </div>
                                        </FormSection>
                                        {guardians.length > 1 && (
                                            <button
                                                type="button"
                                                onClick={() => setGuardians((prev) => prev.filter((_, i) => i !== idx))}
                                                className="text-xs font-bold text-danger-600 hover:text-danger-900"
                                            >
                                                Remove
                                            </button>
                                        )}
                                    </div>
                                    <FieldGrid
                                        fields={GUARDIAN_INPUTS}
                                        row={g}
                                        rowIndex={idx}
                                        prefix="guardians"
                                        errors={form.errors}
                                        onChange={updateRow(setGuardians)}
                                    />
                                    <div className="mt-3 flex flex-wrap gap-6">
                                        {[
                                            { key: 'isEmergencyContact', label: 'Emergency contact' },
                                            { key: 'isAuthorizedToActOnBehalf', label: 'Authorized to act on behalf' },
                                        ].map((flag) => (
                                            <label key={flag.key} className="flex items-center gap-2 text-xs font-semibold text-slate-700">
                                                <input
                                                    type="checkbox"
                                                    checked={Boolean(g[flag.key])}
                                                    onChange={(e) => updateRow(setGuardians)(idx, flag.key, e.target.checked)}
                                                    className="h-4 w-4 rounded border-slate-300 text-indigo-600 focus:ring-indigo-500"
                                                />
                                                {flag.label}
                                            </label>
                                        ))}
                                    </div>
                                </div>
                            ))}
                        </div>
                    </div>

                    <div>
                        <h4 className="mb-3 text-[10px] font-bold uppercase tracking-wider text-slate-500">Enrollment Record</h4>
                        <div className="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4">
                            <FormSection label="Semesters Completed" required error={form.errors.semestersCompleted}>
                                <input
                                    type="number"
                                    min="0"
                                    value={form.data.semestersCompleted}
                                    onChange={(e) => form.setData('semestersCompleted', e.target.value)}
                                    className={inputClass}
                                />
                            </FormSection>
                            <FormSection label="Years in Institution" required error={form.errors.yearsInInstitution}>
                                <input
                                    type="number"
                                    min="0"
                                    value={form.data.yearsInInstitution}
                                    onChange={(e) => form.setData('yearsInInstitution', e.target.value)}
                                    className={inputClass}
                                />
                            </FormSection>
                            <FormSection label="Academic Standing" required error={form.errors.academicStanding}>
                                <Select
                                    value={form.data.academicStanding}
                                    onChange={(v) => form.setData('academicStanding', v)}
                                    options={
                                        academicStandings.length > 0
                                            ? academicStandings
                                            : [
                                                { value: 'regular', label: 'Regular' },
                                                { value: 'irregular', label: 'Irregular' },
                                            ]
                                    }
                                    placeholder="Select standing…"
                                />
                            </FormSection>
                            <FormSection label="Form Issued Date" required error={form.errors.formIssuedDate}>
                                <input
                                    type="date"
                                    value={form.data.formIssuedDate}
                                    onChange={(e) => form.setData('formIssuedDate', e.target.value)}
                                    max={dateOnly(new Date().toISOString())}
                                    className={inputClass}
                                />
                            </FormSection>
                        </div>
                    </div>

                    {Object.keys(form.errors).length > 0 && (
                        <p className="text-xs font-semibold text-danger-600">
                            Some required fields are missing or invalid — they are marked above.
                        </p>
                    )}

                    <div className="flex items-center gap-3">
                        <button
                            type="submit"
                            disabled={form.processing}
                            className="flex items-center gap-2 rounded-xl bg-gradient-to-r from-cyan-600 to-blue-700 px-5 py-2.5 font-heading text-xs font-bold text-white shadow-md transition-all hover:from-cyan-500 hover:to-blue-600 disabled:opacity-50"
                        >
                            {form.processing ? 'Saving…' : 'Save Profile'}
                        </button>
                        <button
                            type="button"
                            onClick={cancelEditing}
                            disabled={form.processing}
                            className="rounded-xl border border-slate-300 px-4 py-2.5 text-xs font-bold text-slate-600 transition hover:bg-slate-50"
                        >
                            Cancel
                        </button>
                        <p className="text-[11px] text-slate-400">
                            Saving updates the student record and dates the enrollment form as issued by this desk.
                        </p>
                    </div>
                </form>
            )}
        </div>
    );
}
