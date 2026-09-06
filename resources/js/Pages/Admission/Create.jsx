import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { Head } from '@inertiajs/react';
import { PageHeader, Card, FormSection, Select, StepProgress } from '@/Components/ui';
import { useForm, router } from '@inertiajs/react';
import { useState } from 'react';
import useFormKeyboardNav from '@/Hooks/useFormKeyboardNav';

const applicantTypeOptions = [
    { value: 'firstYear', label: 'First Year' },
    { value: 'transferee', label: 'Transferee' },
    { value: 'continuing', label: 'Continuing' },
    { value: 'shifter', label: 'Shifter' },
];

const genderOptions = [
    { value: 'male', label: 'Male' },
    { value: 'female', label: 'Female' },
];

const civilStatusOptions = [
    { value: 'single', label: 'Single' },
    { value: 'married', label: 'Married' },
    { value: 'widowed', label: 'Widowed' },
    { value: 'separated', label: 'Separated' },
];

const addressTypeOptions = [
    { value: 'home', label: 'Home Address' },
    { value: 'current', label: 'Current Address' },
    { value: 'permanent', label: 'Permanent Address' },
];

const relationshipOptions = [
    { value: 'mother', label: 'Mother' },
    { value: 'father', label: 'Father' },
    { value: 'guardian', label: 'Guardian' },
    { value: 'other', label: 'Other' },
];

const institutionTypeOptions = [
    { value: 'elementary', label: 'Elementary' },
    { value: 'secondary', label: 'Secondary' },
    { value: 'seniorHigh', label: 'Senior High' },
    { value: 'college', label: 'College' },
    { value: 'graduate', label: 'Graduate' },
];

const levelCompletedOptions = [
    { value: 'elementary', label: 'Elementary' },
    { value: 'secondary', label: 'Secondary' },
    { value: 'seniorHigh', label: 'Senior High' },
    { value: 'college', label: 'College' },
    { value: 'graduate', label: 'Graduate' },
];

// Section progress steps (visual navigator — scroll-to-anchor only)
const sectionSteps = [
    { label: 'Student', status: 'current', anchor: 'section-student' },
    { label: 'Addresses', status: 'pending', anchor: 'section-addresses' },
    { label: 'Guardians', status: 'pending', anchor: 'section-guardians' },
    { label: 'Education', status: 'pending', anchor: 'section-education' },
    { label: 'Admission', status: 'pending', anchor: 'section-admission' },
];

