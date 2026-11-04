import Icon from '@/Components/Icon';
import PrimaryButton from '@/Components/PrimaryButton';
import { useForm } from '@inertiajs/react';
import { FormEvent, useId } from 'react';

export default function ImportUpload() {
    const upload = useForm<{ file: File | null }>({ file: null });
    const fileId = useId();

    function handleUpload(event: FormEvent<HTMLFormElement>) {
        event.preventDefault();
        upload.post(route('imports.store'), {
            forceFormData: true,
            preserveScroll: true,
        });
    }

    return (
        <form
            onSubmit={handleUpload}
            className="flex flex-wrap items-end gap-4 rounded-xl border border-gray-200 bg-white p-5"
        >
            <div className="min-w-0 flex-1 basis-72">
                <label id={`${fileId}-label`} htmlFor={fileId} className="mb-2 block text-sm font-medium text-gray-700">
                    გადახდების XLSX ფაილი (მაქსიმუმ 5 MiB)
                </label>
                <div className="flex min-h-12 min-w-0 items-center gap-3 rounded-lg border border-gray-300 bg-white p-1.5 transition focus-within:border-brand focus-within:ring-2 focus-within:ring-brand/20">
                    <input
                        id={fileId}
                        name="file"
                        type="file"
                        accept=".xlsx"
                        className="sr-only"
                        aria-labelledby={`${fileId}-label`}
                        aria-describedby={upload.errors.file ? `${fileId}-error` : `${fileId}-name`}
                        aria-invalid={Boolean(upload.errors.file)}
                        disabled={upload.processing}
                        onChange={(event) => upload.setData('file', event.target.files?.[0] ?? null)}
                    />
                    <label
                        htmlFor={fileId}
                        className={`inline-flex min-h-9 shrink-0 items-center gap-2 rounded-md bg-brand-soft px-3 text-sm font-medium text-brand transition hover:bg-brand-border/50 ${upload.processing ? 'cursor-wait opacity-60' : 'cursor-pointer'}`}
                    >
                        <Icon name="imports" className="h-4 w-4" />
                        აირჩიე ფაილი
                    </label>
                    <span
                        id={`${fileId}-name`}
                        aria-live="polite"
                        title={upload.data.file?.name}
                        className={`min-w-0 truncate pr-2 text-sm ${upload.data.file ? 'text-ink' : 'text-gray-500'}`}
                    >
                        {upload.data.file?.name ?? 'ფაილი არ არის არჩეული'}
                    </span>
                </div>
            </div>
            <PrimaryButton className="min-h-12" disabled={upload.processing || !upload.data.file}>
                {upload.processing ? 'იტვირთება…' : 'ატვირთვა'}
            </PrimaryButton>
            {upload.errors.file && (
                <p id={`${fileId}-error`} role="alert" className="w-full text-sm text-red-600">
                    {upload.errors.file}
                </p>
            )}
            {upload.progress && <p role="status">{upload.progress.percentage}%</p>}
        </form>
    );
}
