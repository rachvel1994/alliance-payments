import PrimaryButton from '@/Components/PrimaryButton';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { PageProps } from '@/types';
import { Head, Link, useForm, usePoll } from '@inertiajs/react';
import { FormEvent } from 'react';

type EntryLink = { label: string; url: string };
type Cell = string | number | null | EntryLink[];
type Row = Record<string, Cell>;
type WorkspaceData = {
    section: string;
    filters: Record<string, string | number | null>;
    rows: Row[];
    links: { url: string | null; label: string; active: boolean }[];
    total: number;
    summary: Record<string, string | number | null>;
    importStatus: string | null;
};

const navigation = [
    ['overdue.index', 'ვადაგადაცილება', 'clients.view'],
    ['clients.index', 'კლიენტები', 'clients.view'],
    ['apartments.index', 'ბინები', 'apartments.view'],
    ['contracts.index', 'ხელშეკრულებები', 'contracts.view'],
    ['payments.index', 'გადახდები', 'payments.view'],
    ['imports.index', 'იმპორტები', 'imports.view'],
] as const;

const labels: Record<string, string> = {
    overdue: 'ვადაგადაცილება', apartments: 'ბინები', contracts: 'ხელშეკრულებები',
    contract: 'ხელშეკრულება', payments: 'გადახდები', payment: 'გადახდა',
    imports: 'იმპორტები', import: 'იმპორტი', id: '№', client: 'კლიენტი', user: 'მომხმარებელი',
    personal_number: 'პირადი ნომერი', project: 'პროექტი', city: 'ქალაქი', number: 'ბინა',
    floor: 'სართული', area: 'ფართი', price_GEL: 'ფასი (₾)', apartment: 'ბინა',
    date: 'თარიღი', due_date: 'გადახდის ვადა', sequence: 'შენატანი', status: 'სტატუსი',
    amount_GEL: 'თანხა (₾)', paid_GEL: 'დაფარული (₾)', remaining_GEL: 'ნაშთი (₾)',
    allocated_GEL: 'განაწილებული (₾)', unallocated_GEL: 'გაუნაწილებელი (₾)',
    overdue_GEL: 'ვადაგადაცილება (₾)', payer: 'გადამხდელი', bank_reference: 'საბანკო ნომერი',
    client_match: 'კლიენტთან კავშირი', included_as_of: 'თარიღში გათვალისწინება', as_of: 'საანგარიშო თარიღი',
    filename: 'ფაილი', total: 'სულ', imported: 'ჩაწერილი', duplicates: 'დუბლიკატები',
    rejected: 'უარყოფილი', error: 'შეცდომა', row: 'Excel-ის რიგი', message: 'შედეგი',
};
const statuses: Record<string, string> = {
    free: 'თავისუფალი', sold: 'გაყიდული', paid: 'დაფარული', overdue: 'ვადაგადაცილებული',
    partial: 'ნაწილობრივ დაფარული', pending: 'მოსალოდნელი', queued: 'რიგშია', processing: 'მუშავდება',
    completed: 'დასრულებული', completed_with_errors: 'დასრულებული შეცდომებით', failed: 'შეჩერებული',
    imported: 'ჩაწერილი', duplicate: 'დუბლიკატი', rejected: 'უარყოფილი',
    matched: 'დაკავშირებული', unmatched: 'დაუკავშირებელი', yes: 'კი', future: 'მომავალი გადახდა',
};
const controlClass = 'min-h-11 rounded-md border-gray-300 focus:border-indigo-500 focus:ring-indigo-500';

function cell(key: string, value: Cell) {
    if (Array.isArray(value)) return '';
    if (typeof value === 'string' && ['status', 'client_match', 'included_as_of'].includes(key)) {
        return statuses[value] ?? value;
    }
    return value ?? '—';
}

function ImportProgress() {
    usePoll(2000, { only: ['rows', 'links', 'total', 'summary', 'importStatus'] });
    return <p role="status" className="text-sm text-gray-600">ფაილი მუშავდება. შედეგები ავტომატურად განახლდება.</p>;
}

