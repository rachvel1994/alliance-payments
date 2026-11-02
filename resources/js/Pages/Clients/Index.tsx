import InputError from '@/Components/InputError';
import InputLabel from '@/Components/InputLabel';
import PrimaryButton from '@/Components/PrimaryButton';
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
    'inline-flex min-h-11 items-center rounded-md border border-gray-300 px-4 text-sm font-medium focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2';

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
                            <div>
                                <InputLabel htmlFor="client-sort" value="დალაგება" />
                                <select
                                    id="client-sort"
                                    className="mt-1 min-h-11 w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
                                    value={form.data.sort}
                                    onChange={(event) => form.setData('sort', event.target.value as Filters['sort'])}
                                >
                                    <option value="name">სახელით</option>
                                    <option value="id">დამატების რიგით</option>
                                </select>
                                <InputError message={form.errors.sort} className="mt-2" />
                            </div>
                            <div>
                                <InputLabel htmlFor="client-direction" value="მიმართულება" />
                                <select
                                    id="client-direction"
                                    className="mt-1 min-h-11 w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
                                    value={form.data.direction}
                                    onChange={(event) => form.setData('direction', event.target.value as Filters['direction'])}
                                >
                                    <option value="asc">ზრდადობით</option>
                                    <option value="desc">კლებადობით</option>
                                </select>
                                <InputError message={form.errors.direction} className="mt-2" />
                            </div>
                            <div className="flex min-h-11 items-center gap-4">
                                <PrimaryButton disabled={form.processing} className="min-h-11">
                                    {form.processing ? 'იტვირთება…' : 'ძებნა'}
                                </PrimaryButton>
                                <Link href={route('clients.index')} className="text-sm text-gray-600 underline focus:outline-none focus:ring-2 focus:ring-indigo-500">
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
                                            {canReallocate && <th scope="col" className="px-6 py-3">მოქმედება</th>}
                                        </tr>
                                    </thead>
                                    <tbody className="divide-y divide-gray-100">
                                        {clients.data.map((client) => (
                                            <tr key={client.id}>
                                                <th scope="row" className="px-6 py-4 font-medium text-gray-900">{client.name}</th>
                                                <td className="whitespace-nowrap px-6 py-4 text-gray-700">{client.personal_number}</td>
                                                <td className="whitespace-nowrap px-6 py-4 text-gray-700">{client.phone ?? '—'}</td>
                                                <td className="px-6 py-4 text-gray-700">
                                                    {auth.permissions.includes('contracts.view') ? (
                                                        <Link href={route('contracts.index', { client_id: client.id })} className="underline focus:outline-none focus:ring-2 focus:ring-indigo-500">
                                                            {client.contracts_count}
                                                        </Link>
                                                    ) : client.contracts_count}
                                                </td>
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

                        <nav aria-label="კლიენტების გვერდები" className="flex flex-wrap items-center justify-between gap-4 border-t border-gray-200 px-6 py-4">
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
                        </nav>
                    </section>
                </div>
            </div>
        </AuthenticatedLayout>
    );
}
