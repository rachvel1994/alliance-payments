import PrimaryButton from '@/Components/PrimaryButton';
import { useForm } from '@inertiajs/react';
import { FormEvent } from 'react';

export default function ImportUpload() {
    const upload = useForm<{ file: File | null }>({ file: null });

    function handleUpload(event: FormEvent<HTMLFormElement>) {
        event.preventDefault();
        upload.post(route('imports.store'), { forceFormData: true, preserveScroll: true });
    }

    return (
        <form
            onSubmit={handleUpload}
            className="flex flex-wrap items-end gap-4 rounded-xl border border-gray-200 bg-white p-5"
        >
            <label className="flex w-full min-w-0 flex-col gap-2 text-sm sm:w-auto">
                გადახდების XLSX ფაილი (მაქსიმუმ 5 MiB)
                <input
                    type="file"
                    accept=".xlsx"
                    className="max-w-full text-sm"
                    onChange={(event) => upload.setData('file', event.target.files?.[0] ?? null)}
                />
            </label>
            <PrimaryButton className="min-h-11" disabled={upload.processing || !upload.data.file}>
                {upload.processing ? 'იტვირთება…' : 'ატვირთვა'}
            </PrimaryButton>
            {upload.errors.file && (
                <p role="alert" className="text-sm text-red-600">
                    {upload.errors.file}
                </p>
            )}
            {upload.progress && <p role="status">{upload.progress.percentage}%</p>}
        </form>
    );
}