export default function Workspace({ section, filters, rows, links, total, summary, importStatus, auth, flash }: PageProps<WorkspaceData>) {
    const search = useForm({
        q: String(filters.q ?? ''), status: String(filters.status ?? ''),
        project_id: String(filters.project_id ?? ''), client_id: String(filters.client_id ?? ''),
        as_of: String(filters.as_of ?? ''), sort: String(filters.sort ?? ''),
        direction: String(filters.direction ?? 'asc'), import_status: String(filters.import_status ?? ''),
    });
    const upload = useForm<{ file: File | null }>({ file: null });
    const columns = Object.keys(rows[0] ?? {}).filter((key) => !key.startsWith('_'));
    const detail = ['contract', 'payment', 'import'].includes(section);
    const processing = ['queued', 'processing'].includes(importStatus ?? '');

    function handleSearch(event: FormEvent<HTMLFormElement>) {
        event.preventDefault();
        search.get(window.location.pathname, { preserveState: true });
    }
    function handleUpload(event: FormEvent<HTMLFormElement>) {
        event.preventDefault();
        upload.post(route('imports.store'), { forceFormData: true, preserveScroll: true });
    }

    return (
        <AuthenticatedLayout header={<h1 lang="ka" className="text-xl font-semibold text-gray-800">{labels[section]}</h1>}>
            <Head title={labels[section]} />
            <div lang="ka" className="mx-auto flex max-w-7xl flex-col gap-6 px-4 py-8 sm:px-6 lg:px-8">
                <nav aria-label="ფინანსური გვერდები" className="flex flex-wrap gap-4 text-sm">
                    {navigation.filter((item) => auth.permissions.includes(item[2])).map(([name, label]) => (
                        <Link key={name} href={route(name)} className="underline focus:outline-none focus:ring-2 focus:ring-indigo-500">{label}</Link>
                    ))}
                </nav>
                {flash.success && <p role="status" className="rounded-md bg-green-50 p-4 text-green-800">{flash.success}</p>}
                {flash.error && <p role="alert" className="rounded-md bg-red-50 p-4 text-red-800">{flash.error}</p>}
                {processing && <ImportProgress />}
                {Object.keys(summary).length > 0 && (
                    <dl className="grid gap-4 rounded-lg bg-white p-6 sm:grid-cols-2 lg:grid-cols-3">
                        {Object.entries(summary).map(([key, value]) => <div key={key}>
                            <dt className="text-sm text-gray-500">{labels[key] ?? key}</dt>
                            <dd className="mt-1 font-medium text-gray-900">{cell(key, value)}</dd>
                        </div>)}
                    </dl>
                )}
                <form onSubmit={handleSearch} className="flex flex-wrap items-end gap-4 rounded-lg bg-white p-6">
                    {!detail && <label className="flex flex-col gap-1 text-sm">ძიება
                        <input type="search" maxLength={100} className={controlClass} value={search.data.q} onChange={(event) => search.setData('q', event.target.value)} />
                    </label>}
                    {['overdue', 'contract', 'payment'].includes(section) && <label className="flex flex-col gap-1 text-sm">საანგარიშო თარიღი
                        <input type="date" className={controlClass} value={search.data.as_of} onChange={(event) => search.setData('as_of', event.target.value)} />
                    </label>}
                    {section === 'apartments' && <label className="flex flex-col gap-1 text-sm">ბინის სტატუსი
                        <select className={controlClass} value={search.data.status} onChange={(event) => search.setData('status', event.target.value)}>
                            <option value="">ყველა</option><option value="free">თავისუფალი</option><option value="sold">გაყიდული</option>
                        </select>
                    </label>}
                    {section === 'imports' && <label className="flex flex-col gap-1 text-sm">იმპორტის სტატუსი
                        <select className={controlClass} value={search.data.import_status} onChange={(event) => search.setData('import_status', event.target.value)}>
                            <option value="">ყველა</option>
                            {['queued', 'processing', 'completed', 'completed_with_errors', 'failed'].map((status) => <option key={status} value={status}>{statuses[status]}</option>)}
                        </select>
                    </label>}
                    <label className="flex flex-col gap-1 text-sm">მიმართულება
                        <select className={controlClass} value={search.data.direction} onChange={(event) => search.setData('direction', event.target.value)}>
                            <option value="asc">ზრდადობით</option><option value="desc">კლებადობით</option>
                        </select>
                    </label>
                    <PrimaryButton disabled={search.processing} className="min-h-11">{search.processing ? 'იტვირთება…' : 'ძებნა'}</PrimaryButton>
                </form>
                {Object.entries(search.errors).map(([key, error]) => <p key={key} role="alert" className="text-sm text-red-600">{error}</p>)}
                {section === 'imports' && auth.permissions.includes('payments.import') && (
                    <form onSubmit={handleUpload} className="flex flex-wrap items-end gap-4 rounded-lg bg-white p-6">
                        <label className="flex flex-col gap-2 text-sm">გადახდების XLSX ფაილი (მაქსიმუმ 5 MiB)
                            <input type="file" accept=".xlsx" onChange={(event) => upload.setData('file', event.target.files?.[0] ?? null)} />
                        </label>
                        <PrimaryButton className="min-h-11" disabled={upload.processing || !upload.data.file}>{upload.processing ? 'იტვირთება…' : 'ატვირთვა'}</PrimaryButton>
                        {upload.errors.file && <p role="alert" className="text-sm text-red-600">{upload.errors.file}</p>}
                        {upload.progress && <p role="status">{upload.progress.percentage}%</p>}
                    </form>
                )}
                <section aria-label="შედეგები" aria-busy={search.processing} className="overflow-hidden rounded-lg bg-white">
                    <p className="border-b border-gray-200 px-6 py-4 text-sm text-gray-600">ჩანაწერები: {total}</p>
                    {rows.length === 0 ? <p className="p-6 text-gray-600">ჩანაწერები ვერ მოიძებნა.</p> : (
                        <div className="overflow-x-auto"><table className="w-full text-left text-sm">
                            <caption className="sr-only">{labels[section]}</caption>
                            <thead className="bg-gray-50 text-gray-600"><tr>
                                {columns.map((key) => <th key={key} scope="col" className="whitespace-nowrap px-6 py-3">{labels[key] ?? key}</th>)}
                                <th scope="col" className="px-6 py-3">დეტალები</th>
                            </tr></thead>
                            <tbody className="divide-y divide-gray-100">{rows.map((row, index) => <tr key={String(row.id ?? index)}>
                                {columns.map((key) => <td key={key} className="px-6 py-4 align-top">{cell(key, row[key])}</td>)}
                                <td className="space-y-2 px-6 py-4">
                                    {typeof row._url === 'string' && <Link href={row._url} className="block underline">ნახვა</Link>}
                                    {Array.isArray(row._links) && row._links.map((link) => <Link key={link.url} href={link.url} className="block underline">{link.label}</Link>)}
                                </td>
                            </tr>)}</tbody>
                        </table></div>
                    )}
                    <nav aria-label="შედეგების გვერდები" className="flex flex-wrap gap-2 border-t border-gray-200 p-4">
                        {links.map((link, index) => {
                            const label = link.label.includes('Previous') ? 'წინა' : link.label.includes('Next') ? 'შემდეგი' : link.label;
                            return link.url ? <Link key={index} href={link.url} aria-current={link.active ? 'page' : undefined}
                                className={`inline-flex min-h-11 items-center rounded-md border px-4 focus:outline-none focus:ring-2 focus:ring-indigo-500 ${link.active ? 'bg-gray-800 text-white' : 'text-gray-700'}`}>{label}</Link>
                                : <span key={index} aria-disabled="true" className="inline-flex min-h-11 items-center px-4 text-gray-400">{label}</span>;
                        })}
                    </nav>
                </section>
            </div>
        </AuthenticatedLayout>
    );
}
