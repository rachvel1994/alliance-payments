import InputError from '@/Components/InputError';
import InputLabel from '@/Components/InputLabel';
import PrimaryButton from '@/Components/PrimaryButton';
import SelectInput from '@/Components/SelectInput';
import TextInput from '@/Components/TextInput';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { PageProps } from '@/types';
import { Head, Link, router, useForm } from '@inertiajs/react';
import { FormEvent, useState } from 'react';

type Client = {
    id: number;
    name: string;
    personal_number: string;
    phone: string | null;
    contracts_count: number;
};

type Filters = {
    q: string;
    sort: 'name' | 'id';
    direction: 'asc' | 'desc';
};

type ClientsPage = {
    data: Client[];
    total: number;
    from: number | null;
    to: number | null;
    current_page: number;
    last_page: number;
    prev_page_url: string | null;
    next_page_url: string | null;
};

const pageLinkClass =
    'inline-flex min-h-11 items-center rounded-md border border-gray-300 px-4 text-sm font-medium focus:outline-none focus:ring-2 focus:ring-brand focus:ring-offset-2';

export default function Index({
    auth,
    flash,
    clients,
    filters,
}: PageProps<{ clients: ClientsPage; filters: Filters }>) {
    const form = useForm<Filters>(filters);
    const [reallocatingId, setReallocatingId] = useState<number | null>(null);
    const canReallocate = auth.permissions.includes('payments.reallocate');

    function handleSearch(event: FormEvent<HTMLFormElement>) {
        event.preventDefault();
        form.get(route('clients.index'), { preserveState: true });
    }

    function handleReallocate(clientId: number) {
        router.post(route('clients.reallocate', clientId), {}, {
            preserveScroll: true,
            onStart: () => setReallocatingId(clientId),
            onFinish: () => setReallocatingId(null),
        });
    }

    return (
        <AuthenticatedLayout
            header={<h1 className="text-xl font-semibold text-gray-800">კლიენტები</h1>}
        >
            <Head title="კლიენტები" />

            <div className="py-8">
                <div className="mx-auto flex max-w-7xl flex-col gap-6 px-4 sm:px-6 lg:px-8">
                    {auth.permissions.includes('clients.create') && (
                        <Link href={route('clients.create')} className="self-start rounded-md bg-brand hover:bg-brand-hover px-4 py-3 text-sm text-white">ახალი კლიენტი</Link>
                    )}
                    {flash.success && (
                        <div role="status" className="rounded-md border border-green-200 bg-green-50 p-4 text-sm text-green-800">
                            {flash.success}
                        </div>
                    )}
                    {flash.error && (
                        <div role="alert" className="rounded-md border border-red-200 bg-red-50 p-4 text-sm text-red-800">
                            {flash.error}
                        </div>
                    )}

                    <section className="rounded-lg bg-white p-6 shadow-sm" aria-label="კლიენტების ძიება">
                        <form onSubmit={handleSearch} className="grid gap-4 sm:grid-cols-2 lg:grid-cols-4 lg:items-end">
                            <div>
                                <InputLabel htmlFor="client-search" value="სახელი ან პირადი ნომერი" />
                                <TextInput
                                    id="client-search"
                                    type="search"
                                    className="mt-1 min-h-11 w-full"
                                    value={form.data.q}
                                    maxLength={100}
                                    onChange={(event) => form.setData('q', event.target.value)}
                                    aria-invalid={Boolean(form.errors.q)}
                                    aria-describedby={form.errors.q ? 'client-search-error' : undefined}
                                />
                                <InputError id="client-search-error" message={form.errors.q} className="mt-2" />
                            </div>
                            <SelectInput id="client-sort" label="დალაგება" value={form.data.sort} onChange={(value) => form.setData('sort', value as Filters['sort'])}
                                options={[{ value: 'name', label: 'სახელით' }, { value: 'id', label: 'დამატების რიგით' }]} error={form.errors.sort} />
                            <SelectInput id="client-direction" label="მიმართულება" value={form.data.direction} onChange={(value) => form.setData('direction', value as Filters['direction'])}
                                options={[{ value: 'asc', label: 'ზრდადობით' }, { value: 'desc', label: 'კლებადობით' }]} error={form.errors.direction} />
                            <div className="flex min-h-11 items-center gap-4">
                                <PrimaryButton disabled={form.processing} className="min-h-11">
                                    {form.processing ? 'იტვირთება…' : 'ძებნა'}
                                </PrimaryButton>
                                <Link href={route('clients.index')} className="text-sm text-gray-600 underline focus:outline-none focus:ring-2 focus:ring-brand">
                                    გასუფთავება
                                </Link>
                            </div>
                        </form>
                    </section>

                    <section className="overflow-hidden rounded-lg bg-white shadow-sm" aria-label="ძიების შედეგები" aria-busy={form.processing || reallocatingId !== null}>
                        <div className="border-b border-gray-200 px-6 py-4 text-sm text-gray-600">
                            სულ {clients.total} კლიენტი
                        </div>
                        {clients.data.length === 0 ? (
                            <p className="p-6 text-gray-600">კლიენტები არ მოიძებნა. შეცვალე საძიებო ტექსტი ან გაასუფთავე ფილტრები.</p>
                        ) : (
                            <div className="overflow-x-auto">
                                <table className="w-full text-left text-sm">
                                    <caption className="sr-only">კლიენტების სია</caption>
                                    <thead className="border-b border-gray-200 bg-gray-50 text-gray-600">
                                        <tr>
                                            <th scope="col" className="px-6 py-3">სახელი</th>
                                            <th scope="col" className="px-6 py-3">პირადი ნომერი</th>
                                            <th scope="col" className="px-6 py-3">ტელეფონი</th>
                                            <th scope="col" className="px-6 py-3">ხელშეკრულებები</th>
                                            {auth.permissions.includes('clients.update') && <th scope="col" className="px-6 py-3">რედაქტირება</th>}
                                            {canReallocate && <th scope="col" className="px-6 py-3">მოქმედება</th>}
                                        </tr>
                                    </thead>
                                    <tbody className="divide-y divide-gray-100">
                                        {clients.data.map((client) => (
                                            <tr key={client.id}>
                                                <th scope="row" className="px-6 py-4 font-medium text-gray-900">{auth.permissions.includes('contracts.view') ? <Link href={route('contracts.index', { client_id: client.id })} className="text-brand underline underline-offset-4">{client.name}</Link> : client.name}</th>
                                                <td className="whitespace-nowrap px-6 py-4 text-gray-700">{client.personal_number}</td>
                                                <td className="whitespace-nowrap px-6 py-4 text-gray-700">{client.phone && /\d/.test(client.phone) ? <a href={`tel:${client.phone.replace(/[^\d+]/g, '')}`} className="underline underline-offset-4">{client.phone}</a> : client.phone ?? '—'}</td>
                                                <td className="px-6 py-4 text-gray-700">
                                                    {auth.permissions.includes('contracts.view') ? (
                                                        <Link href={route('contracts.index', { client_id: client.id })} className="underline focus:outline-none focus:ring-2 focus:ring-brand">
                                                            {client.contracts_count}
                                                        </Link>
                                                    ) : client.contracts_count}
                                                </td>
                                                {auth.permissions.includes('clients.update') && (
                                                    <td className="px-6 py-4"><Link href={route('clients.edit', client.id)} className="underline">რედაქტირება</Link></td>
                                                )}
                                                {canReallocate && (
                                                    <td className="px-6 py-4">
                                                        <PrimaryButton
                                                            type="button"
                                                            className="min-h-11 whitespace-nowrap"
                                                            disabled={reallocatingId !== null}
                                                            aria-label={`${client.name} — განაწილების განახლება`}
                                                            onClick={() => handleReallocate(client.id)}
                                                        >
                                                            {reallocatingId === client.id ? 'მიმდინარეობს…' : 'გადათვლა'}
                                                        </PrimaryButton>
                                                    </td>
                                                )}
                                            </tr>
                                        ))}
                                    </tbody>
                                </table>
                            </div>
                        )}

                        {clients.last_page > 1 && <nav aria-label="კლიენტების გვერდები" className="flex flex-wrap items-center justify-between gap-4 border-t border-gray-200 px-6 py-4">
                            <p className="text-sm text-gray-600">{clients.from ?? 0}–{clients.to ?? 0} / {clients.total}</p>
                            <div className="flex items-center gap-3">
                                {clients.prev_page_url ? (
                                    <Link href={clients.prev_page_url} className={pageLinkClass}>წინა</Link>
                                ) : (
                                    <span aria-disabled="true" className={`${pageLinkClass} text-gray-400`}>წინა</span>
                                )}
                                <span aria-current="page" className="text-sm text-gray-600">{clients.current_page} / {clients.last_page}</span>
                                {clients.next_page_url ? (
                                    <Link href={clients.next_page_url} className={pageLinkClass}>შემდეგი</Link>
                                ) : (
                                    <span aria-disabled="true" className={`${pageLinkClass} text-gray-400`}>შემდეგი</span>
                                )}
                            </div>
                        </nav>}
                    </section>
                </div>
            </div>
        </AuthenticatedLayout>
    );
}
