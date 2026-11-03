import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { PageProps } from '@/types';
import { Head } from '@inertiajs/react';
import UpdatePasswordForm from './Partials/UpdatePasswordForm';
import UpdateProfileInformationForm from './Partials/UpdateProfileInformationForm';

export default function Edit({
    mustVerifyEmail,
    emailVerified,
    status,
    roles,
}: PageProps<{
    mustVerifyEmail: boolean;
    emailVerified: boolean;
    status?: string;
    roles: string[];
}>) {
    return (
        <AuthenticatedLayout
            header={
                <div className="flex flex-wrap items-center justify-between gap-3">
                    <h1 className="text-2xl font-semibold text-ink">პროფილი</h1>
                    <div aria-label="მინიჭებული როლი" className="flex flex-wrap gap-2">
                        {(roles.length ? roles : ['როლი არ არის მინიჭებული']).map((role) => (
                            <span
                                key={role}
                                className="rounded-full border border-brand-border/70 bg-brand-soft px-3 py-1.5 text-xs font-medium text-brand"
                            >
                                {role}
                            </span>
                        ))}
                    </div>
                </div>
            }
        >
            <Head title="პროფილი" />
            <div lang="ka" className="mx-auto max-w-7xl px-4 py-6 sm:px-6 lg:px-8">
                <div className="grid overflow-hidden rounded-2xl border border-gray-200 bg-white lg:grid-cols-2">
                    <UpdateProfileInformationForm
                        mustVerifyEmail={mustVerifyEmail}
                        emailVerified={emailVerified}
                        status={status}
                    />
                    <UpdatePasswordForm />
                </div>
            </div>
        </AuthenticatedLayout>
    );
}
