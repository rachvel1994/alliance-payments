import DataValue from '@/Components/DataValue';
import PrimaryButton from '@/Components/PrimaryButton';
import SelectInput from '@/Components/SelectInput';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { PageProps } from '@/types';
import { WorkspaceRow, WorkspaceValue } from '@/types/workspace';
import { Head, Link, useForm, usePoll } from '@inertiajs/react';
import { FormEvent } from 'react';

type WorkspaceData = {
    section: string;
    filters: Record<string, string | number | null>;
    rows: WorkspaceRow[];
    links: { url: string | null; label: string; active: boolean }[];
    total: number;
    summary: Record<string, WorkspaceValue>;
    importStatus: string | null;
};
const labels: Record<string, string> = {
    overdue: 'ვადაგადაცილება', apartments: 'ბინები', contracts: 'ხელშეკრულებები', contract: 'ხელშეკრულება',
    payments: 'გადახდები', payment: 'გადახდა', imports: 'იმპორტები', import: 'იმპორტი', client: 'კლიენტი', user: 'მომხმარებელი',
    personal_number: 'პირადი ნომერი', phone: 'ტელეფონი', purpose: 'დანიშნულება', project: 'პროექტი', number: 'ბინა',
    floor: 'სართული', area: 'ფართი (მ²)', price_GEL: 'ფასი', apartment: 'ბინა', date: 'თარიღი', due_date: 'გადახდის ვადა',
    sequence: 'შენატანი', status: 'სტატუსი', amount_GEL: 'თანხა', paid_GEL: 'დაფარული', remaining_GEL: 'ნაშთი',
    allocated_GEL: 'განაწილებული', unallocated_GEL: 'გაუნაწილებელი', overdue_GEL: 'ვადაგადაცილება', payer: 'გადამხდელი',
    bank_reference: 'საბანკო ნომერი', client_match: 'კლიენტთან კავშირი', included_as_of: 'თარიღში გათვალისწინება',
    as_of: 'საანგარიშო თარიღი', filename: 'ფაილი', total: 'სულ', imported: 'ჩაწერილი', duplicates: 'დუბლიკატები',
    rejected: 'უარყოფილი', error: 'შეცდომა', row: 'Excel-ის რიგი', message: 'შედეგი',
};
const columnsBySection: Record<string, string[]> = {
    overdue: ['client', 'contract', 'due_date', 'overdue_GEL'],
    apartments: ['project', 'number', 'floor', 'area', 'price_GEL', 'status', 'contract'],
    contracts: ['contract', 'client', 'price_GEL'],
    contract: ['sequence', 'due_date', 'amount_GEL', 'paid_GEL', 'remaining_GEL', 'status', 'payments'],
    payments: ['bank_reference', 'date', 'payer', 'client', 'amount_GEL', 'client_match'],
    payment: ['contract', 'due_date', 'allocated_GEL'],
    imports: ['filename', 'user', 'status', 'total', 'imported', 'duplicates', 'rejected'],
    import: ['row', 'bank_reference', 'status', 'message'],
};
const sorting: Record<string, { value: string; label: string }[]> = {
    apartments: [{ value: 'id', label: 'დამატების რიგით' }, { value: 'price', label: 'ფასით' }],
    contracts: [{ value: 'id', label: 'დამატების რიგით' }, { value: 'date', label: 'თარიღით' }, { value: 'price', label: 'ფასით' }],
    payments: [{ value: 'date', label: 'თარიღით' }, { value: 'amount', label: 'თანხით' }],
    overdue: [{ value: 'date', label: 'ვადით' }, { value: 'amount', label: 'დავალიანებით' }],
    contract: [{ value: 'date', label: 'ვადით' }, { value: 'amount', label: 'თანხით' }],
    payment: [{ value: 'id', label: 'განაწილების რიგით' }, { value: 'amount', label: 'თანხით' }],
    imports: [{ value: 'id', label: 'დამატების რიგით' }, { value: 'date', label: 'თარიღით' }],
    import: [{ value: 'id', label: 'ფაილის რიგით' }],
};
const importOptions = [
    { value: 'queued', label: 'რიგშია' }, { value: 'processing', label: 'მუშავდება' },
    { value: 'completed', label: 'დასრულებული' }, { value: 'completed_with_errors', label: 'დასრულებული შეცდომებით' },
    { value: 'failed', label: 'შეჩერებული' },
];

