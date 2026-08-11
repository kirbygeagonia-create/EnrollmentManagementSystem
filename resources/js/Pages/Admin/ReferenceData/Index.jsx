import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { Head } from '@inertiajs/react';
import { Link } from '@inertiajs/react';
import { PageHeader, Card, StatCard, Badge } from '@/Components/ui';

const referenceDataItems = [
    { name: 'Courses', route: 'admin.reference-data.courses', icon: CourseIcon, count: 'courses', description: 'Academic programs offered', color: 'brand' },
    { name: 'Majors', route: 'admin.reference-data.majors', icon: MajorIcon, count: 'majors', description: 'Specializations within courses', color: 'success' },
    { name: 'Curriculums', route: 'admin.reference-data.curriculums', icon: CurriculumIcon, count: 'curriculums', description: 'Course curriculum structures', color: 'info' },
    { name: 'Subjects', route: 'admin.reference-data.subjects', icon: SubjectIcon, count: 'subjects', description: 'Individual course subjects', color: 'warning' },
    { name: 'Academic Terms', route: 'admin.reference-data.terms', icon: TermIcon, count: 'terms', description: 'Semesters and school years', color: 'accent' },
    { name: 'Fee Types', route: 'admin.reference-data.fee-types', icon: FeeIcon, count: 'feeTypes', description: 'Tuition and miscellaneous fees', color: 'danger' },
    { name: 'Scholarship Types', route: 'admin.reference-data.scholarship-types', icon: ScholarshipIcon, count: 'scholarshipTypes', description: 'Available scholarship programs', color: 'info' },
    { name: 'Offices', route: 'admin.reference-data.offices', icon: OfficeIcon, count: 'offices', description: 'Administrative offices', color: 'brand' },
    { name: 'Rooms', route: 'admin.reference-data.rooms', icon: RoomIcon, count: 'rooms', description: 'Classrooms and facilities', color: 'success' },
    { name: 'Blocks', route: 'admin.reference-data.blocks', icon: BlockIcon, count: 'blocks', description: 'Student block sections', color: 'warning' },
    { name: 'Admission Requirements', route: 'admin.reference-data.admission-requirements', icon: SubjectIcon, count: 'admissionRequirements', description: 'Documents required for admission', color: 'info' },
    { name: 'Clearance Requirements', route: 'admin.reference-data.clearance-requirements', icon: ClearanceIcon, count: 'clearanceRequirements', description: 'Documents required for clearance', color: 'success' },
];

function CourseIcon({ className }) {
    return <svg className={className} fill="none" stroke="currentColor" viewBox="0 0 24 24"><path strokeLinecap="round" strokeLinejoin="round" strokeWidth="2" d="M12 6.253v13m0-13C10.832 5.477 9.246 5 7.5 5S4.168 5.477 3 6.253v13C4.168 18.477 5.754 18 7.5 18s3.332.477 4.5 1.253m0-13C13.168 5.477 14.754 5 16.5 5c1.747 0 3.332.477 4.5 1.253v13C19.832 18.477 18.247 18 16.5 18c-1.746 0-3.332.477-4.5 1.253" /></svg>;
}
function MajorIcon({ className }) {
    return <svg className={className} fill="none" stroke="currentColor" viewBox="0 0 24 24"><path strokeLinecap="round" strokeLinejoin="round" strokeWidth="2" d="M19 11H5m14 0a2 2 0 012 2v6a2 2 0 01-2 2H5a2 2 0 01-2-2v-6a2 2 0 012-2m14 0V9a2 2 0 00-2-2M5 11V9a2 2 0 012-2m0 0V5a2 2 0 012-2h6a2 2 0 012 2v2M7 7h10" /></svg>;
}
function CurriculumIcon({ className }) {
    return <svg className={className} fill="none" stroke="currentColor" viewBox="0 0 24 24"><path strokeLinecap="round" strokeLinejoin="round" strokeWidth="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" /></svg>;
}
function SubjectIcon({ className }) {
    return <svg className={className} fill="none" stroke="currentColor" viewBox="0 0 24 24"><path strokeLinecap="round" strokeLinejoin="round" strokeWidth="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z" /></svg>;
}
function TermIcon({ className }) {
    return <svg className={className} fill="none" stroke="currentColor" viewBox="0 0 24 24"><path strokeLinecap="round" strokeLinejoin="round" strokeWidth="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z" /></svg>;
}
function FeeIcon({ className }) {
    return <svg className={className} fill="none" stroke="currentColor" viewBox="0 0 24 24"><path strokeLinecap="round" strokeLinejoin="round" strokeWidth="2" d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z" /></svg>;
}
function ScholarshipIcon({ className }) {
    return <svg className={className} fill="none" stroke="currentColor" viewBox="0 0 24 24"><path strokeLinecap="round" strokeLinejoin="round" strokeWidth="2" d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z" /></svg>;
}
function OfficeIcon({ className }) {
    return <svg className={className} fill="none" stroke="currentColor" viewBox="0 0 24 24"><path strokeLinecap="round" strokeLinejoin="round" strokeWidth="2" d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4" /></svg>;
}
function RoomIcon({ className }) {
    return <svg className={className} fill="none" stroke="currentColor" viewBox="0 0 24 24"><path strokeLinecap="round" strokeLinejoin="round" strokeWidth="2" d="M3 12l2-2m0 0l7-7 7 7M5 10v10a1 1 0 001 1h3m10-11l2 2m-2-2v10a1 1 0 01-1 1h-3m-6 0a1 1 0 001-1v-4a1 1 0 011-1h2a1 1 0 011 1v4a1 1 0 001 1m-6 0h6" /></svg>;
}
function BlockIcon({ className }) {
    return <svg className={className} fill="none" stroke="currentColor" viewBox="0 0 24 24"><path strokeLinecap="round" strokeLinejoin="round" strokeWidth="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z" /></svg>;
}
function ClearanceIcon({ className }) {
    return <svg className={className} fill="none" stroke="currentColor" viewBox="0 0 24 24"><path strokeLinecap="round" strokeLinejoin="round" strokeWidth="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z" /><path strokeLinecap="round" strokeLinejoin="round" strokeWidth="2" d="M12 6v6m0 0v6m0-6h6m-6 0H6" /></svg>;
}