export default function Create({ courses, terms, religions }) {
    const [addresses, setAddresses] = useState([{ addressType: 'home', houseBuildingNo: '', street: '', sitioPurok: '', barangay: '', cityMunicipality: '', district: '', province: '', region: '', zipCode: '', country: 'Philippines' }]);
    const [guardians, setGuardians] = useState([{ relationship: 'mother', fullName: '', contactNumber: '', email: '', isEmergencyContact: false, isAuthorizedToActOnBehalf: false }]);
    const [educationalBackgrounds, setEducationalBackgrounds] = useState([]);

    const form = useForm({
        schoolIdNumber: '',
        lastName: '',
        firstName: '',
        middleName: '',
        suffix: '',
        gender: '',
        birthdate: '',
        birthplace: '',
        citizenship: 'Filipino',
        religionId: '',
        civilStatus: 'single',
        contactNumber: '',
        telephoneNumber: '',
        email: '',
        username: '',
        password: '',
        password_confirmation: '',
        courseId: '',
        termId: '',
        applicantType: 'firstYear',
    });

    const updateAddress = (index, field, value) => {
        const newAddresses = [...addresses];
        newAddresses[index] = { ...newAddresses[index], [field]: value };
        setAddresses(newAddresses);
    };

    const addAddress = () => {
        setAddresses([...addresses, { addressType: 'home', houseBuildingNo: '', street: '', sitioPurok: '', barangay: '', cityMunicipality: '', district: '', province: '', region: '', zipCode: '', country: 'Philippines' }]);
    };

    const removeAddress = (index) => {
        if (addresses.length <= 1) return;
        setAddresses(addresses.filter((_, i) => i !== index));
    };

    const updateGuardian = (index, field, value) => {
        const newGuardians = [...guardians];
        if (field === 'isEmergencyContact' || field === 'isAuthorizedToActOnBehalf') {
            newGuardians[index] = { ...newGuardians[index], [field]: value };
        } else {
            newGuardians[index] = { ...newGuardians[index], [field]: value };
        }
        setGuardians(newGuardians);
    };

    const addGuardian = () => {
        setGuardians([...guardians, { relationship: 'mother', fullName: '', contactNumber: '', email: '', isEmergencyContact: false, isAuthorizedToActOnBehalf: false }]);
    };

    const removeGuardian = (index) => {
        if (guardians.length <= 1) return;
        setGuardians(guardians.filter((_, i) => i !== index));
    };

    const updateEducationalBackground = (index, field, value) => {
        const newBackgrounds = [...educationalBackgrounds];
        newBackgrounds[index] = { ...newBackgrounds[index], [field]: value };
        setEducationalBackgrounds(newBackgrounds);
    };

    const addEducationalBackground = () => {
        setEducationalBackgrounds([...educationalBackgrounds, { institutionName: '', institutionType: '', cityMunicipality: '', province: '', levelCompleted: '', strandTrack: '', yearCompleted: '', honorsCertifications: '' }]);
    };

    const removeEducationalBackground = (index) => {
        setEducationalBackgrounds(educationalBackgrounds.filter((_, i) => i !== index));
    };

    const handleSubmit = (e) => {
        e.preventDefault();
        const formData = new FormData();
        
        Object.entries(form.data()).forEach(([key, value]) => {
            formData.append(key, value);
        });

        addresses.forEach((addr, idx) => {
            Object.entries(addr).forEach(([key, value]) => {
                formData.append(`addresses[${idx}][${key}]`, value);
            });
        });

        guardians.forEach((guardian, idx) => {
            Object.entries(guardian).forEach(([key, value]) => {
                formData.append(`guardians[${idx}][${key}]`, value);
            });
        });

        educationalBackgrounds.forEach((bg, idx) => {
            Object.entries(bg).forEach(([key, value]) => {
                if (value !== '' && value !== false) {
                    formData.append(`educationalBackgrounds[${idx}][${key}]`, value);
                }
            });
        });

        router.post(route('admission.store'), formData, {
            onSuccess: () => form.reset(),
        });
    };

    // Smooth-scroll to a section anchor (visual navigation aid only)
    const scrollToSection = (anchor) => {
        const el = document.getElementById(anchor);
        if (el) el.scrollIntoView({ behavior: 'smooth', block: 'start' });
    };

    const { formProps } = useFormKeyboardNav({
        onSubmit: handleSubmit,
        autoFocusFirst: true,
    });

    return (
        <AuthenticatedLayout
            header={
                <PageHeader
                    title="New Admission"
                    subtitle="Register a new student applicant"
                    logo="/images/logos/seait-logo.png"
                    logoAlt="SEAIT Logo"
                />
            }
        >
            <Head title="New Admission" />

            {/* Section Progress Navigator */}
            <Card className="mb-4 sm:mb-5">
                <StepProgress steps={sectionSteps} />
                <div className="mt-4 flex flex-wrap justify-center gap-2">
                    {sectionSteps.map((step) => (
                        <button
                            key={step.anchor}
                            type="button"
                            onClick={() => scrollToSection(step.anchor)}
                            className="btn btn-ghost btn-sm text-brand-600 hover:text-brand-900"
                        >
                            {step.label}
                        </button>
                    ))}
                </div>
            </Card>

            {/* Rapid Data Entry Keyboard Navigation Banner */}
            <div className="flex flex-col sm:flex-row items-start sm:items-center justify-between px-4 py-2.5 rounded-xl bg-white border border-slate-200/90 text-xs text-slate-700 shadow-sm mb-4 gap-2">
                <div className="flex items-center gap-2 flex-wrap">
                    <span className="h-2 w-2 rounded-full bg-seait-500 animate-pulse" />
                    <span className="font-bold text-slate-900">Rapid Data Entry:</span>
                    <span>Press <span className="kbd-badge"><kbd>Enter ↵</kbd> next field</span></span>
                    <span className="text-slate-400 hidden sm:inline">·</span>
                    <span className="hidden sm:inline"><span className="kbd-badge"><kbd>Shift+Enter</kbd> previous</span></span>
                    <span className="text-slate-400 hidden md:inline">·</span>
                    <span className="hidden md:inline"><span className="kbd-badge"><kbd>Ctrl+Enter</kbd> submit</span></span>
                </div>
                <span className="text-[11px] font-bold text-seait-700 uppercase tracking-wider hidden lg:inline">
                    Auto-Advances Cursor
                </span>
            </div>

            <form {...formProps} onSubmit={handleSubmit} className="space-y-6">
                {/* Student Information */}
                <div id="section-student" className="scroll-mt-24">
                    <Card title="Student Information" subtitle="Personal and demographic details">
                        <div className="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-4">
                            <FormSection label="School ID Number" required>
                                <input
                                    type="text"
                                    value={form.data.schoolIdNumber}
                                    onChange={(e) => form.setData('schoolIdNumber', e.target.value)}
                                    className="form-input"
                                    required
                                    maxLength={50}
                                />
                                {form.errors.schoolIdNumber && <p className="form-error">{form.errors.schoolIdNumber}</p>}
                            </FormSection>

                            <FormSection label="Last Name" required>
                                <input
                                    type="text"
                                    value={form.data.lastName}
                                    onChange={(e) => form.setData('lastName', e.target.value)}
                                    className="form-input"
                                    required
                                    maxLength={100}
                                />
                                {form.errors.lastName && <p className="form-error">{form.errors.lastName}</p>}
                            </FormSection>

                            <FormSection label="First Name" required>
                                <input
                                    type="text"
                                    value={form.data.firstName}
                                    onChange={(e) => form.setData('firstName', e.target.value)}
                                    className="form-input"
                                    required
                                    maxLength={100}
                                />
                                {form.errors.firstName && <p className="form-error">{form.errors.firstName}</p>}
                            </FormSection>

                            <FormSection label="Middle Name">
                                <input
                                    type="text"
                                    value={form.data.middleName}
                                    onChange={(e) => form.setData('middleName', e.target.value)}
                                    className="form-input"
                                    maxLength={100}
                                />
                            </FormSection>

                            <FormSection label="Suffix">
                                <input
                                    type="text"
                                    value={form.data.suffix}
                                    onChange={(e) => form.setData('suffix', e.target.value)}
                                    className="form-input"
                                    maxLength={20}
                                />
                            </FormSection>

                            <FormSection label="Gender" required>
                                <Select
                                    value={form.data.gender}
                                    onChange={(e) => form.setData('gender', e.target.value)}
                                    options={genderOptions}
                                    placeholder="Select gender"
                                    required
                                />
                                {form.errors.gender && <p className="form-error">{form.errors.gender}</p>}
                            </FormSection>

                            <FormSection label="Birthdate" required>
                                <input
                                    type="date"
                                    value={form.data.birthdate}
                                    onChange={(e) => form.setData('birthdate', e.target.value)}
                                    className="form-input"
                                    required
                                />
                                {form.errors.birthdate && <p className="form-error">{form.errors.birthdate}</p>}
                            </FormSection>

                            <FormSection label="Birthplace" required>
                                <input
                                    type="text"
                                    value={form.data.birthplace}
                                    onChange={(e) => form.setData('birthplace', e.target.value)}
                                    className="form-input"
                                    required
                                    maxLength={255}
                                />
                                {form.errors.birthplace && <p className="form-error">{form.errors.birthplace}</p>}
                            </FormSection>

                            <FormSection label="Citizenship" required>
                                <input
                                    type="text"
                                    value={form.data.citizenship}
                                    onChange={(e) => form.setData('citizenship', e.target.value)}
                                    className="form-input"
                                    required
                                    maxLength={100}
                                />
                                {form.errors.citizenship && <p className="form-error">{form.errors.citizenship}</p>}
                            </FormSection>

                            <FormSection label="Religion" required>
                                <Select
                                    value={form.data.religionId}
                                    onChange={(e) => form.setData('religionId', e.target.value)}
                                    options={religions.map(r => ({ value: r.religionId, label: r.religionName }))}
                                    placeholder="Select religion"
                                    required
                                />
                                {form.errors.religionId && <p className="form-error">{form.errors.religionId}</p>}
                            </FormSection>

                            <FormSection label="Civil Status" required>
                                <Select
                                    value={form.data.civilStatus}
                                    onChange={(e) => form.setData('civilStatus', e.target.value)}
                                    options={civilStatusOptions}
                                    required
                                />
                                {form.errors.civilStatus && <p className="form-error">{form.errors.civilStatus}</p>}
                            </FormSection>

                            <FormSection label="Contact Number" required>
                                <input
                                    type="tel"
                                    value={form.data.contactNumber}
                                    onChange={(e) => form.setData('contactNumber', e.target.value)}
                                    className="form-input"
                                    required
                                    maxLength={20}
                                />
                                {form.errors.contactNumber && <p className="form-error">{form.errors.contactNumber}</p>}
                            </FormSection>

                            <FormSection label="Telephone Number">
                                <input
                                    type="tel"
                                    value={form.data.telephoneNumber}
                                    onChange={(e) => form.setData('telephoneNumber', e.target.value)}
                                    className="form-input"
                                    maxLength={20}
                                />
                            </FormSection>

                            <FormSection label="Email" required>
                                <input
                                    type="email"
                                    value={form.data.email}
                                    onChange={(e) => form.setData('email', e.target.value)}
                                    className="form-input"
                                    required
                                    maxLength={255}
                                />
                                {form.errors.email && <p className="form-error">{form.errors.email}</p>}
                            </FormSection>

                            <FormSection label="Username" required>
                                <input
                                    type="text"
                                    value={form.data.username}
                                    onChange={(e) => form.setData('username', e.target.value)}
                                    className="form-input"
                                    required
                                    maxLength={50}
                                />
                                {form.errors.username && <p className="form-error">{form.errors.username}</p>}
                            </FormSection>

                            <FormSection label="Password" required>
                                <input
                                    type="password"
                                    value={form.data.password}
                                    onChange={(e) => form.setData('password', e.target.value)}
                                    className="form-input"
                                    required
                                    minLength={8}
                                />
                                {form.errors.password && <p className="form-error">{form.errors.password}</p>}
                            </FormSection>

                            <FormSection label="Confirm Password" required>
                                <input
                                    type="password"
                                    value={form.data.password_confirmation}
                                    onChange={(e) => form.setData('password_confirmation', e.target.value)}
                                    className="form-input"
                                    required
                                />
                                {form.errors.password_confirmation && <p className="form-error">{form.errors.password_confirmation}</p>}
                            </FormSection>
                        </div>
                    </Card>
                </div>

                {/* Addresses */}
                <div id="section-addresses" className="scroll-mt-24">
                    <Card title="Addresses" subtitle="At least one address is required">
                        {addresses.map((addr, idx) => (
                            <div key={idx} className="border border-brand-200 rounded-card p-4 mb-4 last:mb-0">
                                <div className="flex items-center justify-between mb-3">
                                    <h4 className="font-medium text-brand-900">Address {idx + 1}</h4>
                                    {addresses.length > 1 && (
                                        <button
                                            type="button"
                                            onClick={() => removeAddress(idx)}
                                            className="btn btn-ghost btn-sm text-danger-600 hover:text-danger-900"
                                        >
                                            Remove
                                        </button>
                                    )}
                                </div>
                                <div className="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-4">
                                    <FormSection label="Address Type" required>
                                        <Select
                                            value={addr.addressType}
                                            onChange={(e) => updateAddress(idx, 'addressType', e.target.value)}
                                            options={addressTypeOptions}
                                            required
                                        />
                                    </FormSection>
                                    <FormSection label="House/Building No.">
                                        <input
                                            type="text"
                                            value={addr.houseBuildingNo}
                                            onChange={(e) => updateAddress(idx, 'houseBuildingNo', e.target.value)}
                                            className="form-input"
                                            maxLength={100}
                                        />
                                    </FormSection>
                                    <FormSection label="Street">
                                        <input
                                            type="text"
                                            value={addr.street}
                                            onChange={(e) => updateAddress(idx, 'street', e.target.value)}
                                            className="form-input"
                                            maxLength={255}
                                        />
                                    </FormSection>
                                    <FormSection label="Sitio/Purok">
                                        <input
                                            type="text"
                                            value={addr.sitioPurok}
                                            onChange={(e) => updateAddress(idx, 'sitioPurok', e.target.value)}
                                            className="form-input"
                                            maxLength={100}
                                        />
                                    </FormSection>
                                    <FormSection label="Barangay" required>
                                        <input
                                            type="text"
                                            value={addr.barangay}
                                            onChange={(e) => updateAddress(idx, 'barangay', e.target.value)}
                                            className="form-input"
                                            required
                                            maxLength={100}
                                        />
                                    </FormSection>
                                    <FormSection label="City/Municipality" required>
                                        <input
                                            type="text"
                                            value={addr.cityMunicipality}
                                            onChange={(e) => updateAddress(idx, 'cityMunicipality', e.target.value)}
                                            className="form-input"
                                            required
                                            maxLength={100}
                                        />
                                    </FormSection>
                                    <FormSection label="District">
                                        <input
                                            type="text"
                                            value={addr.district}
                                            onChange={(e) => updateAddress(idx, 'district', e.target.value)}
                                            className="form-input"
                                            maxLength={100}
                                        />
                                    </FormSection>
                                    <FormSection label="Province" required>
                                        <input
                                            type="text"
                                            value={addr.province}
                                            onChange={(e) => updateAddress(idx, 'province', e.target.value)}
                                            className="form-input"
                                            required
                                            maxLength={100}
                                        />
                                    </FormSection>
                                    <FormSection label="Region">
                                        <input
                                            type="text"
                                            value={addr.region}
                                            onChange={(e) => updateAddress(idx, 'region', e.target.value)}
                                            className="form-input"
                                            maxLength={100}
                                        />
                                    </FormSection>
                                    <FormSection label="ZIP Code">
                                        <input
                                            type="text"
                                            value={addr.zipCode}
                                            onChange={(e) => updateAddress(idx, 'zipCode', e.target.value)}
                                            className="form-input"
                                            maxLength={20}
                                        />
                                    </FormSection>
                                    <FormSection label="Country" required>
                                        <input
                                            type="text"
                                            value={addr.country}
                                            onChange={(e) => updateAddress(idx, 'country', e.target.value)}
                                            className="form-input"
                                            required
                                            maxLength={100}
                                        />
                                    </FormSection>
                                </div>
                            </div>
                        ))}
                        <button type="button" onClick={addAddress} className="btn btn-secondary btn-sm mt-4">
                            <svg className="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path strokeLinecap="round" strokeLinejoin="round" strokeWidth="2" d="M12 4v16m8-8H4" />
                            </svg>
                            Add Address
                        </button>
                    </Card>
                </div>

                {/* Guardians */}
                <div id="section-guardians" className="scroll-mt-24">
                    <Card title="Guardians" subtitle="At least one guardian is required">
                        {guardians.map((guardian, idx) => (
                            <div key={idx} className="border border-brand-200 rounded-card p-4 mb-4 last:mb-0">
                                <div className="flex items-center justify-between mb-3">
                                    <h4 className="font-medium text-brand-900">Guardian {idx + 1}</h4>
                                    {guardians.length > 1 && (
                                        <button
                                            type="button"
                                            onClick={() => removeGuardian(idx)}
                                            className="btn btn-ghost btn-sm text-danger-600 hover:text-danger-900"
                                        >
                                            Remove
                                        </button>
                                    )}
                                </div>
                                <div className="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-4">
                                    <FormSection label="Relationship" required>
                                        <Select
                                            value={guardian.relationship}
                                            onChange={(e) => updateGuardian(idx, 'relationship', e.target.value)}
                                            options={relationshipOptions}
                                            required
                                        />
                                    </FormSection>
                                    <FormSection label="Full Name" required>
                                        <input
                                            type="text"
                                            value={guardian.fullName}
                                            onChange={(e) => updateGuardian(idx, 'fullName', e.target.value)}
                                            className="form-input"
                                            required
                                            maxLength={255}
                                        />
                                    </FormSection>
                                    <FormSection label="Contact Number" required>
                                        <input
                                            type="tel"
                                            value={guardian.contactNumber}
                                            onChange={(e) => updateGuardian(idx, 'contactNumber', e.target.value)}
                                            className="form-input"
                                            required
                                            maxLength={20}
                                        />
                                    </FormSection>
                                    <FormSection label="Email">
                                        <input
                                            type="email"
                                            value={guardian.email}
                                            onChange={(e) => updateGuardian(idx, 'email', e.target.value)}
                                            className="form-input"
                                            maxLength={255}
                                        />
                                    </FormSection>
                                    <FormSection label="Emergency Contact">
                                        <label className="flex items-center gap-2 cursor-pointer">
                                            <input
                                                type="checkbox"
                                                checked={guardian.isEmergencyContact}
                                                onChange={(e) => updateGuardian(idx, 'isEmergencyContact', e.target.checked)}
                                                className="form-checkbox"
                                            />
                                            <span className="text-sm text-brand-700">Yes</span>
                                        </label>
                                    </FormSection>
                                    <FormSection label="Authorized Representative">
                                        <label className="flex items-center gap-2 cursor-pointer">
                                            <input
                                                type="checkbox"
                                                checked={guardian.isAuthorizedToActOnBehalf}
                                                onChange={(e) => updateGuardian(idx, 'isAuthorizedToActOnBehalf', e.target.checked)}
                                                className="form-checkbox"
                                            />
                                            <span className="text-sm text-brand-700">Yes</span>
                                        </label>
                                    </FormSection>
                                </div>
                            </div>
                        ))}
                        <button type="button" onClick={addGuardian} className="btn btn-secondary btn-sm mt-4">
                            <svg className="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path strokeLinecap="round" strokeLinejoin="round" strokeWidth="2" d="M12 4v16m8-8H4" />
                            </svg>
                            Add Guardian
                        </button>
                    </Card>
                </div>

                {/* Educational Backgrounds */}
                <div id="section-education" className="scroll-mt-24">
                    <Card title="Educational Background" subtitle="Optional - for transferees and shifters">
                        {educationalBackgrounds.map((bg, idx) => (
                            <div key={idx} className="border border-brand-200 rounded-card p-4 mb-4 last:mb-0">
                                <div className="flex items-center justify-between mb-3">
                                    <h4 className="font-medium text-brand-900">Institution {idx + 1}</h4>
                                    <button
                                        type="button"
                                        onClick={() => removeEducationalBackground(idx)}
                                        className="btn btn-ghost btn-sm text-danger-600 hover:text-danger-900"
                                    >
                                        Remove
                                    </button>
                                </div>
                                <div className="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-4">
                                    <FormSection label="Institution Name" required>
                                        <input
                                            type="text"
                                            value={bg.institutionName}
                                            onChange={(e) => updateEducationalBackground(idx, 'institutionName', e.target.value)}
                                            className="form-input"
                                            required
                                            maxLength={255}
                                        />
                                    </FormSection>
                                    <FormSection label="Institution Type" required>
                                        <Select
                                            value={bg.institutionType}
                                            onChange={(e) => updateEducationalBackground(idx, 'institutionType', e.target.value)}
                                            options={institutionTypeOptions}
                                            placeholder="Select type"
                                            required
                                        />
                                    </FormSection>
                                    <FormSection label="City/Municipality">
                                        <input
                                            type="text"
                                            value={bg.cityMunicipality}
                                            onChange={(e) => updateEducationalBackground(idx, 'cityMunicipality', e.target.value)}
                                            className="form-input"
                                            maxLength={100}
                                        />
                                    </FormSection>
                                    <FormSection label="Province">
                                        <input
                                            type="text"
                                            value={bg.province}
                                            onChange={(e) => updateEducationalBackground(idx, 'province', e.target.value)}
                                            className="form-input"
                                            maxLength={100}
                                        />
                                    </FormSection>
                                    <FormSection label="Level Completed" required>
                                        <Select
                                            value={bg.levelCompleted}
                                            onChange={(e) => updateEducationalBackground(idx, 'levelCompleted', e.target.value)}
                                            options={levelCompletedOptions}
                                            placeholder="Select level"
                                            required
                                        />
                                    </FormSection>
                                    <FormSection label="Strand/Track">
                                        <input
                                            type="text"
                                            value={bg.strandTrack}
                                            onChange={(e) => updateEducationalBackground(idx, 'strandTrack', e.target.value)}
                                            className="form-input"
                                            maxLength={100}
                                        />
                                    </FormSection>
                                    <FormSection label="Year Completed">
                                        <input
                                            type="date"
                                            value={bg.yearCompleted}
                                            onChange={(e) => updateEducationalBackground(idx, 'yearCompleted', e.target.value)}
                                            className="form-input"
                                        />
                                    </FormSection>
                                    <FormSection label="Honors/Certifications" className="sm:col-span-2 lg:col-span-3">
                                        <textarea
                                            value={bg.honorsCertifications}
                                            onChange={(e) => updateEducationalBackground(idx, 'honorsCertifications', e.target.value)}
                                            className="form-input form-textarea"
                                            rows="2"
                                            maxLength={500}
                                        />
                                    </FormSection>
                                </div>
                            </div>
                        ))}
                        <button type="button" onClick={addEducationalBackground} className="btn btn-secondary btn-sm mt-4">
                            <svg className="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path strokeLinecap="round" strokeLinejoin="round" strokeWidth="2" d="M12 4v16m8-8H4" />
                            </svg>
                            Add Educational Background
                        </button>
                    </Card>
                </div>

                {/* Admission Details */}
                <div id="section-admission" className="scroll-mt-24">
                    <Card title="Admission Details" subtitle="Course and term selection">
                        <div className="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-4">
                            <FormSection label="Course" required>
                                <Select
                                    value={form.data.courseId}
                                    onChange={(e) => form.setData('courseId', e.target.value)}
                                    options={courses.map(c => ({ value: c.courseId, label: `${c.courseCode} - ${c.courseName}` }))}
                                    placeholder="Select course"
                                    required
                                />
                                {form.errors.courseId && <p className="form-error">{form.errors.courseId}</p>}
                            </FormSection>

                            <FormSection label="Term" required>
                                <Select
                                    value={form.data.termId}
                                    onChange={(e) => form.setData('termId', e.target.value)}
                                    options={terms.map(t => ({ value: t.termId, label: `${t.semester} ${t.academicYear?.year || ''}` }))}
                                    placeholder="Select term"
                                    required
                                />
                                {form.errors.termId && <p className="form-error">{form.errors.termId}</p>}
                            </FormSection>

                            <FormSection label="Applicant Type" required>
                                <Select
                                    value={form.data.applicantType}
                                    onChange={(e) => form.setData('applicantType', e.target.value)}
                                    options={applicantTypeOptions}
                                    required
                                />
                                {form.errors.applicantType && <p className="form-error">{form.errors.applicantType}</p>}
                            </FormSection>
                        </div>
                    </Card>
                </div>

                {/* Actions */}
                <div className="flex justify-end gap-3">
                    <button
                        type="button"
                        onClick={() => router.get(route('admission.index'))}
                        className="btn btn-secondary"
                    >
                        Cancel
                    </button>
                    <button
                        type="submit"
                        disabled={form.processing}
                        className="btn btn-primary"
                    >
                        {form.processing ? 'Registering...' : 'Register Applicant'}
                    </button>
                </div>
            </form>
        </AuthenticatedLayout>
    );
}
