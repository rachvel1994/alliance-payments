import InputError from '@/Components/InputError';
import PrimaryButton from '@/Components/PrimaryButton';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { PageProps } from '@/types';
import { Head, Link, useForm } from '@inertiajs/react';
import { FormEvent } from 'react';

export default function Create({ auth, flash }: PageProps) {
    const form = useForm({ name: '', city: '' });

    function submit(event: FormEvent<HTMLFormElement>) {
        event.preventDefault();
        form.post(route('projects.store'));
    }

    return <AuthenticatedLayout header={<h1 className="text-xl font-semibold">ახალი პროექტი</h1>}>
        <Head title="ახალი პროექტი" />
        <div lang="ka" className="mx-auto max-w-2xl space-y-6 px-4 py-8">
            {auth.permissions.includes('apartments.view') && <Link href={route('apartments.index')} className="underline">ბინებში დაბრუნება</Link>}
            {flash.success && <p role="status" className="rounded-md bg-green-50 p-4 text-green-800">{flash.success}</p>}
            <form onSubmit={submit} className="space-y-5 rounded-lg bg-white p-6 shadow-sm">
                {([['name', 'პროექტის სახელი'], ['city', 'ქალაქი']] as const).map(([key, label]) => <label key={key} className="block text-sm">{label}
                    <input required maxLength={255} className="mt-1 block w-full rounded-md border-gray-300 focus:border-indigo-500 focus:ring-indigo-500"
                        value={form.data[key]} onChange={(e) => form.setData(key, e.target.value)} />
                    <InputError message={form.errors[key]} />
                </label>)}
                <PrimaryButton className="min-h-11" disabled={form.processing}>{form.processing ? 'ინახება…' : 'პროექტის დამატება'}</PrimaryButton>
            </form>
        </div>
    </AuthenticatedLayout>;
}