const colorClasses = {
    brand: { bg: 'bg-brand-100', text: 'text-brand-700', iconBg: 'bg-brand-100', iconText: 'text-brand-700', badge: 'badge-brand' },
    success: { bg: 'bg-success-100', text: 'text-success-700', iconBg: 'bg-success-100', iconText: 'text-success-700', badge: 'badge-success' },
    info: { bg: 'bg-info-100', text: 'text-info-700', iconBg: 'bg-info-100', iconText: 'text-info-700', badge: 'badge-info' },
    warning: { bg: 'bg-warning-100', text: 'text-warning-700', iconBg: 'bg-warning-100', iconText: 'text-warning-700', badge: 'badge-warning' },
    accent: { bg: 'bg-accent-100', text: 'text-accent-700', iconBg: 'bg-accent-100', iconText: 'text-accent-700', badge: 'badge-accent' },
    danger: { bg: 'bg-danger-100', text: 'text-danger-700', iconBg: 'bg-danger-100', iconText: 'text-danger-700', badge: 'badge-danger' },
};

export default function Index({ stats = {} }) {
    return (
        <AuthenticatedLayout
            header={
                <PageHeader
                    title="Reference Data Management"
                    subtitle="Manage all reference data for the enrollment system"
                    logo="/images/logos/seait-logo.png"
                    logoAlt="SEAIT Logo"
                />
            }
        >
            <Head title="Reference Data" />

            {/* Stats Overview */}
            <div className="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-5 gap-4 mb-6">
                <StatCard
                    label="Courses"
                    value={stats.courses || 0}
                    icon={<CourseIcon className="h-5 w-5" />}
                    iconBg="brand"
                />
                <StatCard
                    label="Majors"
                    value={stats.majors || 0}
                    icon={<MajorIcon className="h-5 w-5" />}
                    iconBg="success"
                />
                <StatCard
                    label="Curriculums"
                    value={stats.curriculums || 0}
                    icon={<CurriculumIcon className="h-5 w-5" />}
                    iconBg="info"
                />
                <StatCard
                    label="Subjects"
                    value={stats.subjects || 0}
                    icon={<SubjectIcon className="h-5 w-5" />}
                    iconBg="warning"
                />
                <StatCard
                    label="Terms"
                    value={stats.terms || 0}
                    icon={<TermIcon className="h-5 w-5" />}
                    iconBg="accent"
                />
            </div>

            <div className="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-5 gap-4 mb-8">
                <StatCard
                    label="Fee Types"
                    value={stats.feeTypes || 0}
                    icon={<FeeIcon className="h-5 w-5" />}
                    iconBg="danger"
                />
                <StatCard
                    label="Scholarships"
                    value={stats.scholarshipTypes || 0}
                    icon={<ScholarshipIcon className="h-5 w-5" />}
                    iconBg="info"
                />
                <StatCard
                    label="Offices"
                    value={stats.offices || 0}
                    icon={<OfficeIcon className="h-5 w-5" />}
                    iconBg="brand"
                />
                <StatCard
                    label="Rooms"
                    value={stats.rooms || 0}
                    icon={<RoomIcon className="h-5 w-5" />}
                    iconBg="success"
                />
                <StatCard
                    label="Blocks"
                    value={stats.blocks || 0}
                    icon={<BlockIcon className="h-5 w-5" />}
                    iconBg="warning"
                />
            </div>

            {/* Management Cards */}
            <Card title="Data Management" subtitle="Click a card to manage that reference data">
                <div className="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4 gap-4">
                    {referenceDataItems.map((item) => {
                        const colors = colorClasses[item.color] || colorClasses.brand;
                        const count = stats[item.count] || 0;
                        return (
                            <Link
                                key={item.route}
                                href={route(item.route)}
                                className="card p-5 hover:shadow-card-hover hover:border-brand-300 transition-all duration-200 group"
                            >
                                <div className="flex items-start gap-4">
                                    <div className={`${colors.iconBg} ${colors.iconText} h-12 w-12 rounded-lg flex items-center justify-center flex-shrink-0 group-hover:scale-105 transition-transform`}>
                                        <item.icon className="h-6 w-6" />
                                    </div>
                                    <div className="flex-1 min-w-0">
                                        <div className="flex items-center justify-between">
                                            <h3 className="font-semibold text-brand-900 group-hover:text-brand-700 transition-colors">
                                                {item.name}
                                            </h3>
                                            <Badge tone={item.color === 'brand' ? 'neutral' : item.color}>{count}</Badge>
                                        </div>
                                        <p className="text-sm text-brand-500 mt-1">{item.description}</p>
                                    </div>
                                </div>
                            </Link>
                        );
                    })}
                </div>
            </Card>
        </AuthenticatedLayout>
    );
}