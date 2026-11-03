import InputError from '@/Components/InputError';
import PrimaryButton from '@/Components/PrimaryButton';
import SelectInput from '@/Components/SelectInput';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { PageProps } from '@/types';
import { Head, Link, useForm } from '@inertiajs/react';
import { FormEvent } from 'react';

type Apartment = { id: number; project_id: number; number: string; floor: number; area: string; price: string; status: string; contract_exists: boolean };
type Project = { id: number; name: string; city: string };
const control = 'mt-1 block w-full rounded-md border-gray-300 focus:border-brand focus:ring-brand';

export default function Form({ apartment, projects, selectedProjectId, auth, flash }: PageProps<{ apartment: Apartment | null; projects: Project[]; selectedProjectId: number | null }>) {
    const title = apartment ? 'ბინის რედაქტირება' : 'ახალი ბინა';
    const editable = !apartment || (apartment.status === 'free' && !apartment.contract_exists);
    const form = useForm({
        project_id: String(apartment?.project_id ?? selectedProjectId ?? ''), number: apartment?.number ?? '',
        floor: String(apartment?.floor ?? ''), area: apartment?.area ?? '', price: apartment?.price ?? '',
    });

    function submit(event: FormEvent<HTMLFormElement>) {
        event.preventDefault();
        if (apartment) form.patch(route('apartments.update', apartment.id));
        else form.post(route('apartments.store'));
    }

    return <AuthenticatedLayout header={<h1 className="text-xl font-semibold">{title}</h1>}>
        <Head title={title} />
        <div lang="ka" className="mx-auto max-w-2xl space-y-6 px-4 py-8">
            <Link href={route('apartments.index')} className="underline">ბინებში დაბრუნება</Link>
            {flash.success && <p role="status" className="rounded-md bg-green-50 p-4 text-green-800">{flash.success}</p>}
            {!apartment && auth.permissions.includes('projects.create') && <Link href={route('projects.create')} className="block underline">ახალი პროექტის დამატება</Link>}
            {!editable && <p role="status" className="rounded-md bg-amber-50 p-4 text-amber-900">გაყიდული ბინის რედაქტირება დაუშვებელია.</p>}
            {!projects.length && <p role="status">ბინის დამატებისთვის საჭიროა პროექტი.</p>}
            <form onSubmit={submit} className="rounded-lg bg-white p-6 shadow-sm">
                <fieldset disabled={!editable || form.processing || !projects.length} className="space-y-5">
                    <SelectInput label="პროექტი" searchable required disabled={!editable || form.processing || !projects.length} placeholder="მოძებნე პროექტი ან ქალაქი"
                        value={form.data.project_id} onChange={(value) => form.setData('project_id', value)} error={form.errors.project_id}
                        options={projects.map((project) => ({ value: String(project.id), label: project.name, description: project.city }))} />
                    {([['number', 'ბინის ნომერი'], ['floor', 'სართული'], ['area', 'ფართი (მ²)'], ['price', 'ბინის ფასი (₾)']] as const).map(([key, label]) => <label key={key} className="block text-sm">{label}
                        <input required className={control} maxLength={key === 'number' ? 255 : undefined}
                            inputMode={key === 'floor' ? 'numeric' : key === 'number' ? 'text' : 'decimal'}
                            value={form.data[key]} onChange={(e) => form.setData(key, e.target.value)} />
                        <InputError message={form.errors[key]} />
                    </label>)}
                    <p className="text-sm text-gray-600">ბინა იქმნება თავისუფალი სტატუსით. გაყიდულად მონიშვნა ხელშეკრულების შექმნისას ხდება.</p>
                    <PrimaryButton className="min-h-11">{form.processing ? 'ინახება…' : 'შენახვა'}</PrimaryButton>
                </fieldset>
            </form>
        </div>
    </AuthenticatedLayout>;
}
