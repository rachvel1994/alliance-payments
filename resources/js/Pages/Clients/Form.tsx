import InputError from '@/Components/InputError';
import PrimaryButton from '@/Components/PrimaryButton';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { PageProps } from '@/types';
import { Head, Link, useForm } from '@inertiajs/react';
import { FormEvent } from 'react';

type Client = { id: number; name: string; personal_number: string; phone: string | null };

export default function Form({ client }: PageProps<{ client: Client | null }>) {
    const title = client ? 'კლიენტის რედაქტირება' : 'ახალი კლიენტი';
    const form = useForm({ name: client?.name ?? '', personal_number: client?.personal_number ?? '', phone: client?.phone ?? '' });

    function submit(event: FormEvent<HTMLFormElement>) {
        event.preventDefault();
        if (client) form.patch(route('clients.update', client.id));
        else form.post(route('clients.store'));
    }

    return <AuthenticatedLayout header={<h1 className="text-xl font-semibold">{title}</h1>}>
        <Head title={title} />
        <div lang="ka" className="mx-auto max-w-2xl space-y-6 px-4 py-8">
            <Link href={route('clients.index')} className="underline">კლიენტებში დაბრუნება</Link>
            <form onSubmit={submit} className="space-y-5 rounded-lg bg-white p-6 shadow-sm">
                {([['name', 'კლიენტის სახელი'], ['personal_number', 'პირადი ნომერი'], ['phone', 'ტელეფონი']] as const).map(([key, label]) => <label key={key} className="block text-sm">{label}
                    <input className="mt-1 block w-full rounded-md border-gray-300 focus:border-indigo-500 focus:ring-indigo-500"
                        required={key !== 'phone'} maxLength={key === 'personal_number' ? 11 : 255}
                        inputMode={key === 'personal_number' ? 'numeric' : key === 'phone' ? 'tel' : 'text'}
                        value={form.data[key]} onChange={(e) => form.setData(key, e.target.value)} />
                    <InputError message={form.errors[key]} />
                </label>)}
                {client && <p className="text-sm text-gray-600">პირადი ნომრის შეცვლა შესაძლებელია მხოლოდ ხელშეკრულებისა და დაკავშირებული გადახდების გარეშე.</p>}
                <PrimaryButton className="min-h-11" disabled={form.processing}>{form.processing ? 'ინახება…' : 'შენახვა'}</PrimaryButton>
            </form>
        </div>
    </AuthenticatedLayout>;
}
