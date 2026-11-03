import DataValue from '@/Components/DataValue';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { PageProps } from '@/types';
import { WorkspacePaginationLink, WorkspaceRow, WorkspaceSearch, WorkspaceValue } from '@/types/workspace';
import { Head, Link, useForm, usePoll } from '@inertiajs/react';
import { labels, sorting } from './Workspace/config';
import Filters from './Workspace/Partials/Filters';
import ImportUpload from './Workspace/Partials/ImportUpload';
import ResultsTable from './Workspace/Partials/ResultsTable';

type WorkspaceData = {
    section: string;
    filters: Record<string, string | number | null>;
    rows: WorkspaceRow[];
    links: WorkspacePaginationLink[];
    total: number;
    summary: Record<string, WorkspaceValue>;
    importStatus: string | null;
};
function ImportProgress() {
    usePoll(2000, { only: ['rows', 'links', 'total', 'summary', 'importStatus'] });
    return (
        <p role="status" className="text-sm text-gray-600">
            ფაილი მუშავდება. შედეგები ავტომატურად განახლდება.
        </p>
    );
}

function pageTitle(section: string, summary: Record<string, WorkspaceValue>): string {
    const contract = summary.contract;
    if (section === 'contract' && contract && typeof contract === 'object' && !Array.isArray(contract)) {
        return contract.label;
    }
    if (section === 'payment') return `გადახდა — ${summary.bank_reference}`;
    return labels[section];
}

export default function Workspace({
    section,
    filters,
    rows,
    links,
    total,
    summary,
    importStatus,
    auth,
    flash,
}: PageProps<WorkspaceData>) {
    const sortOptions = sorting[section] ?? sorting.contracts;
    const search = useForm<WorkspaceSearch>({
        q: String(filters.q ?? ''),
        status: String(filters.status ?? ''),
        project_id: String(filters.project_id ?? ''),
        client_id: String(filters.client_id ?? ''),
        as_of: String(filters.as_of ?? ''),
        sort: String(filters.sort ?? sortOptions[0].value),
        direction: String(filters.direction ?? 'asc'),
        import_status: String(filters.import_status ?? ''),
        client_match: String(filters.client_match ?? ''),
    });
    const processing = ['queued', 'processing'].includes(importStatus ?? '');
    const financial = Object.entries(summary).filter(([key]) => key.endsWith('_GEL'));
    const context = Object.entries(summary).filter(([field]) => {
        if (field.endsWith('_GEL') || field === 'as_of') return false;
        if (section === 'contract' && field === 'contract') return false;
        const hasClientLink = typeof summary.client === 'object' && summary.client !== null;
        return field !== 'personal_number' || !hasClientLink;
    });
    const title = pageTitle(section, summary);

    return (
        <AuthenticatedLayout
            header={
                <div lang="ka">
                    {section === 'contract' && <p className="mb-1 text-xs font-medium text-gray-500">ხელშეკრულება</p>}
                    <h1 className="text-xl font-semibold text-ink">{title}</h1>
                </div>
            }
        >
            <Head title={title} />
            <div lang="ka" className="mx-auto flex max-w-7xl flex-col gap-6 px-4 py-7 sm:px-6 lg:px-8">
                <div className="flex flex-wrap gap-2 empty:hidden">
                    {section === 'apartments' && auth.permissions.includes('apartments.create') && (
                        <Link
                            href={route('apartments.create')}
                            className="rounded-lg bg-brand px-4 py-3 text-sm text-white hover:bg-brand-hover"
                        >
                            ახალი ბინა
                        </Link>
                    )}
                    {['contracts', 'apartments'].includes(section) && auth.permissions.includes('contracts.create') && (
                        <Link
                            href={route('contracts.create')}
                            className={`rounded-lg px-4 py-3 text-sm ${section === 'contracts' ? 'bg-brand text-white hover:bg-brand-hover' : 'border border-gray-300 bg-white text-gray-700 hover:border-brand hover:text-brand'}`}
                        >
                            ახალი ხელშეკრულება
                        </Link>
                    )}
                    {section === 'apartments' && auth.permissions.includes('projects.create') && (
                        <Link
                            href={route('projects.create')}
                            className="rounded-lg border border-gray-300 bg-white px-4 py-3 text-sm text-gray-700 hover:border-brand hover:text-brand"
                        >
                            ახალი პროექტი
                        </Link>
                    )}
                </div>
                {flash.success && (
                    <p role="status" className="rounded-lg bg-green-50 p-4 text-green-800">
                        {flash.success}
                    </p>
                )}
                {flash.error && (
                    <p role="alert" className="rounded-lg bg-red-50 p-4 text-red-800">
                        {flash.error}
                    </p>
                )}
                {processing && <ImportProgress />}
                {financial.length > 0 && (
                    <dl className="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
                        {financial.map(([key, value]) => (
                            <div
                                key={key}
                                className={`rounded-xl border p-5 ${key === 'overdue_GEL' ? 'border-red-100 bg-red-50 text-red-700' : key === 'paid_GEL' ? 'border-green-100 bg-green-50 text-green-800' : 'border-gray-200 bg-white text-ink'}`}
                            >
                                <dt className="text-sm opacity-80">{labels[key]}</dt>
                                <dd className="mt-2 text-2xl font-semibold">
                                    <DataValue field={key} value={value} permissions={auth.permissions} />
                                </dd>
                            </div>
                        ))}
                    </dl>
                )}
                {context.length > 0 && (
                    <dl className="grid gap-x-8 gap-y-5 rounded-xl border border-gray-200 bg-white p-6 sm:grid-cols-2 lg:grid-cols-3">
                        {context.map(([key, value]) => (
                            <div key={key} className={key === 'purpose' || key === 'error' ? 'sm:col-span-2' : ''}>
                                <dt className="text-xs font-medium text-gray-500">{labels[key] ?? key}</dt>
                                <dd className="mt-1 break-words text-sm text-ink">
                                    <DataValue field={key} value={value} permissions={auth.permissions} />
                                </dd>
                            </div>
                        ))}
                    </dl>
                )}
                <Filters section={section} search={search} />
                {section === 'imports' && auth.permissions.includes('payments.import') && <ImportUpload />}
                <ResultsTable
                    section={section}
                    rows={rows}
                    links={links}
                    total={total}
                    permissions={auth.permissions}
                    processing={search.processing}
                />
            </div>
        </AuthenticatedLayout>
    );
}
