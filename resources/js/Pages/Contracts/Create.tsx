import InputError from '@/Components/InputError';
import PrimaryButton from '@/Components/PrimaryButton';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { PageProps } from '@/types';
import { Head, Link, useForm } from '@inertiajs/react';
import { FormEvent } from 'react';

type Options = {
    clients: { id: number; name: string; personal_number: string }[];
    apartments: { id: number; label: string; price: string }[];
    today: string;
};
const control = 'mt-1 block w-full rounded-md border-gray-300 focus:border-indigo-500 focus:ring-indigo-500';

export default function Create({ clients, apartments, today }: PageProps<Options>) {
    const form = useForm({
        client_id: '', apartment_id: '', contract_date: today, price: '',
        first_due_date: today, installments_count: '12',
    });

    function submit(event: FormEvent<HTMLFormElement>) {
        event.preventDefault();
        form.post(route('contracts.store'));
    }

    return <AuthenticatedLayout header={<h1 className="text-xl font-semibold">ახალი ხელშეკრულება</h1>}>
        <Head title="ახალი ხელშეკრულება" />
        <div lang="ka" className="mx-auto max-w-3xl space-y-6 px-4 py-8">
            <Link href={route('contracts.index')} className="underline">ხელშეკრულებებში დაბრუნება</Link>
            {(!clients.length || !apartments.length) && <p role="status" className="rounded-md bg-amber-50 p-4 text-amber-900">შექმნისთვის საჭიროა კლიენტი და თავისუფალი ბინა.</p>}
            <form onSubmit={submit} className="space-y-5 rounded-lg bg-white p-6 shadow-sm">
                <label className="block text-sm">კლიენტი
                    <select required className={control} value={form.data.client_id} onChange={(e) => form.setData('client_id', e.target.value)}>
                        <option value="">აირჩიე კლიენტი</option>
                        {clients.map((client) => <option key={client.id} value={client.id}>{client.name} — {client.personal_number}</option>)}
                    </select><InputError message={form.errors.client_id} />
                </label>
                <label className="block text-sm">თავისუფალი ბინა
                    <select required className={control} value={form.data.apartment_id} onChange={(e) => {
                        const apartment = apartments.find((item) => String(item.id) === e.target.value);
                        form.setData({ ...form.data, apartment_id: e.target.value, price: apartment?.price ?? '' });
                    }}>
                        <option value="">აირჩიე ბინა</option>
                        {apartments.map((apartment) => <option key={apartment.id} value={apartment.id}>{apartment.label} — {apartment.price} ₾</option>)}
                    </select><InputError message={form.errors.apartment_id} />
                </label>
                <label className="block text-sm">ხელშეკრულების ფასი (₾)
                    <input required inputMode="decimal" className={control} value={form.data.price} onChange={(e) => form.setData('price', e.target.value)} />
                    <InputError message={form.errors.price} />
                </label>
                <label className="block text-sm">ხელშეკრულების თარიღი
                    <input required type="date" className={control} value={form.data.contract_date} onChange={(e) => form.setData('contract_date', e.target.value)} />
                    <InputError message={form.errors.contract_date} />
                </label>
                <label className="block text-sm">პირველი შენატანის თარიღი
                    <input required type="date" min={form.data.contract_date} className={control} value={form.data.first_due_date} onChange={(e) => form.setData('first_due_date', e.target.value)} />
                    <InputError message={form.errors.first_due_date} />
                </label>
                <label className="block text-sm">ყოველთვიური შენატანების რაოდენობა
                    <input required type="number" min="1" max="360" className={control} value={form.data.installments_count} onChange={(e) => form.setData('installments_count', e.target.value)} />
                    <InputError message={form.errors.installments_count} />
                </label>
                <p className="text-sm text-gray-600">თანხა თანაბრად ნაწილდება; დარჩენილი თეთრები ბოლო შენატანს ემატება. მოკლე თვეში ვადა თვის ბოლო დღეა, შემდეგ თვეში კი საწყისი რიცხვი ბრუნდება.</p>
                <PrimaryButton className="min-h-11" disabled={form.processing || !clients.length || !apartments.length}>{form.processing ? 'ინახება…' : 'ხელშეკრულების შექმნა'}</PrimaryButton>
            </form>
        </div>
    </AuthenticatedLayout>;
}