function ImportProgress() {
    usePoll(2000, { only: ['rows', 'links', 'total', 'summary', 'importStatus'] });
    return <p role="status" className="text-sm text-gray-600">ფაილი მუშავდება. შედეგები ავტომატურად განახლდება.</p>;
}

export default function Workspace({ section, filters, rows, links, total, summary, importStatus, auth, flash }: PageProps<WorkspaceData>) {
    const sortOptions = sorting[section] ?? sorting.contracts;
    const search = useForm({
        q: String(filters.q ?? ''), status: String(filters.status ?? ''), project_id: String(filters.project_id ?? ''),
        client_id: String(filters.client_id ?? ''), as_of: String(filters.as_of ?? ''),
        sort: String(filters.sort ?? sortOptions[0].value), direction: String(filters.direction ?? 'asc'),
        import_status: String(filters.import_status ?? ''),
        client_match: String(filters.client_match ?? ''),
    });
    const upload = useForm<{ file: File | null }>({ file: null });
    const columns = columnsBySection[section] ?? [];
    const detail = ['contract', 'payment', 'import'].includes(section);
    const processing = ['queued', 'processing'].includes(importStatus ?? '');
    const financial = Object.entries(summary).filter(([key]) => key.endsWith('_GEL'));
    const context = Object.entries(summary).filter(([key]) => !key.endsWith('_GEL') && key !== 'as_of' && !(section === 'contract' && key === 'contract')
        && !(key === 'personal_number' && typeof summary.client === 'object' && summary.client !== null));
    const contractName = summary.contract;
    const title = section === 'contract' && contractName && typeof contractName === 'object' && !Array.isArray(contractName)
        ? contractName.label : section === 'payment' ? `გადახდა — ${summary.bank_reference}` : labels[section];
    const canEdit = section === 'apartments' && auth.permissions.includes('apartments.update') && rows.some((row) => typeof row._edit_url === 'string');

    function handleSearch(event: FormEvent<HTMLFormElement>) {
        event.preventDefault();
        search.get(window.location.pathname, { preserveState: true });
    }
    function handleUpload(event: FormEvent<HTMLFormElement>) {
        event.preventDefault();
        upload.post(route('imports.store'), { forceFormData: true, preserveScroll: true });
    }

    return <AuthenticatedLayout header={<div lang="ka">{section === 'contract' && <p className="mb-1 text-xs font-medium text-gray-500">ხელშეკრულება</p>}<h1 className="text-xl font-semibold text-ink">{title}</h1></div>}>
        <Head title={title} />
        <div lang="ka" className="mx-auto flex max-w-7xl flex-col gap-6 px-4 py-7 sm:px-6 lg:px-8">
            <div className="flex flex-wrap gap-2 empty:hidden">
                {section === 'apartments' && auth.permissions.includes('apartments.create') && <Link href={route('apartments.create')} className="rounded-lg bg-brand px-4 py-3 text-sm text-white hover:bg-brand-hover">ახალი ბინა</Link>}
                {['contracts', 'apartments'].includes(section) && auth.permissions.includes('contracts.create') && <Link href={route('contracts.create')} className={`rounded-lg px-4 py-3 text-sm ${section === 'contracts' ? 'bg-brand text-white hover:bg-brand-hover' : 'border border-gray-300 bg-white text-gray-700 hover:border-brand hover:text-brand'}`}>ახალი ხელშეკრულება</Link>}
                {section === 'apartments' && auth.permissions.includes('projects.create') && <Link href={route('projects.create')} className="rounded-lg border border-gray-300 bg-white px-4 py-3 text-sm text-gray-700 hover:border-brand hover:text-brand">ახალი პროექტი</Link>}
            </div>
            {flash.success && <p role="status" className="rounded-lg bg-green-50 p-4 text-green-800">{flash.success}</p>}
            {flash.error && <p role="alert" className="rounded-lg bg-red-50 p-4 text-red-800">{flash.error}</p>}
            {processing && <ImportProgress />}
            {financial.length > 0 && <dl className="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
                {financial.map(([key, value]) => <div key={key} className={`rounded-xl border p-5 ${key === 'overdue_GEL' ? 'border-red-100 bg-red-50 text-red-700' : key === 'paid_GEL' ? 'border-green-100 bg-green-50 text-green-800' : 'border-gray-200 bg-white text-ink'}`}>
                    <dt className="text-sm opacity-80">{labels[key]}</dt>
                    <dd className="mt-2 text-2xl font-semibold"><DataValue field={key} value={value} permissions={auth.permissions} /></dd>
                </div>)}
            </dl>}
            {context.length > 0 && <dl className="grid gap-x-8 gap-y-5 rounded-xl border border-gray-200 bg-white p-6 sm:grid-cols-2 lg:grid-cols-3">
                {context.map(([key, value]) => <div key={key} className={key === 'purpose' || key === 'error' ? 'sm:col-span-2' : ''}>
                    <dt className="text-xs font-medium text-gray-500">{labels[key] ?? key}</dt>
                    <dd className="mt-1 break-words text-sm text-ink"><DataValue field={key} value={value} permissions={auth.permissions} /></dd>
                </div>)}
            </dl>}
            <form onSubmit={handleSearch} className="flex flex-wrap items-end gap-4 rounded-xl border border-gray-200 bg-white p-5">
                {!detail && <label className="flex min-w-0 flex-1 flex-col gap-1.5 text-sm font-medium text-gray-700">ძიება
                    <input type="search" maxLength={100} className="min-h-11 min-w-48 rounded-lg border-gray-300 font-normal focus:border-brand focus:ring-brand" placeholder={section === 'imports' ? 'ფაილის სახელი' : 'სახელი, პირადი ნომერი ან ნომერი'} value={search.data.q} onChange={(event) => search.setData('q', event.target.value)} />
                </label>}
                {['overdue', 'contract', 'payment'].includes(section) && <label className="flex flex-col gap-1.5 text-sm font-medium text-gray-700">საანგარიშო თარიღი
                    <input type="date" className="min-h-11 rounded-lg border-gray-300 text-sm font-normal focus:border-brand focus:ring-brand" value={search.data.as_of} onChange={(event) => search.setData('as_of', event.target.value)} />
                </label>}
                {section === 'apartments' && <SelectInput className="w-full sm:w-44" label="ბინის სტატუსი" value={search.data.status} onChange={(value) => search.setData('status', value)} placeholder="ყველა სტატუსი" options={[{ value: 'free', label: 'თავისუფალი' }, { value: 'sold', label: 'გაყიდული' }]} error={search.errors.status} />}
                {section === 'imports' && <SelectInput className="w-full sm:w-52" label="იმპორტის სტატუსი" value={search.data.import_status} onChange={(value) => search.setData('import_status', value)} placeholder="ყველა სტატუსი" options={importOptions} error={search.errors.import_status} />}
                {section === 'payments' && <SelectInput className="w-full sm:w-48" label="კლიენტთან კავშირი" value={search.data.client_match} onChange={(value) => search.setData('client_match', value)} placeholder="ყველა გადახდა" options={[{ value: 'matched', label: 'დაკავშირებული' }, { value: 'unmatched', label: 'დაუკავშირებელი' }]} error={search.errors.client_match} />}
                <SelectInput className="w-full sm:w-44" label="დალაგება" value={search.data.sort} onChange={(value) => search.setData('sort', value)} options={sortOptions} error={search.errors.sort} />
                <SelectInput className="w-full sm:w-40" label="მიმართულება" value={search.data.direction} onChange={(value) => search.setData('direction', value)} options={[{ value: 'asc', label: 'ზრდადობით' }, { value: 'desc', label: 'კლებადობით' }]} error={search.errors.direction} />
                <PrimaryButton disabled={search.processing} className="min-h-11">{search.processing ? 'იტვირთება…' : 'ძებნა'}</PrimaryButton>
            </form>
            {Object.entries(search.errors).filter(([key]) => !['sort', 'direction', 'status', 'import_status', 'client_match'].includes(key)).map(([key, error]) => <p key={key} role="alert" className="text-sm text-red-600">{error}</p>)}
            {section === 'imports' && auth.permissions.includes('payments.import') && <form onSubmit={handleUpload} className="flex flex-wrap items-end gap-4 rounded-xl border border-gray-200 bg-white p-5">
                <label className="flex flex-col gap-2 text-sm">გადახდების XLSX ფაილი (მაქსიმუმ 5 MiB)<input type="file" accept=".xlsx" onChange={(event) => upload.setData('file', event.target.files?.[0] ?? null)} /></label>
                <PrimaryButton className="min-h-11" disabled={upload.processing || !upload.data.file}>{upload.processing ? 'იტვირთება…' : 'ატვირთვა'}</PrimaryButton>
                {upload.errors.file && <p role="alert" className="text-sm text-red-600">{upload.errors.file}</p>}
                {upload.progress && <p role="status">{upload.progress.percentage}%</p>}
            </form>}
            <section aria-label="შედეგები" aria-busy={search.processing} className="overflow-hidden rounded-xl border border-gray-200 bg-white">
                <p className="border-b border-gray-100 px-6 py-4 text-sm text-gray-500">ჩანაწერები: <span className="font-semibold text-ink">{total}</span></p>
                {rows.length === 0 ? <p className="p-6 text-sm text-gray-500">ჩანაწერები ვერ მოიძებნა.</p> : <div className="overflow-x-auto"><table className="w-full text-left text-sm">
                    <caption className="sr-only">{labels[section]}</caption>
                    <thead className="bg-gray-50 text-xs font-medium text-gray-500"><tr>
                        {columns.map((key) => <th key={key} scope="col" className={`whitespace-nowrap px-5 py-3.5 ${key.endsWith('_GEL') ? 'text-right' : ''}`}>{labels[key] ?? key}</th>)}
                        {canEdit && <th scope="col" className="px-5 py-3.5">მოქმედება</th>}
                    </tr></thead>
                    <tbody className="divide-y divide-gray-100">{rows.map((row, index) => <tr key={String(row.id ?? index)} className="hover:bg-gray-50/60">
                        {columns.map((key) => <td key={key} className={`px-5 py-4 align-top ${key.endsWith('_GEL') ? `whitespace-nowrap text-right font-medium ${key === 'overdue_GEL' ? 'text-red-700' : key === 'paid_GEL' ? 'text-green-800' : 'text-ink'}` : key === 'message' ? 'min-w-48 max-w-sm break-words' : ''}`}>
                            <DataValue field={key} value={key === 'payments' ? row._links : row[key]} permissions={auth.permissions} />
                            {key === 'payer' && typeof row.personal_number === 'string' && <p className="mt-1 text-xs text-gray-500">{row.personal_number}</p>}
                        </td>)}
                        {canEdit && <td className="px-5 py-4">{typeof row._edit_url === 'string' && <Link href={row._edit_url} className="inline-flex min-h-11 items-center text-brand underline underline-offset-4">რედაქტირება</Link>}</td>}
                    </tr>)}</tbody>
                </table></div>}
                {links.filter((link) => /^\d+$/.test(link.label)).length > 1 && <nav aria-label="შედეგების გვერდები" className="flex flex-wrap gap-2 border-t border-gray-100 p-4">
                    {links.map((link, index) => {
                        const label = link.label.includes('Previous') ? 'წინა' : link.label.includes('Next') ? 'შემდეგი' : link.label;
                        return link.url ? <Link key={index} href={link.url} aria-current={link.active ? 'page' : undefined} className={`inline-flex min-h-11 items-center rounded-lg border px-4 text-sm focus:outline-none focus:ring-2 focus:ring-brand ${link.active ? 'border-brand bg-brand text-white' : 'border-gray-200 text-gray-600 hover:border-brand hover:text-brand'}`}>{label}</Link>
                            : <span key={index} aria-disabled="true" className="inline-flex min-h-11 items-center px-4 text-sm text-gray-300">{label}</span>;
                    })}
                </nav>}
            </section>
        </div>
    </AuthenticatedLayout>;
}
